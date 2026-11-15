import 'order_aftercare_screen.dart';
import 'order_presentation.dart';
import 'gcash_payment_screen.dart';
import '../../models/app_user.dart';
import '../../services/website_payment_service.dart';
import '../../services/platform_payment_service.dart';
import '../../theme/app_colors.dart';
import '../../services/product_service.dart';

import 'package:flutter/material.dart';

import '../../services/auth_service.dart';
import '../../services/buyer_workflow_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/primary_button.dart';
import 'cart_tab.dart';
import '../../models/chat_ui.dart';
import '../../services/backend_service.dart';
import '../../services/conversation_service.dart';
import 'conversation_detail_screen.dart';

const cancellationReasons = [
  'Changed my mind',
  'Found a better price',
  'Ordered by mistake',
  'Payment issue',
  'Other',
];

class BuyerOrderActions extends StatefulWidget {
  final String orderId;
  final BackendClient? client;
  final AppUser? user;
  final bool compact;
  final bool platformPayment;
  final VoidCallback? onViewDetails;
  final VoidCallback? onTrack;
  const BuyerOrderActions({
    super.key,
    required this.orderId,
    this.client,
    this.user,
    this.compact = false,
    this.platformPayment = false,
    this.onViewDetails,
    this.onTrack,
  });
  @override
  State<BuyerOrderActions> createState() => _BuyerOrderActionsState();
}

class _BuyerOrderActionsState extends State<BuyerOrderActions>
    with WidgetsBindingObserver, DeliveryRefresh<BuyerOrderActions> {
  late final _service = BuyerWorkflowService(client: widget.client);
  bool _confirming = false;
  bool _canBuyAgain = false;
  Map<String, dynamic>? _order;
  bool _busy = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    refreshDeliveryData();
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (_busy) return;
    try {
      final order = await _service.act('order', {'order_id': widget.orderId});
      var canBuyAgain = false;
      if (['completed', 'cancelled'].contains(order['status'])) {
        final items = (order['details']?['items'] as List? ?? [])
            .whereType<Map>()
            .toList();
        final products = await ProductService(client: widget.client)
            .fetchByIds(items.map((i) => '${i['product_id']}'));
        canBuyAgain = items.any((item) {
          final product = products['${item['product_id']}'];
          if (product == null || !product.purchasable) return false;
          final group = '${item['variation_group'] ?? ''}';
          final value =
              '${item['variation_value'] ?? item['size'] ?? item['color'] ?? ''}';
          return product.purchaseOptions.isEmpty
              ? !product.hasVariations && group.isEmpty && value.isEmpty
              : product.purchaseOptions.any(
                  (o) =>
                      o.stock > 0 &&
                      (o.group == group || group.isEmpty) &&
                      o.value == value,
                );
        });
      }
      if (mounted) {
        setState(() {
          _order = order;
          _canBuyAgain = canBuyAgain;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    }
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    var succeeded = false;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      succeeded = true;
      DeliveryService.updates.add(null);
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        if (succeeded) await refreshDeliveryData();
      }
    }
  }

  Future<void> _cancel() async {
    if (_busy || _confirming) return;
    _confirming = true;
    String reason = cancellationReasons.first;
    final note = TextEditingController();
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, change) => AlertDialog(
          title: const Text('Cancel order'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_order?['prepaid'] == true)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text('${_order!['refund_notice']}'),
                  ),
                DropdownButtonFormField<String>(
                  initialValue: reason,
                  isExpanded: true,
                  items: cancellationReasons
                      .map((r) => DropdownMenuItem(value: r, child: Text(r)))
                      .toList(),
                  onChanged: (r) => change(() => reason = r!),
                ),
                TextField(
                  controller: note,
                  maxLength: 500,
                  decoration: const InputDecoration(labelText: 'Optional note'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Keep order'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, {
                'cancellation_reason': reason,
                'cancellation_note': note.text.trim(),
              }),
              child: const Text('Cancel order'),
            ),
          ],
        ),
      ),
    );
    Future<void>.delayed(const Duration(seconds: 1), note.dispose);
    _confirming = false;
    if (result != null && mounted) {
      await _run(() async {
        await _service.act('cancel', {'order_id': widget.orderId, ...result});
        if (mounted) AppSnackBar.success(context, 'Order cancelled.');
      });
    }
  }

  Future<void> _review() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderAftercareScreen(orderId: widget.orderId),
      ),
    );
    if (mounted) await refreshDeliveryData();
  }

  Future<void> _buyAgain() => _run(() async {
    final result = await _service.buyAgain(widget.orderId);
    final user = widget.user ?? await AuthService().fetchMyProfile();
    if (!mounted) return;
    AppSnackBar.success(context, '${result['message']}');
    if (user != null) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CartTab(user: user, client: widget.client),
        ),
      );
    }
  });
  Future<void> _reportIssue() async {
    String kind = 'not_received';
    final description = TextEditingController();
    final form = GlobalKey<FormState>();
    final report = await showDialog<Map<String, String>>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, change) => AlertDialog(
          title: const Text('Report delivery issue'),
          content: SingleChildScrollView(
            child: Form(
              key: form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: kind,
                    isExpanded: true,
                    items: const [
                      DropdownMenuItem(
                        value: 'not_received',
                        child: Text('Parcel not received'),
                      ),
                      DropdownMenuItem(
                        value: 'damaged',
                        child: Text('Parcel arrived damaged'),
                      ),
                    ],
                    onChanged: (v) => change(() => kind = v!),
                  ),
                  TextFormField(
                    controller: description,
                    maxLength: 1800,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'What happened?',
                    ),
                    validator: (v) => (v ?? '').trim().length < 10
                        ? 'Describe the issue in at least 10 characters.'
                        : null,
                  ),
                  const Text(
                    'This opens a case for the seller and logistics team. It does not confirm receipt or issue an automatic refund.',
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () {
                if (form.currentState!.validate()) {
                  Navigator.pop(ctx, {
                    'kind': kind,
                    'description': description.text.trim(),
                  });
                }
              },
              child: const Text('Submit issue'),
            ),
          ],
        ),
      ),
    );
    Future<void>.delayed(const Duration(seconds: 1), description.dispose);
    if (report != null && mounted) {
      await _run(() async {
        final result = await _service.reportIssue(
          widget.orderId,
          report['kind']!,
          report['description']!,
        );
        if (mounted) {
          AppSnackBar.success(
            context,
            'Delivery issue #${result['issue_id']} recorded.',
          );
        }
      });
    }
  }

  void _contactSeller() {
    final userId = int.tryParse(
      BackendService.client.auth.currentUser?.id ?? '',
    );
    if (userId == null || _order?['seller_id'] == null) return;
    final name = '${_order!['seller_name'] ?? 'Seller'}';
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: '${_order!['seller_id']}',
            participantName: name,
            participantAvatarInitial: name.isEmpty ? '?' : name[0],
            lastMessagePreview: '',
            lastMessageAt: DateTime.now(),
            participantRole: 'seller',
          ),
          service: BackendConversationService(myUserId: userId),
        ),
      ),
    );
  }

  Future<void> _confirmReceipt() async {
    if (_busy || _confirming || _order?['can_confirm'] != true) return;
    _confirming = true;
    final confirmed = await confirmBuyerReceipt(context);
    _confirming = false;
    if (!confirmed || !mounted || _busy) return;
    final shipping = (_order?['details'] as Map?)?['shipping'] as Map?;
    if (shipping?['id'] == null) return;
    await _run(() async {
      await (widget.client ?? BackendService.client).rpc(
        'confirm_order_received',
        params: {'p_shipment_id': shipping!['id']},
      );
      if (mounted) {
        AppSnackBar.success(context, 'Order received. Completion recorded.');
      }
    });
  }

  Future<void> _pay() async {
    final platform = widget.platformPayment;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GcashPaymentScreen(
          orderIds: [widget.orderId],
          service: platform
              ? PlatformPaymentService(client: widget.client)
              : WebsitePaymentService(client: widget.client),
        ),
      ),
    );
    if (mounted) await refreshDeliveryData();
  }

  Widget _compactActions() {
    final status = '${_order?['status']}';
    final canPay = _order?['payment_verification']?['can_submit'] == true;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_error != null) ...[
          Text(_error!, style: TextStyle(color: AppColors.danger)),
          TextButton(
            onPressed: _busy ? null : refreshDeliveryData,
            child: const Text('Retry order actions'),
          ),
        ],
        Wrap(
          alignment: WrapAlignment.end,
          spacing: 8,
          runSpacing: 8,
          children: [
            if (widget.onViewDetails != null)
              OutlinedButton(
                onPressed: _busy ? null : widget.onViewDetails,
                child: const Text('View Details'),
              ),
            if (_order?['can_cancel'] == true)
              TextButton(
                style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                onPressed: _busy ? null : _cancel,
                child: const Text('Cancel Order'),
              ),
            if (_order != null &&
                widget.onTrack != null &&
                ![
                  'cancelled',
                  'refunded',
                  'returned',
                  'completed',
                ].contains(status))
              FilledButton(
                onPressed: _busy ? null : widget.onTrack,
                child: const Text('Track Order'),
              ),
            if (canPay)
              FilledButton(
                onPressed: _busy ? null : _pay,
                child: const Text('Pay Now'),
              ),
            if (_order?['can_confirm'] == true)
              FilledButton(
                onPressed: _busy ? null : _confirmReceipt,
                child: const Text('Confirm Received'),
              ),
            if (_order?['can_review'] == true)
              OutlinedButton(
                onPressed: _busy ? null : _review,
                child: const Text('Leave a Review'),
              ),
            if (_canBuyAgain && ['completed', 'cancelled'].contains(status))
              FilledButton(
                onPressed: _busy ? null : _buyAgain,
                child: const Text('Buy Again'),
              ),
          ],
        ),
        if (_busy)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: LinearProgressIndicator(),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) => widget.compact
      ? _compactActions()
      : Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_error != null) ...[
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
              TextButton(
                onPressed: refreshDeliveryData,
                child: const Text('Refresh actions'),
              ),
            ],
            if (_order != null) ...[
              if (_order!['delivery_code'] != null)
                SelectableText(
                  'Delivery code: ${_order!['delivery_code']}\nShare this only when receiving your parcel.',
                ),
              if (_order!['delivery_proof'] is Map) ...[
                Image.network(
                  BackendService.client.storage
                      .from('delivery-proofs')
                      .getPublicUrl('${_order!['delivery_proof']['photo']}'),
                  height: 180,
                  errorBuilder: (_, _, _) => const Text(
                    'Delivery photo unavailable. Refresh to retry.',
                  ),
                ),
                Text('Delivered: ${_order!['delivery_proof']['recorded_at']}'),
              ],
              OutlinedButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) =>
                        OrderAftercareScreen(orderId: widget.orderId),
                  ),
                ),
                child: const Text('Returns, refunds and item reviews'),
              ),
              if (_order!['prepaid'] == true &&
                  _order!['status'] == 'cancelled')
                Text('${_order!['refund_notice']}'),
              if (_order!['issue'] is Map) ...[
                Text(
                  'Delivery issue #${_order!['issue']['id']}: ${_order!['issue']['status']}',
                ),
                if (_order!['issue']['resolution'] != null)
                  Text('Resolution: ${_order!['issue']['resolution']}'),
              ],
              if (_order!['can_report_issue'] == true &&
                  (_order!['issue'] == null ||
                      _order!['issue']['status'] == 'resolved'))
                OutlinedButton(
                  onPressed: _busy ? null : _reportIssue,
                  child: const Text('Report delivery issue'),
                ),
              if (_order!['seller_id'] != null)
                TextButton(
                  onPressed: _busy ? null : _contactSeller,
                  child: const Text('Contact seller'),
                ),
              if (_order!['buyer_note'] != null &&
                  '${_order!['buyer_note']}'.isNotEmpty)
                Text('Note to seller: ${_order!['buyer_note']}'),
              if (_order!['cancellation_reason'] != null)
                Text(
                  'Cancelled: ${_order!['cancellation_reason']}\n${_order!['cancellation_note'] ?? ''}',
                ),
              if (_order!['can_review'] == true)
                PrimaryButton(
                  label: 'Rate order',
                  loading: _busy,
                  onPressed: _review,
                ),
              if (_order!['review'] is Map)
                Text(
                  'Your rating: ${_order!['review']['rating']}/5\n${_order!['review']['comment'] ?? ''}',
                ),
              if (_canBuyAgain)
                OutlinedButton(
                  onPressed: _busy ? null : _buyAgain,
                  child: const Text('Buy Again'),
                ),
              if (_order!['can_cancel'] == true)
                TextButton(
                  onPressed: _busy ? null : _cancel,
                  child: const Text('Cancel order'),
                ),
              if (_order!['can_cancel'] != true &&
                  ![
                    'cancelled',
                    'completed',
                    'delivered',
                    'returned',
                  ].contains(_order!['status'])) ...[
                const TextButton(
                  onPressed: null,
                  child: Text('Cancellation unavailable'),
                ),
                Text('${_order!['cancellation_unavailable_reason']}'),
              ],
            ],
          ],
        );
}
