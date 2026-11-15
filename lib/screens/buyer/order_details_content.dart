import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../models/buyer_order.dart';
import 'order_presentation.dart';
import '../../theme/app_colors.dart';
import '../../widgets/status_badge.dart';
import '../../services/backend_service.dart';
import '../../utils/formatters.dart';
import '../../widgets/titled_card.dart';

String orderDetailDate(dynamic value) {
  final date = DateTime.tryParse('$value');
  return date == null
      ? 'Not recorded'
      : DateFormat.yMMMd().add_jm().format(date.toLocal());
}

class OrderDetailsContent extends StatelessWidget {
  final Map<String, dynamic> record;
  final void Function(String id)? onRelatedOrder;
  final Key? trackingKey;
  final List<Map> timeline;
  const OrderDetailsContent({
    super.key,
    required this.record,
    this.onRelatedOrder,
    this.trackingKey,
    this.timeline = const [],
  });
  double _number(dynamic value) => double.tryParse('$value') ?? 0;
  Widget _money(String label, dynamic amount) =>
      OrderAmountRow(label, _number(amount), prominent: label == 'Final total');
  Widget _card(String title, List<Widget> children) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: TitledCard(
      title: title,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) const SizedBox(height: 8),
            children[i],
          ],
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final price = record['pricing'] as Map? ?? {};
    final payment = record['payment'] as Map? ?? {};
    final address = record['address'] as Map? ?? {};
    final shipping = record['shipping'] as Map?;
    final receipt = record['receipt'] as Map? ?? {};
    final cancellation = record['cancellation'] as Map? ?? {};
    final returned = record['return'] as Map?;
    final refund = record['cancellation_refund'] as Map?;
    final fullAddress =
        [
              'house_no',
              'street',
              'barangay',
              'municipality',
              'province',
              'region',
              'postal_code',
            ]
            .map((key) => '${address[key] ?? ''}')
            .where((s) => s.trim().isNotEmpty)
            .join(', ');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _card('Order information', [
          SelectableText('${record['order_number']}'),
          Text('Placed: ${orderDetailDate(record['created_at'])}'),
          Text('Shop: ${record['seller_name'] ?? 'Not recorded'}'),
          Align(
            alignment: Alignment.centerLeft,
            child: StatusBadge(
              status: '${record['status']}',
              label: titleCase('${record['status']}'.replaceAll('_', ' ')),
            ),
          ),
          Text(
            'Payment status: ${titleCase('${payment['status'] ?? 'Not recorded'}'.replaceAll('_', ' '))}',
          ),
        ]),
        _card('Products purchased', [
          for (final entry
              in (record['items'] as List? ?? []).asMap().entries) ...[
            if (entry.key > 0) const Divider(height: 1),
            PurchasedProductRow(
              item: OrderLineItem.fromMap(
                Map<String, dynamic>.from(entry.value as Map),
              ),
            ),
          ],
          const Text('Product details and prices were saved at checkout.'),
        ]),
        _card('Delivery address', [
          Text(
            'Recipient: ${address['recipient_name'] ?? address['name'] ?? 'Not recorded'}',
          ),
          Text(
            'Contact: ${address['contact_no'] ?? address['phone'] ?? 'Not recorded'}',
          ),
          Text(fullAddress.isEmpty ? 'Address not recorded' : fullAddress),
          Text(
            'Delivery instructions: ${address['delivery_instructions'] ?? address['notes'] ?? 'None recorded'}',
          ),
        ]),
        _card('Price breakdown', [
          _money('Product subtotal', price['subtotal']),
          if (price['discount_split_known'] == true)
            _money('Product discounts', -_number(price['product_discount'])),
          _money('Delivery fee (this parcel)', price['shipping']),
          if (price['discount_split_known'] == true)
            _money('Shipping discounts', -_number(price['shipping_discount']))
          else ...[
            _money('Combined checkout discounts', -_number(price['discount'])),
            const Text(
              'Separate discount amounts were not recorded for this historical order.',
            ),
          ],
          const Divider(),
          _money('Final total', price['total']),
          for (final sibling in record['related_orders'] as List? ?? [])
            TextButton(
              onPressed: onRelatedOrder == null
                  ? null
                  : () => onRelatedOrder!('${sibling['id']}'),
              child: Text(
                '${sibling['order_number']} · ${sibling['seller']} · separate seller parcel',
              ),
            ),
        ]),
        _card('Payment', [
          Text('Method: ${payment['method'] ?? 'Not recorded'}'),
          _money('Order amount', payment['total']),
          if (payment['paid'] != null)
            _money(
              payment['status'] == 'collected'
                  ? 'COD collection recorded'
                  : 'Verified amount paid',
              payment['paid'],
            )
          else
            const Text('Verified amount paid: Not recorded'),
          if (payment['outstanding'] != null)
            _money('Outstanding against order total', payment['outstanding'])
          else
            const Text(
              'Outstanding amount cannot be established from the saved payment record.',
            ),
          if (payment['cancelled'] == true)
            const Text(
              'This order is cancelled. Do not send another payment; any repayment is tracked separately.',
            ),
          if (payment['reference'] != null)
            SelectableText(
              'Verified transaction reference: ${payment['reference']}',
            ),
          if (payment['status'] == 'paid' && payment['reference'] == null)
            const Text('Verified transaction reference: Not recorded'),
          if (payment['verified_at'] != null)
            Text(
              'Payment recorded: ${orderDetailDate(payment['verified_at'])}',
            ),
          if (payment['status'] == 'collected')
            const Text(
              'Hub cash-remittance verification is separate from the buyer payment.',
            ),
        ]),
        KeyedSubtree(
          key: trackingKey,
          child: _card('Shipping and tracking', [
            if (shipping == null)
              const Text('Shipment has not been assigned.')
            else ...[
              Text('Logistics: ${shipping['company'] ?? 'Not assigned'}'),
              SelectableText(
                'Tracking number: ${shipping['tracking_number'] ?? 'Not assigned'}',
              ),
              Text(
                'Shipment status: ${titleCase('${shipping['status']}'.replaceAll('_', ' '))}',
              ),
              if (shipping['estimated_from'] != null ||
                  shipping['estimated_by'] != null)
                Text(
                  'Estimated delivery: ${orderDetailDate(shipping['estimated_from'])} – ${orderDetailDate(shipping['estimated_by'])}',
                )
              else
                const Text('Estimated delivery window is not yet available.'),
            ],
            if (shipping == null &&
                record['estimated_delivery'] is Map &&
                (record['estimated_delivery']['from'] != null ||
                    record['estimated_delivery']['by'] != null))
              Text(
                'Estimated delivery: ${orderDetailDate(record['estimated_delivery']['from'])} – ${orderDetailDate(record['estimated_delivery']['by'])}',
              ),
            if (timeline.isNotEmpty)
              BuyerDeliveryTimeline(
                stages: [
                  if (record['created_at'] != null)
                    {
                      'label': 'Order Placed',
                      'recorded_at': record['created_at'],
                      'state': 'recorded',
                    },
                  ...timeline,
                ],
                status: '${record['status']}',
              ),
            if ((record['events'] as List? ?? []).isNotEmpty) ...[
              const Divider(height: 20),
              Text(
                'Recorded delivery updates',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
            for (final event in record['events'] as List? ?? [])
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  Icons.check_circle_outline,
                  size: 20,
                  color: AppColors.info,
                ),
                title: Text(
                  titleCase('${event['label']}'),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                subtitle: Text(
                  '${orderDetailDate(event['at'])}${event['notes'] == null ? '' : '\n${event['notes']}'}',
                  style: const TextStyle(fontSize: 12),
                ),
              ),
          ]),
        ),
        _card('Delivery confirmation', [
          Text('Delivered: ${orderDetailDate(shipping?['delivered_at'])}'),
          Text(
            receipt['confirmed'] == true
                ? 'Buyer receipt: Confirmed'
                : 'Buyer receipt: No buyer confirmation recorded',
          ),
          if (receipt['completed'] == true && receipt['confirmed'] != true)
            const Text(
              'Order completed separately; no buyer confirmation event is recorded.',
            ),
          if (receipt['buyer_confirmed_at'] != null)
            Text(
              'Buyer confirmed: ${orderDetailDate(receipt['buyer_confirmed_at'])}',
            ),
          if (receipt['recorded_at'] != null)
            Text(
              'Completion recorded: ${orderDetailDate(receipt['recorded_at'])}',
            ),
          if (shipping?['proof'] != null)
            Image.network(
              BackendService.client.storage
                  .from('delivery-proofs')
                  .getPublicUrl('${shipping!['proof']}'),
              height: 180,
              errorBuilder: (_, _, _) => const Text(
                'Delivery proof could not be loaded. Pull to refresh.',
              ),
            ),
        ]),
        if (cancellation['reason'] != null ||
            returned != null ||
            refund != null)
          _card('Cancellation and refunds', [
            if (cancellation['reason'] != null)
              Text('Cancellation reason: ${cancellation['reason']}'),
            if (cancellation['notes'] != null) Text('${cancellation['notes']}'),
            if (returned != null) ...[
              Text('Return request: ${returned['status']}'),
              Text('Reason: ${returned['reason']}'),
              _money('Requested amount', returned['amount']),
              if (returned['approved_amount'] != null)
                _money('Approved refund amount', returned['approved_amount']),
              if (returned['decision_reason'] != null)
                Text('Decision: ${returned['decision_reason']}'),
              if (returned['refund_reference'] != null)
                SelectableText(
                  'Repayment reference: ${returned['refund_reference']}',
                ),
              if (returned['repayment_recorded_at'] != null)
                Text(
                  'Repayment recorded: ${orderDetailDate(returned['repayment_recorded_at'])}',
                ),
            ],
            if (refund != null) ...[
              Text('Cancellation refund: ${refund['status']}'),
              _money('Cancellation refund amount', refund['amount']),
              if (refund['reference'] != null)
                SelectableText('Repayment reference: ${refund['reference']}'),
              if (refund['recorded_at'] != null)
                Text(
                  'Repayment recorded: ${orderDetailDate(refund['recorded_at'])}',
                ),
            ],
            const Text(
              'Approval alone is not repayment. A repayment reference is shown only when recorded.',
            ),
          ]),
      ],
    );
  }
}
