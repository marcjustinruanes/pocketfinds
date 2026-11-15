import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';
import '../services/rider_earnings.dart';
import '../utils/formatters.dart';

/// A simple earnings bar chart for the Earnings screen — hand-rolled with
/// plain `Container`s (not a charting package). Shows [days] worth of daily
/// earnings; a 7-day series fills the width edge-to-edge (each bar
/// `Expanded`), while a longer series (e.g. 30 days) scrolls horizontally
/// with fixed-width bars instead of squeezing 30 columns into one screen.
class MiniBarChart extends StatelessWidget {
  final List<DailyEarnings> days;

  const MiniBarChart({super.key, required this.days});

  @override
  Widget build(BuildContext context) {
    if (days.isEmpty) return const SizedBox.shrink();
    final maxAmount = days
        .map((d) => d.amount)
        .fold<double>(0, (a, b) => a > b ? a : b);
    final compact = days.length > 7;

    final bars = List.generate(days.length, (index) {
      final d = days[index];
      final heightFraction = maxAmount <= 0
          ? 0.0
          : (d.amount / maxAmount).clamp(0.05, 1.0);
      final isToday = _isSameDay(d.day, DateTime.now());
      // Over a long range, only label every few bars (plus "today") so the
      // real numbers shown stay legible instead of overlapping.
      final showAmount =
          d.amount > 0 && (!compact || isToday || index % 5 == 0);

      final bar = Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          SizedBox(
            height: 12,
            child: showAmount
                ? Text(
                    formatPeso(d.amount),
                    style: AppTypography.statLabel.copyWith(fontSize: 8.5),
                    maxLines: 1,
                    overflow: TextOverflow.visible,
                  )
                : null,
          ),
          const SizedBox(height: 4),
          AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            height: 88 * heightFraction,
            decoration: BoxDecoration(
              color: isToday ? AppColors.pink : AppColors.pinkSoft,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(6),
              ),
              border: isToday ? null : Border.all(color: AppColors.pinkBorder),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            compact ? '${d.day.day}' : _weekdayLabel(d.day),
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: isToday ? FontWeight.w800 : FontWeight.w500,
              color: isToday ? AppColors.pinkDark : AppColors.textMuted,
            ),
          ),
        ],
      );

      if (!compact) {
        return Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: bar,
          ),
        );
      }
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: SizedBox(width: 28, child: bar),
      );
    });

    final row = Row(crossAxisAlignment: CrossAxisAlignment.end, children: bars);

    return SizedBox(
      height: 140,
      child: compact
          ? SingleChildScrollView(scrollDirection: Axis.horizontal, child: row)
          : row,
    );
  }

  bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  String _weekdayLabel(DateTime day) {
    const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return labels[day.weekday - 1];
  }
}
