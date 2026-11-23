import 'package:flutter/material.dart';

import '../models/shipment.dart';
import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import '../utils/formatters.dart';
import '../utils/status_style.dart';
import 'pressable.dart';
import 'status_badge.dart';

/// A shipment/delivery card — the single shared rendering used everywhere a
/// [Shipment] shows up as a card (Deliveries' Available/My Deliveries/
/// History tabs, and the Rider Dashboard's active-delivery list), replacing
/// three previously separate ad hoc renderings of the same data.
class ShipmentCard extends StatelessWidget {
  final Shipment shipment;
  final VoidCallback onTap;
  final IconData leadingIcon;

  const ShipmentCard({
    super.key,
    required this.shipment,
    required this.onTap,
    this.leadingIcon = Icons.local_shipping_outlined,
  });

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.forOrderStatus(shipment.shippingStatus);
    return Pressable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: style.background,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(leadingIcon, color: style.color, size: 22),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          shipment.orderNumber,
                          style: AppTypography.productTitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusBadge(
                        status: shipment.shippingStatus,
                        label: shipment.statusLabel,
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
                          shipment.deliverTo.isEmpty
                              ? 'Address not provided'
                              : shipment.deliverTo,
                          style: AppTypography.caption,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '${shipment.items.length} item(s)',
                        style: AppTypography.caption,
                      ),
                      Text(
                        formatPeso(shipment.total),
                        style: AppTypography.price.copyWith(fontSize: 14),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
