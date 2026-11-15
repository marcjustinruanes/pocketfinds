import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_motion.dart';
import '../theme/app_typography.dart';

/// One option in a [SegmentedControl].
class SegmentedControlOption<T> {
  final T value;
  final String label;

  const SegmentedControlOption(this.value, this.label);
}

/// A pill-style, horizontally scrollable filter bar — the "Editorial
/// Marketplace" replacement for the default underline `TabBar` look on
/// Buyer Orders' and Rider Deliveries' status filters. Purely a value/
/// onChanged control; it doesn't own any `TabController` itself, so it can
/// sit alongside an existing `TabController`-driven `TabBarView` without
/// touching that screen's data-fetching logic.
class SegmentedControl<T> extends StatelessWidget {
  final List<SegmentedControlOption<T>> options;
  final T value;
  final ValueChanged<T> onChanged;

  const SegmentedControl({
    super.key,
    required this.options,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: options.map((option) {
          final selected = option.value == value;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: GestureDetector(
              onTap: () => onChanged(option.value),
              child: AnimatedContainer(
                duration: AppMotion.fast,
                curve: AppMotion.curve,
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: selected ? AppColors.pinkSoft : Colors.transparent,
                  borderRadius: BorderRadius.circular(6),
                  border: Border(
                    bottom: BorderSide(
                      color: selected ? AppColors.pinkDark : AppColors.border,
                      width: selected ? 2 : 1,
                    ),
                  ),
                ),
                child: Text(
                  option.label,
                  style: AppTypography.label.copyWith(
                    color: selected ? AppColors.pinkDark : AppColors.textMuted,
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
