import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../utils/backend_config.dart';

class AuthException implements Exception {
  final String message;
  final int? statusCode;
  const AuthException(this.message, {this.statusCode});
  @override
  String toString() => message;
}

class DatabaseException implements Exception {
  final String message;
  final int? statusCode;
  const DatabaseException(this.message, {this.statusCode});
  @override
  String toString() => message;
}

class BackendUser {
  final String id;
  final String? email;
  BackendUser(Map<String, dynamic> row)
    : id = row['id'].toString(),
      email = row['email'] as String?;
}

class Session {
  final String accessToken;
  final String? mediaToken;
  final BackendUser user;
  Session(Map<String, dynamic> data)
    : accessToken = data['access_token'] as String,
      mediaToken = data['media_token'] as String?,
      user = BackendUser(Map<String, dynamic>.from(data['user'] as Map));
}

class AuthResponse {
  final Session? session;
  final String? registrationToken;
  final String? email;
  final String? googleRegistrationToken;
  final String? givenNames;
  final String? lastName;
  final bool applicationSubmitted;
  BackendUser? get user => session?.user;
  AuthResponse(
    this.session, {
    this.registrationToken,
    this.email,
    this.googleRegistrationToken,
    this.givenNames,
    this.lastName,
    this.applicationSubmitted = false,
  });
}

class AuthState {
  final Session? session;
  AuthState(this.session);
}

class UserAttributes {
  final String? email;
  final String? password;
  final Map<String, dynamic>? data;
  UserAttributes({this.email, this.password, this.data});
}

enum OtpType { email }

enum OAuthProvider { google }

class FileOptions {
  final bool upsert;
  const FileOptions({this.upsert = false});
}

class BackendService {
  BackendService._();
  static final client = BackendClient();
  static Future<void> initialize() => client.auth.initialize();
}

class BackendClient {
  late final auth = BackendAuth(this);
  late final storage = BackendStorage(this);
  final http.Client transport;
  BackendClient({http.Client? transport})
    : transport = transport ?? http.Client();

  Future<dynamic> request(String path, [Map<String, dynamic>? body]) async {
    final response = await transport
        .post(
          Uri.parse('${BackendConfig.url}/$path'),
          headers: {
            'Content-Type': 'application/json',
            if (auth.currentSession != null)
              'Authorization': 'Bearer ${auth.currentSession!.accessToken}',
          },
          body: jsonEncode(body ?? {}),
        )
        .timeout(const Duration(seconds: 30));
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (_) {
      throw const DatabaseException(
        'The local PocketFinds API returned an invalid response.',
      );
    }
    if (response.statusCode >= 400) {
      final message = data is Map
          ? '${data['message'] ?? 'Request failed.'}'
          : 'Request failed.';
      if (response.statusCode == 401) {
        await auth.clearSession();
        throw AuthException(message);
      }
      if (path.startsWith('auth/'))
        throw AuthException(message, statusCode: response.statusCode);
      throw DatabaseException(message, statusCode: response.statusCode);
    }
    return data;
  }

  BackendQuery from(String table) => BackendQuery(this, table);
  Future<dynamic> rpc(String name, {Map<String, dynamic>? params}) =>
      request('rpc/$name', params);
}

class BackendAuth {
  final BackendClient client;
  Session? currentSession;
  BackendUser? get currentUser => currentSession?.user;
  final _events = StreamController<AuthState>.broadcast();
  BackendAuth(this.client);
  Stream<AuthState> get onAuthStateChange async* {
    yield AuthState(currentSession);
    yield* _events.stream;
  }

  Future<void> initialize() async {
    final saved = (await SharedPreferences.getInstance()).getString(
      'mysql_session',
    );
    if (saved != null) {
      try {
        currentSession = Session(
          Map<String, dynamic>.from(jsonDecode(saved) as Map),
        );
      } catch (_) {
        await clearSession();
      }
    }
  }

  Future<AuthResponse> _session(
    String path,
    Map<String, dynamic> body, {
    bool persist = true,
  }) async {
    final data = Map<String, dynamic>.from(
      await client.request('auth/$path', body) as Map,
    );
    if (data['access_token'] == null) {
      return AuthResponse(
        null,
        registrationToken: data['registration_token'] as String?,
        email: data['email'] as String?,
        googleRegistrationToken: data['google_registration_token'] as String?,
        givenNames: data['given_names'] as String?,
        lastName: data['last_name'] as String?,
        applicationSubmitted: data['submitted'] == true,
      );
    }
    currentSession = Session(data);
    if (persist) {
      await (await SharedPreferences.getInstance()).setString(
        'mysql_session',
        jsonEncode(data),
      );
    } else {
      await (await SharedPreferences.getInstance()).remove('mysql_session');
    }
    _events.add(AuthState(currentSession));
    return AuthResponse(currentSession);
  }

  Future<void> clearSession() async {
    final hadSession = currentSession != null;
    currentSession = null;
    await (await SharedPreferences.getInstance()).remove('mysql_session');
    if (hadSession) _events.add(AuthState(null));
  }

  Future<AuthResponse> signInWithPassword({
    required String email,
    required String password,
    bool remember = true,
  }) => _session('login', {
    'email': email,
    'password': password,
  }, persist: remember);
  Future<AuthResponse> signUp({
    required String email,
    required String password,
    Map<String, dynamic>? data,
  }) => _session('register', {
    'email': email,
    'password': password,
    'data': data,
  });
  Future<AuthResponse> registerBuyer(Map<String, dynamic> fields) =>
      _session('register-buyer', fields);
  Future<void> signOut() async {
    try {
      if (currentSession != null) await client.request('auth/logout');
    } on AuthException {
      // Expired/revoked sessions are already signed out on the server.
    } finally {
      await clearSession();
    }
  }

  Future<void> resetPasswordForEmail(String email) async {
    await client.request('auth/reset-password', {'email': email});
  }

  Future<void> signInWithOtp({
    required String email,
    bool shouldCreateUser = true,
  }) async {
    await client.request('auth/otp', {'email': email});
  }

  Future<AuthResponse> verifyOTP({
    required String email,
    required String token,
    required OtpType type,
  }) => _session('verify-otp', {'email': email, 'code': token});
  Future<AuthResponse> signInWithIdToken({
    required OAuthProvider provider,
    required String idToken,
    String? accessToken,
    String intent = 'login',
  }) => _session('google', {'id_token': idToken, 'intent': intent});
  Future<void> updateUser(UserAttributes attributes) async {
    await client.request('auth/update', {
      'email': ?attributes.email,
      'password': ?attributes.password,
      'data': ?attributes.data,
    });
  }

  Future<void> refreshSession() async {
    await _session('refresh', {});
  }
}

/// A small typed query builder. The API validates tables, columns and ownership.
class BackendQuery implements Future<List<Map<String, dynamic>>> {
  final BackendClient client;
  final String table;
  String action = 'select';
  String columns = '*';
  dynamic data;
  final List<Map<String, dynamic>> filters = [];
  final List<Map<String, dynamic>> ordering = [];
  int? rowLimit;
  Future<List<Map<String, dynamic>>>? _pending;
  BackendQuery(this.client, this.table);
  BackendQuery select([String value = '*']) {
    columns = value;
    return this;
  }

  BackendQuery insert(dynamic value) {
    action = 'insert';
    data = value;
    return this;
  }

  BackendQuery update(Map<String, dynamic> value) {
    action = 'update';
    data = value;
    return this;
  }

  BackendQuery delete() {
    action = 'delete';
    return this;
  }

  BackendQuery _filter(String column, String op, dynamic value) {
    filters.add({'column': column, 'op': op, 'value': value});
    return this;
  }

  BackendQuery eq(String column, dynamic value) => _filter(column, '=', value);
  BackendQuery ilike(String column, String value) =>
      _filter(column, 'like', value);
  BackendQuery isFilter(String column, dynamic value) =>
      _filter(column, 'is', value);
  BackendQuery inFilter(String column, List<dynamic> values) =>
      _filter(column, 'in', values);
  BackendQuery order(String column, {bool ascending = true}) {
    ordering.add({'column': column, 'ascending': ascending});
    return this;
  }

  BackendQuery limit(int value) {
    rowLimit = value;
    return this;
  }

  Future<List<Map<String, dynamic>>> _run() => _pending ??= () async {
    final result = await client.request('query', {
      'table': table,
      'action': action,
      'columns': columns,
      'filters': filters,
      'order': ordering,
      'limit': rowLimit,
      'data': data,
    });
    return (result as List)
        .map((r) => Map<String, dynamic>.from(r as Map))
        .toList();
  }();
  Future<Map<String, dynamic>?> maybeSingle() async {
    final rows = await _run();
    if (rows.length > 1) {
      throw const DatabaseException('Expected at most one result.');
    }
    return rows.isEmpty ? null : rows.single;
  }

  Future<Map<String, dynamic>> single() async {
    final row = await maybeSingle();
    if (row == null) {
      throw const DatabaseException('The requested record was not found.');
    }
    return row;
  }

  @override
  Future<R> then<R>(
    FutureOr<R> Function(List<Map<String, dynamic>>) onValue, {
    Function? onError,
  }) => _run().then(onValue, onError: onError);
  @override
  Future<List<Map<String, dynamic>>> catchError(
    Function onError, {
    bool Function(Object)? test,
  }) => _run().catchError(onError, test: test);
  @override
  Future<List<Map<String, dynamic>>> whenComplete(
    FutureOr<void> Function() action,
  ) => _run().whenComplete(action);
  @override
  Future<List<Map<String, dynamic>>> timeout(
    Duration timeLimit, {
    FutureOr<List<Map<String, dynamic>>> Function()? onTimeout,
  }) => _run().timeout(timeLimit, onTimeout: onTimeout);
  @override
  Stream<List<Map<String, dynamic>>> asStream() => _run().asStream();
}

class BackendStorage {
  final BackendClient client;
  BackendStorage(this.client);
  BackendBucket from(String bucket) => BackendBucket(client, bucket);
}

class BackendBucket {
  final BackendClient client;
  final String bucket;
  BackendBucket(this.client, this.bucket);
  Future<String> upload(
    String path,
    File file, {
    FileOptions fileOptions = const FileOptions(),
  }) async {
    final result = await client.request('upload', {
      'bucket': bucket,
      'path': path,
      'content': base64Encode(await file.readAsBytes()),
      'upsert': fileOptions.upsert,
    });
    return result['path'] as String;
  }

  String getPublicUrl(String path) {
    final url = BackendConfig.mediaUrl(path, bucket: bucket);
    final ticket = client.auth.currentSession?.mediaToken;
    return ticket != null &&
            [
              'messages',
              'identity-documents',
              'payment-proofs',
              'return-evidence',
              'rider-documents',
              'delivery-proofs',
            ].contains(bucket)
        ? '$url?media_token=${Uri.encodeQueryComponent(ticket)}'
        : url;
  }
}
