import 'package:flutter/material.dart';

import '../models/shipment.dart';
import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import '../utils/formatters.dart';
import '../utils/task_phase.dart';
import 'pressable.dart';
import 'status_badge.dart';
import 'task_type_tag.dart';

/// The card used everywhere a task appears (Dashboard, Tasks, History) —
/// the type tag up top (impossible to miss, derived from the shipment's
/// real status via [taskTypeForShipment]), then whichever leg is currently
/// relevant: the seller before pickup, the customer after.
class TaskCard extends StatelessWidget {
  final Shipment shipment;
  final VoidCallback onTap;

  const TaskCard({super.key, required this.shipment, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final type = taskTypeForShipment(shipment);
    final showPickup =
        type == TaskType.pickup || (type == TaskType.pickupAndDeliver);
    final name = showPickup ? 'Sorting center' : null;
    final address = showPickup ? shipment.pickupLocation : shipment.deliverTo;
    final itemCount = shipment.items.fold<int>(0, (sum, i) => sum + i.qty);

    return Pressable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                TaskTypeTag(type: type),
                StatusBadge(
                  status: shipment.shippingStatus,
                  label: shipment.statusLabel,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Order #${shipment.orderNumber}',
                    style: AppTypography.productTitle,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Text(
                  'Shipping\n${formatPeso(shipment.deliveryFee)}',
                  style: AppTypography.price.copyWith(fontSize: 14),
                ),
              ],
            ),
            const SizedBox(height: 4),
            if (shipment.trackingNumber != null)
              Text(
                shipment.trackingNumber!,
                style: AppTypography.caption,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            Text(
              '📦 $itemCount item${itemCount == 1 ? '' : 's'}',
              style: AppTypography.caption,
            ),
            const SizedBox(height: 6),
            if (name != null) ...[
              Text(
                name,
                style: AppTypography.body.copyWith(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 2),
            ],
            if (address != null && address.isNotEmpty)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.location_on_outlined,
                    size: 13,
                    color: AppColors.textMuted,
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      address,
                      style: AppTypography.caption,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
