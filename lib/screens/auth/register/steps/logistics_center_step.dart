import 'package:flutter/material.dart';

import '../../../../models/logistics_center.dart';
import '../../../../theme/app_colors.dart';
import '../../../../theme/app_shadows.dart';
import '../../../../theme/app_spacing.dart';

/// Step: "Choose Logistics / Sorting Center" — a rider must apply to a
/// center and wait for approval before getting task access (see the
/// redesign brief). Real centers from `public.logistics_centers`, fetched
/// by the wizard before this step renders (no session exists yet at this
/// point — the table's public-readable for exactly this reason).
class LogisticsCenterStep extends StatelessWidget {
  final List<LogisticsCenter> centers;
  final int? selectedIndex;
  final ValueChanged<int> onSelected;

  const LogisticsCenterStep({
    super.key,
    required this.centers,
    required this.selectedIndex,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: List.generate(centers.length, (index) {
        final center = centers[index];
        final selected = selectedIndex == index;
        return Padding(
          padding: EdgeInsets.only(
            bottom: index == centers.length - 1 ? 0 : AppSpacing.md,
          ),
          child: GestureDetector(
            onTap: center.acceptingApplications
                ? () => onSelected(index)
                : null,
            child: Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: selected ? AppColors.pinkSoft : AppColors.white,
                borderRadius: BorderRadius.circular(AppRadius.card),
                border: Border.all(
                  color: selected ? AppColors.pink : AppColors.border,
                  width: selected ? 1.5 : 1,
                ),
                boxShadow: AppShadows.sm,
              ),
              child: Opacity(
                opacity: center.acceptingApplications ? 1 : 0.55,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            center.name,
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 14.5,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        if (selected)
                          Icon(
                            Icons.check_circle,
                            color: AppColors.pink,
                            size: 20,
                          ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(
                          Icons.location_on_outlined,
                          size: 13,
                          color: AppColors.textMuted,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            center.location,
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      center.address,
                      style: TextStyle(
                        fontSize: 11.5,
                        color: AppColors.textMuted,
                      ),
                    ),
                    if (center.operatingHours != null) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(
                            Icons.schedule,
                            size: 12,
                            color: AppColors.textMuted,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            center.operatingHours!,
                            style: TextStyle(
                              fontSize: 11.5,
                              color: AppColors.textMuted,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            center.availableSlots == null
                                ? 'Open applications'
                                : '${center.availableSlots} slots',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: center.acceptingApplications
                            ? AppColors.successSoft
                            : AppColors.neutralSoft,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        center.recruitmentLabel,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: center.acceptingApplications
                              ? AppColors.success
                              : AppColors.neutral,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }),
    );
  }
}
