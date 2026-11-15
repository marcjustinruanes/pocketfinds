import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../utils/task_phase.dart';

/// The single most important visual element of the Courier/Rider redesign:
/// an unmissable, icon+text (never color-only) tag showing exactly what a
/// task needs from the rider. Used everywhere a task appears — dashboard,
/// task list, task detail, notifications — so the look is identical
/// throughout the app.
class TaskTypeTag extends StatelessWidget {
  final TaskType type;
  final bool large;

  const TaskTypeTag({super.key, required this.type, this.large = false});

  ({Color color, Color background, IconData icon, String label}) get _style =>
      switch (type) {
        TaskType.pickup => (
          color: const Color(0xFF7C3AED),
          background: const Color(0xFFF1E9FE),
          icon: Icons.storefront_outlined,
          label: 'PICK UP FROM SORTING CENTER',
        ),
        TaskType.delivery => (
          color: AppColors.pinkDark,
          background: AppColors.pinkSoft,
          icon: Icons.flag_outlined,
          label: 'DELIVER TO CUSTOMER',
        ),
        TaskType.pickupAndDeliver => (
          color: AppColors.info,
          background: AppColors.infoSoft,
          icon: Icons.sync_alt,
          label: 'PICKUP + DELIVERY',
        ),
      };

  @override
  Widget build(BuildContext context) {
    final s = _style;
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: large ? 12 : 9,
        vertical: large ? 7 : 5,
      ),
      decoration: BoxDecoration(
        color: s.background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(s.icon, size: large ? 15 : 12, color: s.color),
          SizedBox(width: large ? 6 : 4),
          Flexible(
            child: Text(
              s.label,
              style: TextStyle(
                color: s.color,
                fontSize: large ? 11.5 : 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.3,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
