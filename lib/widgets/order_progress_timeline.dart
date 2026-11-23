import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// A horizontal dot+connector progress stepper for an order/delivery's real
/// status — generalized from Rider's `DeliveryDetailScreen` (which had this
/// motif privately, only for shipment statuses) so Buyer's Order Detail
/// screen (which previously had no visual timeline at all, just plain
/// status text) gets the same component, driven by whichever real status
/// vocabulary applies:
/// - Buyer order: `to_ship` → `in_transit`/`out_for_delivery` → `completed`
/// - Rider shipment: `accepted` → `picked_up` → `out_for_delivery` → `delivered`
///
/// [cancelled] renders a distinct terminal state instead of forcing a
/// cancelled/rejected order into the linear stepper.
class OrderProgressTimeline extends StatelessWidget {
  final List<String> stages;
  final List<String> stageLabels;
  final String currentStatus;
  final bool cancelled;

  const OrderProgressTimeline({
    super.key,
    required this.stages,
    required this.stageLabels,
    required this.currentStatus,
    this.cancelled = false,
  }) : assert(stages.length == stageLabels.length);

  @override
  Widget build(BuildContext context) {
    if (cancelled) {
      return Row(
        children: [
          Icon(Icons.cancel_outlined, color: AppColors.danger, size: 20),
          const SizedBox(width: 8),
          Text(
            'This order was cancelled.',
            style: TextStyle(
              color: AppColors.danger,
              fontWeight: FontWeight.w700,
              fontSize: 13,
            ),
          ),
        ],
      );
    }

    final currentIndex = stages.indexOf(currentStatus);
    return Column(
      children: [
        Row(
          children: List.generate(stages.length, (index) {
            final done = currentIndex >= 0 && index <= currentIndex;
            final isLast = index == stages.length - 1;
            return Expanded(
              child: Row(
                children: [
                  Container(
                    width: 22,
                    height: 22,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: done ? AppColors.pink : AppColors.border,
                    ),
                    child: done
                        ? const Icon(Icons.check, size: 13, color: Colors.white)
                        : null,
                  ),
                  if (!isLast)
                    Expanded(
                      child: Container(
                        height: 3,
                        color: currentIndex > index
                            ? AppColors.pink
                            : AppColors.border,
                      ),
                    ),
                ],
              ),
            );
          }),
        ),
        const SizedBox(height: 6),
        Row(
          children: List.generate(stages.length, (index) {
            final done = currentIndex >= 0 && index <= currentIndex;
            return Expanded(
              child: Text(
                stageLabels[index],
                textAlign: index == 0
                    ? TextAlign.left
                    : index == stages.length - 1
                    ? TextAlign.right
                    : TextAlign.center,
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: done ? FontWeight.w700 : FontWeight.w500,
                  color: done ? AppColors.pinkDark : AppColors.textMuted,
                ),
              ),
            );
          }),
        ),
      ],
    );
  }
}
