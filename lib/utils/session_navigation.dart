import 'package:flutter/material.dart';

import '../screens/auth/unsupported_role_screen.dart';
import '../screens/buyer/buyer_shell.dart';
import '../screens/guest/guest_home_screen.dart';
import '../screens/rider/rider_shell.dart';
import '../services/auth_service.dart';

/// Turns a [SessionResolution] into the right screen and replaces the whole
/// navigation stack with it — used right after login/registration and at
/// app startup, so both paths land in the same place for the same account
/// state instead of duplicating this logic twice.
Future<void> navigateForSession(
  BuildContext context,
  SessionResolution resolution,
) async {
  Widget destination;
  switch (resolution.outcome) {
    case SessionOutcome.buyer:
      destination = BuyerShell(user: resolution.user!);
    case SessionOutcome.rider:
      destination = RiderShell(user: resolution.user!);
    case SessionOutcome.unsupportedRole:
      destination = UnsupportedRoleScreen(user: resolution.user!);
    case SessionOutcome.blocked:
    case SessionOutcome.error:
      // Deliberately NOT signing out here: they still have a valid local API
      // session, just not one the mobile app can do anything with yet. If
      // an admin approves them later, simply reopening the app will resolve
      // straight to their real home next time — no re-login needed. Signing
      // out here would also re-trigger this same listener via the
      // resulting `signedOut` event, racing with this navigation.
      destination = GuestHomeScreen(flashMessage: resolution.message);
    case SessionOutcome.guest:
      destination = const GuestHomeScreen();
  }

  if (!context.mounted) return;
  Navigator.of(context).pushAndRemoveUntil(
    MaterialPageRoute(builder: (_) => destination),
    (route) => false,
  );
}
