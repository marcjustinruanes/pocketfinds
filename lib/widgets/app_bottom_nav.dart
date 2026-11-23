import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_motion.dart';
import '../theme/app_spacing.dart';
import 'pressable.dart';

/// Compact mobile navigation with the website's white surface and pink active state.
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
      decoration: BoxDecoration(
        color: AppColors.white,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height:
              AppDimensions.navigationHeight +
              (MediaQuery.textScalerOf(context).scale(12) - 12).clamp(0, 24),
          child: Row(
            children: List.generate(items.length, (index) {
              final item = items[index];
              final selected = index == currentIndex;
              return Expanded(
                child: Pressable(
                  onTap: () => onTap(index),
                  child: Semantics(
                    button: true,
                    selected: selected,
                    child: _NavItemContent(item: item, selected: selected),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}

class _NavItemContent extends StatelessWidget {
  final BottomNavigationBarItem item;
  final bool selected;

  const _NavItemContent({required this.item, required this.selected});

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.pink : AppColors.textMuted;
    return AnimatedContainer(
      duration: AppMotion.medium,
      curve: AppMotion.curve,
      margin: const EdgeInsets.symmetric(vertical: 6, horizontal: 3),
      decoration: BoxDecoration(
        color: selected ? AppColors.pinkSoft : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        border: Border(
          top: BorderSide(
            color: selected ? AppColors.pink : Colors.transparent,
            width: 2,
          ),
        ),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // A subtle lift + pop on the icon itself, on top of the outline-
          // ->filled cross-fade, so the active tab feels like it actually
          // responds rather than just recoloring.
          AnimatedSlide(
            duration: AppMotion.medium,
            curve: AppMotion.curve,
            offset: Offset(0, selected ? -0.08 : 0),
            child: AnimatedScale(
              duration: AppMotion.medium,
              curve: AppMotion.curve,
              scale: selected ? 1.1 : 1.0,
              child: IconTheme(
                data: IconThemeData(color: color, size: 22),
                child: AnimatedSwitcher(
                  duration: AppMotion.fast,
                  transitionBuilder: (child, animation) => FadeTransition(
                    opacity: animation,
                    child: ScaleTransition(scale: animation, child: child),
                  ),
                  child: KeyedSubtree(
                    key: ValueKey(selected),
                    child: selected ? item.activeIcon : item.icon,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 3),
          AnimatedDefaultTextStyle(
            duration: AppMotion.fast,
            curve: AppMotion.curve,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              color: color,
            ),
            child: Text(
              item.label ?? '',
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
