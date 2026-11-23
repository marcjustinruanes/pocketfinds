import 'package:flutter/material.dart';

import '../../models/buyer_order.dart';
import '../../models/product.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/product_image.dart';

import 'package:intl/intl.dart';

const buyerOrderFilters = [
  'All',
  'To Pay',
  'To Ship',
  'To Receive',
  'Completed',
  'Cancelled',
];

int buyerOrderFilter(BuyerOrder order) {
  if (['cancelled', 'returned', 'refunded'].contains(order.status)) return 5;
  if (order.status == 'completed') return 4;
  if ([
        'pending_payment',
        'pending_payment_verification',
      ].contains(order.status) ||
      (order.paymentStatus != null &&
          !['paid', 'refunded'].contains(order.paymentStatus) &&
          [
            'placed',
            'payment_confirmed',
            'confirmed',
            'preparing',
            'to_ship',
          ].contains(order.status))) {
    return 1;
  }
  if ([
    'placed',
    'payment_confirmed',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'to_ship',
  ].contains(order.status)) {
    return 2;
  }
  if ([
    'picked_up',
    'at_sorting_center',
    'hub_transfer',
    'sorted',
    'assigned_to_rider',
    'in_transit',
    'out_for_delivery',
    'delivered',
    'delivery_failed',
    'rescheduled',
    'return_in_transit',
  ].contains(order.status)) {
    return 3;
  }
  return 0;
}

/// A purchased item uses its immutable order snapshot, never today's catalog price.
class PurchasedProductRow extends StatelessWidget {
  final OrderLineItem item;
  final bool compact;
  const PurchasedProductRow({
    super.key,
    required this.item,
    this.compact = false,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.symmetric(vertical: compact ? 6 : 12),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: SizedBox(
            width: compact ? 52 : 64,
            height: compact ? 52 : 64,
            child: ProductImage(imageUrl: Product.resolveImageUrl(item.image)),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.name,
                maxLines: compact ? 2 : null,
                overflow: compact ? TextOverflow.ellipsis : null,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              if (item.variantLabel.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    item.variantLabel,
                    maxLines: compact ? 1 : null,
                    overflow: compact ? TextOverflow.ellipsis : null,
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ),
              const SizedBox(height: 6),
              Text(
                '${formatPeso(item.price)} × ${item.qty}',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              if (!compact) const SizedBox(height: 4),
              if (!compact)
                Text(
                  formatPeso(((item.price * 100).round() * item.qty) / 100),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class OrderAmountRow extends StatelessWidget {
  final String label;
  final double amount;
  final bool prominent;
  const OrderAmountRow(
    this.label,
    this.amount, {
    super.key,
    this.prominent = false,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: prominent ? 14 : 12,
              fontWeight: prominent ? FontWeight.w700 : FontWeight.w400,
              color: prominent ? AppColors.textPrimary : AppColors.textMuted,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            formatPeso(amount),
            textAlign: TextAlign.end,
            style: TextStyle(
              fontSize: prominent ? 18 : 13,
              fontWeight: prominent ? FontWeight.w700 : FontWeight.w500,
              color: prominent ? AppColors.pink : AppColors.textPrimary,
            ),
          ),
        ),
      ],
    ),
  );
}

Future<bool> confirmBuyerReceipt(BuildContext context) async =>
    await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm order received?'),
        content: const Text(
          'Confirm only after you have physically received and checked your parcel. This records completion of your order.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm Received'),
          ),
        ],
      ),
    ) ??
    false;

/// Only recorded timestamps count as completed; upcoming stages come from the API.
class BuyerDeliveryTimeline extends StatelessWidget {
  final List<Map> stages;
  final String status;
  const BuyerDeliveryTimeline({
    super.key,
    required this.stages,
    required this.status,
  });
  String _date(dynamic value) {
    final date = DateTime.tryParse('$value');
    return date == null
        ? 'Date not recorded'
        : DateFormat.yMMMd().add_jm().format(date.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final terminal = [
      'cancelled',
      'refunded',
      'returned',
      'completed',
    ].contains(status);
    final visible = stages
        .where(
          (s) =>
              s['state'] != 'not_required' &&
              (!terminal || s['recorded_at'] != null),
        )
        .toList();
    final current = visible.lastIndexWhere((s) => s['recorded_at'] != null);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 8),
        for (var i = 0; i < visible.length; i++)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 28,
                  child: Icon(
                    visible[i]['recorded_at'] != null
                        ? Icons.check_circle
                        : Icons.radio_button_unchecked,
                    color: i == current
                        ? AppColors.info
                        : visible[i]['recorded_at'] != null
                        ? AppColors.success
                        : AppColors.textMuted,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${visible[i]['label']}',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: i == current
                              ? FontWeight.w700
                              : FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        visible[i]['recorded_at'] == null
                            ? 'Awaiting update'
                            : _date(visible[i]['recorded_at']),
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
