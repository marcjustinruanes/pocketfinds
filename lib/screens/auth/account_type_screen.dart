import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../theme/app_motion.dart';
import '../../utils/auth_page_route.dart';
import '../../widgets/hero_auth_scaffold.dart';
import '../../widgets/pressable.dart';
import 'login_screen.dart';
import 'register/buyer_registration_screen.dart';

/// "Who are you on PocketFinds?" — only Buyer and Rider are offered here;
/// Seller registration isn't part of this app (see project scope notes).
class AccountTypeScreen extends StatelessWidget {
  const AccountTypeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return HeroAuthScaffold(
      registration: true,
      footer: Text(
        'Your data is safe and secure with us.',
        textAlign: TextAlign.center,
        style: AppTypography.caption,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Get started', style: AppTypography.pageTitle),
          const SizedBox(height: 8),
          Text(
            'Select the account type that matches what you want to do.',
            style: AppTypography.caption.copyWith(height: 1.5),
          ),
          const SizedBox(height: AppSpacing.xl),
          _RoleCard(
            icon: Icons.shopping_bag_outlined,
            name: 'Buyer',
            description: 'Browse products, place orders, and track deliveries.',
            onTap: () =>
                Navigator.of(context)
                    .push(authRoute(const BuyerRegistrationScreen())),
          ),
          const SizedBox(height: AppSpacing.md),
          _RoleCard(
            icon: Icons.two_wheeler_outlined,
            name: 'Rider',
            description:
                'Apply to a logistics company and deliver with PocketFinds.',
            onTap: () => Navigator.of(context).push(
              authRoute(const BuyerRegistrationScreen(accountType: 'rider')),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text("Already have an account? ", style: AppTypography.caption),
              TextButton(
                onPressed: () =>
                    Navigator.of(context)
                        .pushReplacement(authRoute(const LoginScreen())),
                style: TextButton.styleFrom(
                  foregroundColor: AppColors.pinkDark,
                ),
                child: const Text('Sign in'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RoleCard extends StatefulWidget {
  final IconData icon;
  final String name;
  final String description;
  final VoidCallback onTap;

  const _RoleCard({
    required this.icon,
    required this.name,
    required this.description,
    required this.onTap,
  });

  @override
  State<_RoleCard> createState() => _RoleCardState();
}

class _RoleCardState extends State<_RoleCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    // The scale-on-press itself is delegated to the shared `Pressable` (same
    // primitive every other tappable card in the app uses). A Listener tracks
    // pointer feedback without competing with Pressable for the tap gesture.
    return Pressable(
      onTap: widget.onTap,
      child: Listener(
        onPointerDown: (_) => setState(() => _pressed = true),
        onPointerCancel: (_) => setState(() => _pressed = false),
        onPointerUp: (_) => setState(() => _pressed = false),
        child: AnimatedContainer(
          duration: AppMotion.fast,
          curve: AppMotion.curve,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            border: Border.all(
              color: _pressed ? AppColors.pink : AppColors.border,
              width: _pressed ? 1.4 : 1,
            ),
            borderRadius: BorderRadius.circular(AppRadius.card),
            color: AppColors.white,
            boxShadow: _pressed ? null : AppShadows.sm,
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: AppColors.pinkGradient,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(widget.icon, color: AppColors.white, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.name,
                      style: AppTypography.productTitle.copyWith(fontSize: 15),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      widget.description,
                      style: AppTypography.caption.copyWith(height: 1.3),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              AnimatedRotation(
                turns: _pressed ? 0.04 : 0,
                duration: AppMotion.fast,
                child: Icon(Icons.chevron_right, color: AppColors.textMuted),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
