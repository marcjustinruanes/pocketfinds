import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../widgets/primary_button.dart';

/// Shown when a real, approved PocketFinds account signs in whose role isn't
/// available on mobile (seller, admin, logistics) — an informational stop,
/// not an error, since nothing is actually wrong with the account.
class UnsupportedRoleScreen extends StatelessWidget {
  final AppUser user;

  const UnsupportedRoleScreen({super.key, required this.user});

  // Signing out fires an auth-state event that the app-wide listener in
  // main.dart reacts to by navigating back to Guest Home — no need to
  // navigate from here too.
  Future<void> _logout(BuildContext context) => AuthService().signOut();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.xxl),
            child: Container(
              padding: const EdgeInsets.all(AppSpacing.xxl),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(AppRadius.xl),
                boxShadow: AppShadows.md,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: AppColors.pinkSoft,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.info_outline,
                      size: 34,
                      color: AppColors.pink,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  const Text(
                    'Not available on mobile yet',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    'Your "${user.accountType}" account is set up on PocketFinds, '
                    "but this app only supports Buyer and Rider accounts right now. "
                    'Please use the PocketFinds website to manage your account.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.textMuted, height: 1.4),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  PrimaryButton(
                    label: 'Log out',
                    onPressed: () => _logout(context),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
