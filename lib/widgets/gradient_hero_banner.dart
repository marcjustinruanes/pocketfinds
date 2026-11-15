import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';

/// The pink gradient hero/welcome banner — one shared widget instead of the
/// three near-identical hand-rolled copies that used to live in
/// `guest_home_screen.dart`, `home_tab.dart`, and `dashboard_tab.dart`
/// (same gradient stops, same decorative corner circle, same headline
/// layout, drifting independently). Keeping it in one place means a future
/// tweak to the brand gradient only has to happen once.
class GradientHeroBanner extends StatelessWidget {
  final String? eyebrow;
  final String title;
  final String subtitle;
  final Widget? action;
  final IconData? leadingIcon;

  const GradientHeroBanner({
    super.key,
    this.eyebrow,
    required this.title,
    required this.subtitle,
    this.action,
    this.leadingIcon,
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.xl),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.xl),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF4A1836), Color(0xFFA52B6C), Color(0xFFEF7EB5)],
            stops: [0, 0.55, 1],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              right: -60,
              top: -60,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.10),
                ),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (eyebrow != null) ...[
                  Row(
                    children: [
                      if (leadingIcon != null) ...[
                        Icon(leadingIcon, color: Colors.white, size: 16),
                        const SizedBox(width: 6),
                      ],
                      Text(
                        eyebrow!.toUpperCase(),
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.4,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                ],
                Text(
                  title,
                  style: AppTypography.display.copyWith(
                    color: Colors.white,
                    fontSize: 24,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                if (action != null) ...[
                  const SizedBox(height: AppSpacing.lg),
                  action!,
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
