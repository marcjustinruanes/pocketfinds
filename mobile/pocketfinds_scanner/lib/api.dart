import 'dart:convert';
import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiError implements Exception {
  final int status;
  final String message;
  ApiError(this.status, this.message);
  @override
  String toString() => message;
}

class ScannerApi {
  final Uri base;
  final http.Client client;
  String? token;
  ScannerApi(String url, {http.Client? client})
    : base = Uri.parse(url.endsWith('/') ? url : '$url/'),
      client = client ?? http.Client() {
    if (!base.hasAuthority ||
        base.userInfo.isNotEmpty ||
        base.hasQuery ||
        base.hasFragment ||
        !(base.scheme == 'https' || (kDebugMode && base.scheme == 'http')) ||
        !base.path.endsWith('/api/v1/mobile/')) {
      throw ArgumentError(
        'Use an HTTPS URL ending in /api/v1/mobile (HTTP only in debug builds).',
      );
    }
  }
  String get namespace => base.toString();
  Future<Map<String, dynamic>> call(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
    final http.Response response;
    try {
      response =
          await (body == null
                  ? client.get(base.resolve(path), headers: headers)
                  : client.post(
                      base.resolve(path),
                      headers: headers,
                      body: jsonEncode(body),
                    ))
              .timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw ApiError(
        503,
        'PocketFinds did not respond. Check your connection and retry.',
      );
    } on http.ClientException {
      throw ApiError(
        503,
        'Unable to connect to PocketFinds. Check your connection and retry.',
      );
    }
    Map<String, dynamic> data;
    try {
      data = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiError(
        503,
        'PocketFinds is unavailable. Check your connection and retry.',
      );
    }
    if (response.statusCode >= 400) {
      throw ApiError(
        response.statusCode,
        response.statusCode >= 500
            ? 'Server unavailable. Retry later.'
            : (data['message'] as String? ?? 'Request failed.'),
      );
    }
    return data;
  }
}

String pairingToken(String value, Uri base) {
  if (RegExp(r'^[A-Za-z0-9]{64}$').hasMatch(value)) return value;
  final uri = Uri.tryParse(value);
  if (uri == null ||
      !uri.hasAuthority ||
      uri.origin != base.origin ||
      uri.hasQuery ||
      uri.hasFragment ||
      uri.userInfo.isNotEmpty) {
    throw ArgumentError('Scan a pairing QR from this server.');
  }
  final match = RegExp(
    r'^/logistics/scan/phone/([A-Za-z0-9]{64})$',
  ).firstMatch(uri.path);
  if (match == null) throw ArgumentError('This is not a computer pairing QR.');
  return match.group(1)!;
}
