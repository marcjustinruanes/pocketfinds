import 'package:supabase_flutter/supabase_flutter.dart';
import '../models/app_user.dart';
import 'supabase_service.dart';

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

/// Wraps Supabase Auth (real signUp/signIn/signOut/session — no custom
/// password handling) plus the two `SECURITY DEFINER` RPC functions that
/// bridge to the existing `public.users`/`rider_profiles` tables.
class AuthService {
  /// Set to true while a screen is running its own multi-step auth flow
  /// (see RegisterScreen) so the app-wide auth-state listener in `main.dart`
  /// doesn't also try to resolve/navigate mid-flow and race with it.
  static bool suppressGlobalNavigation = false;

  GoTrueClient get _auth => SupabaseService.client.auth;

  Stream<AuthState> get onAuthStateChange => _auth.onAuthStateChange;
  Session? get currentSession => _auth.currentSession;

  Future<AuthResponse> signIn({
    required String email,
    required String password,
  }) {
    return _auth.signInWithPassword(email: email.trim(), password: password);
  }

  /// [metadata] becomes the new Supabase Auth user's `user_metadata` (visible
  /// in the JWT once a session exists) — `complete_registration()` reads the
  /// registration form fields back out of it, since there may be no session
  /// yet at the moment this call returns (e.g. email confirmation pending).
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

  /// The caller's own full profile row, or null if none exists yet.
  Future<AppUser?> fetchMyProfile() async {
    final row = await SupabaseService.client.rpc('get_my_profile');
    if (row == null) return null;
    final map = Map<String, dynamic>.from(row as Map);
    if (map['id'] == null) return null; // no matching row — all columns null
    return AppUser.fromMap(map);
  }

  Future<AppUser> completeRegistration() async {
    final row = await SupabaseService.client.rpc('complete_registration');
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
  }) async {
    final row = await SupabaseService.client.rpc(
      'update_my_profile',
      params: {
        'p_contact_no': contactNo,
        'p_house_no': houseNo,
        'p_street': street,
        'p_barangay': barangay,
        'p_municipality': municipality,
        'p_province': province,
      },
    );
    return AppUser.fromMap(Map<String, dynamic>.from(row as Map));
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
        message:
            'Your account is still pending admin approval. Please wait for confirmation.',
      );
    }
    if (user.isRejected) {
      return const SessionResolution(
        SessionOutcome.blocked,
        message:
            'Your account has been rejected. Please contact support for assistance.',
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
