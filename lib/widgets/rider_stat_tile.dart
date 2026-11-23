import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';

/// A KPI tile — icon, big number, small caption — used on both the Rider
/// Dashboard's "Today's Overview" row and the Earnings screen. Extracted
/// from `RiderDashboardTab`'s original private `_statTile` so both screens
/// share one implementation instead of two near-identical copies.
class RiderStatTile extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final Color background;

  const RiderStatTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.background,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 32,
            height: 32,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: background,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 10),
          Text(
            value,
            textAlign: TextAlign.center,
            style: AppTypography.price.copyWith(
              color: AppColors.textPrimary,
              fontSize: 22,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: AppTypography.caption.copyWith(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              letterSpacing: 0,
              height: 1.35,
            ),
            textAlign: TextAlign.center,
            softWrap: true,
          ),
        ],
      ),
    );
  }
}
