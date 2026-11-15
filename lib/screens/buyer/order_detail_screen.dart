import 'package:flutter/material.dart';
import '../../models/buyer_order.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/status_style.dart';
import '../../widgets/titled_card.dart';

const _deliveryStatusLabels = {
  'available': 'Waiting for a rider',
  'accepted': 'Rider assigned',
  'picked_up': 'Picked up',
  'out_for_delivery': 'Out for delivery',
  'delivered': 'Delivered',
  'completed': 'Completed',
};

/// Order details / tracking. Shows the rider's live delivery status once a
/// shipment exists for this order (created at checkout, updated by the
/// assigned rider) — same `shipments` table Rider screens write to, so
/// updates show up here without any extra plumbing.
class OrderDetailScreen extends StatefulWidget {
  final BuyerOrder order;

  const OrderDetailScreen({super.key, required this.order});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final _shipmentService = ShipmentService();
  late Future<String?> _deliveryStatusFuture;

  @override
  void initState() {
    super.initState();
    _deliveryStatusFuture = _shipmentService.fetchStatusForOrder(widget.order.id);
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final style = StatusStyle.forOrderStatus(order.status);
    return Scaffold(
      appBar: AppBar(title: Text(order.orderNumber)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TitledCard(
            title: 'Status',
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: style.background,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    order.statusLabel,
                    style: TextStyle(fontWeight: FontWeight.w700, color: style.color, fontSize: 12),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  'Placed ${_formatDate(order.createdAt)}',
                  style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          FutureBuilder<String?>(
            future: _deliveryStatusFuture,
            builder: (context, snapshot) {
              final status = snapshot.data;
              if (status == null) return const SizedBox.shrink();
              return Column(
                children: [
                  TitledCard(
                    title: 'Delivery Status',
                    child: Text(
                      _deliveryStatusLabels[status] ?? status.replaceAll('_', ' '),
                      style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.pinkDark),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              );
            },
          ),
          TitledCard(
            title: 'Seller',
            child: Text(order.sellerName),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Items',
            child: Column(
              children: order.items.map((item) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                            if (item.variantLabel.isNotEmpty)
                              Text(
                                item.variantLabel,
                                style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                              ),
                            Text(
                              '${formatPeso(item.price)} × ${item.qty}',
                              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        formatPeso(item.price * item.qty),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Delivery address',
            child: Text(
              order.deliverTo.isEmpty ? 'Not provided' : order.deliverTo,
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Payment',
            child: Text(order.paymentMethodName ?? 'Not provided'),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Order Summary',
            child: Column(
              children: [
                SummaryRow('Subtotal', order.subtotal),
                SummaryRow('Shipping', order.shippingAmount),
                const Divider(),
                SummaryRow('Total', order.total, bold: true),
              ],
            ),
          ),
        ],
      ),
    );
  }


  String _formatDate(DateTime date) {
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }
}
