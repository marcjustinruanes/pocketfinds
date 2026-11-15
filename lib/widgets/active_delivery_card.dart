import 'package:flutter/material.dart';

import '../models/shipment.dart';
import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';
import '../theme/app_spacing.dart';
import '../utils/formatters.dart';
import 'pressable.dart';
import 'status_badge.dart';

/// The Dashboard's single most important element: the rider's current
/// in-progress delivery, rendered as a prominent hero card rather than just
/// another row in a list — order #, buyer, pickup/drop-off, fee, item
/// summary, and a "View Delivery" CTA into the full detail screen.
class ActiveDeliveryCard extends StatelessWidget {
  final Shipment shipment;
  final VoidCallback onTap;

  const ActiveDeliveryCard({
    super.key,
    required this.shipment,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final itemCount = shipment.items.fold<int>(0, (sum, i) => sum + i.qty);

    return Pressable(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          boxShadow: AppShadows.md,
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.pink, const Color(0xFF7A2A56)],
          ),
        ),
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'CURRENT DELIVERY',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.6,
                    ),
                  ),
                ),
                StatusBadge(
                  status: shipment.shippingStatus,
                  label: shipment.statusLabel,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              shipment.orderNumber,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            _buyerRow(),
            const SizedBox(height: AppSpacing.md),
            _addressLine(
              icon: Icons.storefront_outlined,
              label: 'Pickup',
              value: shipment.sellerName == null
                  ? (shipment.pickupLocation ?? 'Not available')
                  : '${shipment.sellerName}${shipment.pickupLocation == null ? '' : ' · ${shipment.pickupLocation}'}',
            ),
            const SizedBox(height: 6),
            _addressLine(
              icon: Icons.flag_outlined,
              label: 'Drop-off',
              value: shipment.deliverTo.isEmpty
                  ? 'Address not provided'
                  : shipment.deliverTo,
            ),
            const SizedBox(height: AppSpacing.md),
            Container(height: 1, color: Colors.white.withValues(alpha: 0.18)),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: 16,
              runSpacing: 12,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _footerStat('$itemCount item${itemCount == 1 ? '' : 's'}'),
                _footerStat('Shipping ${formatPeso(shipment.deliveryFee)}'),
                const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'View Delivery',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(width: 4),
                    Icon(
                      Icons.arrow_forward_rounded,
                      color: Colors.white,
                      size: 18,
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buyerRow() {
    final name = shipment.items.isEmpty ? 'Customer' : 'Delivery for buyer';
    return Row(
      children: [
        CircleAvatar(
          radius: 16,
          backgroundColor: Colors.white.withValues(alpha: 0.25),
          child: Text(
            name.isNotEmpty ? name[0].toUpperCase() : '?',
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            'Order #${shipment.orderNumber} · ${formatPeso(shipment.total)} order total',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.92),
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _addressLine({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 14, color: Colors.white.withValues(alpha: 0.85)),
        const SizedBox(width: 6),
        Text(
          '$label: ',
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.75),
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _footerStat(String value, {TextAlign align = TextAlign.left}) {
    return Text(
      value,
      textAlign: align,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 13,
        fontWeight: FontWeight.w800,
      ),
    );
  }
}
