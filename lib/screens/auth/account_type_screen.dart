import 'package:flutter/material.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../widgets/auth_scaffold.dart';
import '../../widgets/pressable.dart';
import 'register_screen.dart';

/// Mobile translation of `resources/views/auth/account-type.blade.php` —
/// "Who are you on PocketFinds?" Only Buyer and Rider are offered here;
/// Seller registration isn't part of this app (see project scope notes).
class AccountTypeScreen extends StatelessWidget {
  const AccountTypeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      eyebrow: 'Get Started',
      title: 'Join PocketFinds',
      subtitle: 'Select the account type that matches what you want to do.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _RoleCard(
            icon: Icons.shopping_bag_outlined,
            name: 'Buyer',
            description: 'Browse products, place orders, and track deliveries.',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const RegisterScreen(accountType: 'buyer'),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          _RoleCard(
            icon: Icons.two_wheeler_outlined,
            name: 'Rider',
            description: 'Pick up and deliver orders, manage your routes and earnings.',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const RegisterScreen(accountType: 'rider'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RoleCard extends StatelessWidget {
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
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.card),
          color: AppColors.white,
          boxShadow: AppShadows.sm,
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: AppColors.pinkGradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(icon, color: AppColors.white, size: 24),
            ),
            const SizedBox(width: AppSpacing.lg),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    description,
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted, height: 1.3),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}
