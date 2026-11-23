import 'package:flutter/material.dart';

import '../models/shipment.dart';
import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import '../utils/formatters.dart';

/// A compact card for one newly-available delivery — order #, pickup/
/// drop-off, item count, fee, and Accept/Decline actions.
///
/// **"Decline" is a local-only dismiss**, not a persisted backend action —
/// there's no per-rider "offer" concept in `shipments` (any authenticated
/// rider can claim any available shipment), so declining just hides this
/// card from the current list for this session; it'll reappear on the next
/// refresh or for any other rider. Documented here so it isn't mistaken for
/// something the server remembers.
class DeliveryRequestCard extends StatelessWidget {
  final Shipment shipment;
  final VoidCallback onTap;
  final VoidCallback onAccept;
  final VoidCallback onDecline;
  final bool accepting;

  const DeliveryRequestCard({
    super.key,
    required this.shipment,
    required this.onTap,
    required this.onAccept,
    required this.onDecline,
    this.accepting = false,
  });

  @override
  Widget build(BuildContext context) {
    final itemCount = shipment.items.fold<int>(0, (sum, i) => sum + i.qty);

    return InkWell(
      onTap: accepting ? null : onTap,
      borderRadius: BorderRadius.circular(AppRadius.card),
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
            Row(
              children: [
                Expanded(
                  child: Text(
                    shipment.orderNumber,
                    style: AppTypography.productTitle,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Text(
                  'Shipping\n${formatPeso(shipment.deliveryFee)}',
                  style: AppTypography.price.copyWith(fontSize: 15),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (shipment.pickupLocation != null) ...[
              _locationLine(
                Icons.storefront_outlined,
                'Pickup',
                shipment.sellerName == null
                    ? shipment.pickupLocation!
                    : '${shipment.sellerName} · ${shipment.pickupLocation}',
              ),
              const SizedBox(height: 4),
            ],
            _locationLine(
              Icons.flag_outlined,
              'Drop-off',
              shipment.deliverTo.isEmpty
                  ? 'Address not provided'
                  : shipment.deliverTo,
            ),
            const SizedBox(height: 8),
            Text(
              '$itemCount item${itemCount == 1 ? '' : 's'} · Order total ${formatPeso(shipment.total)}',
              style: AppTypography.caption,
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: accepting ? null : onDecline,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.textMuted,
                      side: BorderSide(color: AppColors.border),
                    ),
                    child: const Text('Decline'),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: ElevatedButton(
                    onPressed: accepting ? null : onAccept,
                    child: accepting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation(Colors.white),
                            ),
                          )
                        : const Text('Accept'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _locationLine(IconData icon, String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 13, color: AppColors.textMuted),
        const SizedBox(width: 4),
        Expanded(
          child: Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '$label: ',
                  style: AppTypography.caption.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                TextSpan(text: value, style: AppTypography.caption),
              ],
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
