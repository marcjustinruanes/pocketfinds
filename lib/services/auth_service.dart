import 'dart:io';

import 'package:flutter/foundation.dart';

import 'package:google_sign_in/google_sign_in.dart';

import 'backend_service.dart';
import '../models/app_user.dart';
import '../utils/backend_config.dart';
import '../utils/google_sign_in_error.dart';

/// What the app should do once we know whether/who is signed in — used by
/// both the app's startup routing (`AppRoot`) and right after a successful
/// login, so that logic lives in exactly one place.
enum SessionOutcome {
  guest, // no session — show Guest Home
  buyer,
  rider,
  unsupportedRole, // signed in, approved, but not buyer/rider (seller/admin/logistics)
  blocked, // signed in but pending/rejected — see [message]
  error, // something unexpected went wrong resolving the profile
}

class SessionResolution {
  final SessionOutcome outcome;
  final AppUser? user;
  final String? message;

  const SessionResolution(this.outcome, {this.user, this.message});
}

class AuthService {
  static Future<void>? _googleInitialization;

  /// Set to true while a screen is running its own multi-step auth flow
  /// (see RegistrationWizardScreen) so the app-wide auth-state listener in `main.dart`
  /// doesn't also try to resolve/navigate mid-flow and race with it.
  static bool suppressGlobalNavigation = false;

  BackendAuth get _auth => BackendService.client.auth;

  Stream<AuthState> get onAuthStateChange => _auth.onAuthStateChange;
  Session? get currentSession => _auth.currentSession;

  Future<AuthResponse> signIn({
    required String email,
    required String password,
    bool remember = true,
  }) {
    return _auth.signInWithPassword(
      email: email.trim(),
      password: password,
      remember: remember,
    );
  }

  Future<AuthResponse> signUp({
    required String email,
    required String password,
    required Map<String, dynamic> metadata,
  }) {
    return _auth.signUp(
      email: email.trim(),
      password: password,
      data: metadata,
    );
  }

  Future<void> signOut() => _auth.signOut();

  Future<void> sendPasswordResetEmail(String email) {
    return _auth.resetPasswordForEmail(email.trim());
  }

  Future<void> sendEmailOtp(String email) {
    return _auth.signInWithOtp(email: email.trim(), shouldCreateUser: true);
  }

  /// Verifies the code from [sendEmailOtp]. On success this leaves the
  /// caller with a live, confirmed session for [email] — carried through the
  /// rest of the wizard and finished off by [finalizeRegistration].
  Future<AuthResponse> verifyEmailOtp({
    required String email,
    required String code,
  }) {
    return _auth.verifyOTP(
      email: email.trim(),
      token: code.trim(),
      type: OtpType.email,
    );
  }

  Future<AuthResponse?> signInWithGoogle({
    bool register = false,
    bool verifyAccountEmail = false,
  }) async {
    try {
      final google = GoogleSignIn.instance;
      _googleInitialization ??=
          () async {
            final config = await BackendService.client.request('auth/config');
            final webId = BackendConfig.googleServerClientId.isNotEmpty
                ? BackendConfig.googleServerClientId
                : '${config['google_web_client_id'] ?? ''}';
            if (webId.isEmpty) {
              throw const AuthException(
                'Google sign-in is temporarily unavailable.',
              );
            }
            final iosId = '${config['google_ios_client_id'] ?? ''}';
            await google.initialize(
              serverClientId: webId,
              clientId: iosId.isEmpty ? null : iosId,
            );
          }().catchError((Object error) {
            _googleInitialization = null;
            throw error;
          });
      await _googleInitialization;
      if (!google.supportsAuthenticate()) {
        throw const AuthException(
          'Google sign-in is available in the Android and iOS mobile app.',
        );
      }
      final account = await google.authenticate();
      final idToken = account.authentication.idToken;
      if (idToken == null) {
        throw const AuthException(
          'Google could not verify this account. Please try again.',
        );
      }
      if (verifyAccountEmail) {
        await BackendService.client.request('buyer-web/google-email', {
          'id_token': idToken,
        });
        return null;
      }
      return await _auth.signInWithIdToken(
        provider: OAuthProvider.google,
        idToken: idToken,
        intent: register ? 'register' : 'login',
      );
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) return null;
      if (kDebugMode) debugPrint('Google sign-in failed (${error.code.name}).');
      throw AuthException(googleSignInErrorMessage(error.code.name));
    }
  }

  Future<AuthResponse> registerBuyer(Map<String, dynamic> fields) =>
      _auth.registerBuyer(fields);
  Future<void> resendRegistration(String token) async {
    await BackendService.client.request('auth/resend-registration', {
      'registration_token': token,
    });
  }

  Future<void> verifyRegistration(String token, String code) async {
    await BackendService.client.request('auth/verify-registration', {
      'registration_token': token,
      'code': code.trim(),
    });
  }

  Future<AppUser> finalizeRegistration({
    required String password,
    required Map<String, dynamic> metadata,
  }) async {
    await _auth.updateUser(UserAttributes(password: password, data: metadata));
    await _auth.refreshSession();
    return completeRegistration();
  }

  /// The caller's own full profile row, or null if none exists yet.
  Future<AppUser?> fetchMyProfile() async {
    final row = await BackendService.client.rpc('get_my_profile');
    if (row == null) return null;
    final map = Map<String, dynamic>.from(row as Map);
    if (map['id'] == null) return null; // no matching row — all columns null
    return AppUser.fromMap(map);
  }

  Future<AppUser> completeRegistration() async {
    final row = await BackendService.client.rpc('complete_registration');
    return AppUser.fromMap(Map<String, dynamic>.from(row as Map));
  }

  /// Updates the caller's own contact/address fields (not identity fields
  /// like name/email, which stay admin-controlled) via the
  /// `update_my_profile` RPC.
  Future<AppUser> updateMyProfile({
    required String contactNo,
    required String houseNo,
    required String street,
    required String barangay,
    required String municipality,
    required String province,
    String? region,
  }) async {
    final row = await BackendService.client.rpc(
      'update_my_profile',
      params: {
        'p_contact_no': contactNo,
        'p_house_no': houseNo,
        'p_street': street,
        'p_barangay': barangay,
        'p_municipality': municipality,
        'p_province': province,
        'p_region': ?region,
      },
    );
    return AppUser.fromMap(Map<String, dynamic>.from(row as Map));
  }

  Future<String> uploadAvatar(File file) async {
    final authUid = BackendService.client.auth.currentUser?.id;
    if (authUid == null) throw StateError('No active session.');
    final dot = file.path.lastIndexOf('.');
    final ext = dot == -1 ? 'jpg' : file.path.substring(dot + 1).toLowerCase();
    final path = '$authUid/avatar.${ext.isEmpty ? 'jpg' : ext}';
    await BackendService.client.storage
        .from('profile-images')
        .upload(path, file, fileOptions: const FileOptions(upsert: true));
    return BackendService.client.storage
        .from('profile-images')
        .getPublicUrl(path);
  }

  Future<AppUser> updateProfileDetails({
    required String givenNames,
    required String lastName,
    String? middleName,
    String? facebookUrl,
    String? instagramUrl,
    String? twitterUrl,
    String? tiktokUrl,
    bool? notifyEnabled,
    String? profilePicture,
  }) async {
    final row = await BackendService.client.rpc(
      'update_my_profile_details',
      params: {
        'p_given_names': givenNames,
        'p_last_name': lastName,
        'p_middle_name': ?middleName,
        'p_facebook_url': ?facebookUrl,
        'p_instagram_url': ?instagramUrl,
        'p_twitter_url': ?twitterUrl,
        'p_tiktok_url': ?tiktokUrl,
        'p_notify_enabled': ?notifyEnabled,
        'p_profile_picture': ?profilePicture,
      },
    );
    return AppUser.fromMap(Map<String, dynamic>.from(row as Map));
  }

  /// Real uniqueness check — same rule `complete_registration()` already
  /// enforces at signup — before actually committing a username change.
  Future<bool> checkUsernameAvailable(String username) async {
    final result = await BackendService.client.rpc(
      'check_username_available',
      params: {'p_username': username},
    );
    return result as bool;
  }

  Future<AppUser> updateUsername(String username) async {
    final row = await BackendService.client.rpc(
      'update_my_username',
      params: {'p_username': username},
    );
    return AppUser.fromMap(Map<String, dynamic>.from(row as Map));
  }

  Future<bool> checkEmailAvailable(String email) async {
    final result = await BackendService.client.rpc(
      'check_email_available',
      params: {'p_email': email},
    );
    return result as bool;
  }

  Future<void> requestEmailChange(String newEmail) =>
      _auth.updateUser(UserAttributes(email: newEmail));

  /// Real password change for an already-authenticated user (distinct from
  /// [sendPasswordResetEmail], which is the forgot-password flow for a
  /// signed-out user).
  Future<void> changePassword(
    String newPassword, {
    String? currentPassword,
  }) async {
    await BackendService.client.request('auth/update', {
      'password': newPassword,
      'password_confirmation': newPassword,
      'current_password': currentPassword,
    });
  }

  /// Real, self-service "Deactivate Account" — see `AppUser.isSuspended`
  /// for why this reuses the `suspended` status rather than a new one.
  /// Signs the caller out immediately after, matching what a real
  /// deactivation should do.
  Future<void> deactivateAccount() async {
    await BackendService.client.rpc('deactivate_my_account');
    await signOut();
  }

  /// One real admin account to message from Help Center.
  Future<({int adminId, String adminName})?> fetchAdminContact() async {
    final rows = await BackendService.client.rpc('fetch_admin_contact');
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return null;
    return (
      adminId: list.first['admin_id'] as int,
      adminName: list.first['admin_name']?.toString() ?? 'PocketFinds Support',
    );
  }

  /// A real, formal complaint record — the existing `complaints` table.
  Future<void> submitComplaint({
    required String type,
    required String subject,
    String? description,
  }) async {
    await BackendService.client.rpc(
      'submit_complaint',
      params: {
        'p_type': type,
        'p_subject': subject,
        'p_description': description,
      },
    );
  }

  /// Central place that decides what a session means for navigation —
  /// mirrors `AdminController::loginPost`'s pending/rejected/role checks.
  Future<SessionResolution> resolveSession() async {
    if (currentSession == null) {
      return const SessionResolution(SessionOutcome.guest);
    }

    AppUser? user;
    try {
      user = await fetchMyProfile();
      user ??= await completeRegistration();
    } catch (e) {
      return SessionResolution(
        SessionOutcome.error,
        message: 'Could not load your PocketFinds profile: $e',
      );
    }

    if (user.isPending) {
      return const SessionResolution(
        SessionOutcome.blocked,
        message: 'Your account is still pending admin approval. Please wait for confirmation.',
      );
    }
    if (user.isRejected) {
      return const SessionResolution(
        SessionOutcome.blocked,
        message: 'Your account has been rejected. Please contact support for assistance.',
      );
    }
    if (user.isSuspended) {
      return const SessionResolution(
        SessionOutcome.blocked,
        message: 'Your account is deactivated. Contact support if you\'d like to reactivate it.',
      );
    }
    if (user.isBuyer) {
      return SessionResolution(SessionOutcome.buyer, user: user);
    }
    if (user.isRider) {
      return SessionResolution(SessionOutcome.rider, user: user);
    }
    return SessionResolution(SessionOutcome.unsupportedRole, user: user);
  }
}
