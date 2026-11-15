import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';

/// Wraps a [BottomNavigationBar] with a soft top shadow instead of
/// Material's default elevation — matches the site's floating-card feel
/// (`--shadow-sm`) rather than a hard drop shadow. Shared by every shell
/// (Guest, Buyer, Rider) so the nav bar looks identical everywhere.
class AppBottomNav extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final List<BottomNavigationBarItem> items;

  const AppBottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
    required this.items,
  });

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.white,
        boxShadow: AppShadows.md,
      ),
      child: SafeArea(
        top: false,
        child: BottomNavigationBar(
          currentIndex: currentIndex,
          onTap: onTap,
          items: items,
          backgroundColor: Colors.transparent,
          elevation: 0,
        ),
      ),
    );
  }
}
