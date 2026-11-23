import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/buyer_order.dart';
import '../../models/chat_ui.dart';
import '../../services/buyer_workflow_service.dart';
import '../../services/backend_service.dart';
import '../../services/conversation_service.dart';
import '../../services/delivery_service.dart';
import '../../services/order_service.dart';
import '../../services/payment_verification_service.dart';
import '../../services/shipment_service.dart';
import '../../services/website_payment_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/titled_card.dart';
import 'buyer_order_actions.dart';
import 'conversation_detail_screen.dart';
import 'gcash_payment_screen.dart';
import 'order_details_content.dart';
import 'order_presentation.dart';

class OrderDetailScreen extends StatefulWidget {
  final BuyerOrder order;
  final int myUserId;
  final BackendClient? client;
  final bool showTracking;
  const OrderDetailScreen({
    super.key,
    required this.order,
    required this.myUserId,
    this.client,
    this.showTracking = false,
  });
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen>
    with WidgetsBindingObserver, DeliveryRefresh<OrderDetailScreen> {
  late Future<Map<String, dynamic>> _future;
  final _trackingKey = GlobalKey();
  bool _busy = false;
  bool _confirming = false;
  bool _trackingShown = false;
  @override
  void initState() {
    super.initState();
    _future = _read();
  }

  Future<Map<String, dynamic>> _read() async {
    final payload = await BuyerWorkflowService(client: widget.client)
        .act('order', {'order_id': widget.order.id});
    final shipping = (payload['details'] as Map)['shipping'] as Map?;
    if (shipping?['courier_id'] != null) {
      try {
        final rider = await ShipmentService().fetchMyRiderInfo(
          '${shipping!['id']}',
        );
        if (rider != null) {
          payload['rider'] = {
            'id': rider.riderId,
            'name': rider.name,
            'contact': rider.contact,
            'photo': rider.photo,
          };
        }
      } catch (e) {
        payload['rider_error'] = friendlyError(e);
      }
    }
    return payload;
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (_busy) return;
    setState(() {
      _future = _read();
    });
    await _future;
  }

  Future<void> _confirm(String shipment) async {
    if (_busy || _confirming) return;
    _confirming = true;
    final confirmed = await confirmBuyerReceipt(context);
    _confirming = false;
    if (!confirmed || !mounted || _busy) return;
    setState(() => _busy = true);
    try {
      await (widget.client ?? BackendService.client).rpc(
        'confirm_order_received',
        params: {'p_shipment_id': shipment},
      );
      DeliveryService.updates.add(null);
      if (mounted) {
        AppSnackBar.success(context, 'Order received. Completion recorded.');
      }
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        await refreshDeliveryData();
      }
    }
  }

  Future<void> _related(String id) async {
    if (id == widget.order.id) return;
    try {
      final order = await OrderService(client: widget.client)
          .findOrder(widget.myUserId, id);
      if (!mounted) return;
      if (order == null) {
        AppSnackBar.error(context, 'This order could not be loaded.');
        return;
      }
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => OrderDetailScreen(
            order: order,
            myUserId: widget.myUserId,
            client: widget.client,
          ),
        ),
      );
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  void _message(Map rider) {
    final name = '${rider['name']}';
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: '${rider['id']}',
            participantName: name,
            participantAvatarInitial: name.isEmpty ? '?' : name[0],
            lastMessagePreview: '',
            lastMessageAt: DateTime.now(),
          ),
          service: BackendConversationService(myUserId: widget.myUserId),
        ),
      ),
    );
  }

  Future<void> _call(String number) async {
    try {
      if (!await launchUrl(Uri(scheme: 'tel', path: number))) {
        throw Exception('Could not open the dialer.');
      }
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Order Details')),
    body: RefreshIndicator(
      onRefresh: refreshDeliveryData,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return ListView(
              children: [
                Text(friendlyError(snapshot.error!)),
                TextButton(
                  onPressed: refreshDeliveryData,
                  child: const Text('Retry'),
                ),
              ],
            );
          }
          if (!snapshot.hasData) {
            return ListView(children: const [LinearProgressIndicator()]);
          }
          final data = snapshot.data!;
          final record = Map<String, dynamic>.from(data['details'] as Map);
          final shipping = record['shipping'] as Map?;
          final rider = data['rider'] as Map?;
          final payment = data['payment_verification'] as Map?;
          if (widget.showTracking && !_trackingShown) {
            _trackingShown = true;
            WidgetsBinding.instance.addPostFrameCallback((_) {
              final target = _trackingKey.currentContext;
              if (mounted && target != null) {
                Scrollable.ensureVisible(
                  target,
                  duration: const Duration(milliseconds: 300),
                );
              }
            });
          }
          return ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              if (shipping != null)
                OutlinedButton.icon(
                  icon: const Icon(Icons.local_shipping_outlined),
                  label: const Text('Track delivery'),
                  onPressed: () {
                    final target = _trackingKey.currentContext;
                    if (target != null) {
                      Scrollable.ensureVisible(
                        target,
                        duration: const Duration(milliseconds: 300),
                      );
                    }
                  },
                ),
              OrderDetailsContent(
                record: record,
                trackingKey: _trackingKey,
                onRelatedOrder: _related,
                timeline: (data['timeline'] as List? ?? [])
                    .whereType<Map>()
                    .toList(),
              ),
              if (rider != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: TitledCard(
                    title: 'Assigned rider',
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 8,
                      children: [
                        Text('${rider['name']}'),
                        if ('${rider['contact'] ?? ''}'.isNotEmpty)
                          TextButton.icon(
                            onPressed: () => _call('${rider['contact']}'),
                            icon: const Icon(Icons.call_outlined),
                            label: const Text('Call rider'),
                          ),
                        TextButton.icon(
                          onPressed: () => _message(rider),
                          icon: MessageIcon(color: AppColors.pink),
                          label: const Text('Message rider'),
                        ),
                      ],
                    ),
                  ),
                ),
              if (data['rider_error'] != null)
                Text('Rider details unavailable: ${data['rider_error']}'),
              if (record['status'] == 'delivered' && shipping != null) ...[
                FilledButton(
                  onPressed: _busy || data['can_confirm'] != true
                      ? null
                      : () => _confirm('${shipping['id']}'),
                  child: const Text('Confirm Received'),
                ),
                if (data['can_confirm'] != true)
                  const Text(
                    'Resolve the active return/refund or delivery issue before confirming receipt.',
                  ),
              ],
              if (payment != null) ...[
                if (payment['rejection_reason'] != null)
                  Text('Payment correction: ${payment['rejection_reason']}'),
                if (payment['submitted_at'] != null)
                  Text(
                    'Receipt submitted: ${orderDetailDate(payment['submitted_at'])}',
                  ),
                if (payment['verification_deadline'] != null)
                  Text(
                    'Expected payment review: ${orderDetailDate(payment['verification_deadline'])}',
                  ),
                FilledButton.tonal(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => GcashPaymentScreen(
                        orderIds: [widget.order.id],
                        service: widget.order.platformPayment
                            ? null
                            : WebsitePaymentService(client: widget.client),
                      ),
                    ),
                  ),
                  child: const Text('Payment status / upload proof'),
                ),
              ] else if (!widget.order.platformPayment &&
                  '${(record['payment'] as Map)['method']}'
                      .toLowerCase()
                      .contains('gcash'))
                FutureBuilder<PaymentVerification?>(
                  future: PaymentVerificationService().fetch(widget.order.id),
                  builder: (context, state) => state.data == null
                      ? const SizedBox.shrink()
                      : _PaymentVerificationCard(
                          verification: state.data!,
                          onResubmitted: refreshDeliveryData,
                        ),
                ),
              const SizedBox(height: 16),
              TitledCard(
                title: 'Order actions',
                child: BuyerOrderActions(
                  orderId: widget.order.id,
                  client: widget.client,
                  platformPayment: widget.order.platformPayment,
                ),
              ),
            ],
          );
        },
      ),
    ),
  );
}

/// The real payment-verification status for one order — reference number,
/// proof thumbnail, and (only when rejected) the real reason + a real
/// resubmit flow. "Confirmed" here always means a seller (today, via the
/// backend-only `confirm_payment_verification()` RPC — no seller screens
/// exist in this app yet) actually reviewed it; a buyer's own screenshot
/// upload never self-confirms.
class _PaymentVerificationCard extends StatefulWidget {
  final PaymentVerification verification;
  final VoidCallback onResubmitted;

  const _PaymentVerificationCard({
    required this.verification,
    required this.onResubmitted,
  });

  @override
  State<_PaymentVerificationCard> createState() =>
      _PaymentVerificationCardState();
}

class _PaymentVerificationCardState extends State<_PaymentVerificationCard> {
  final _service = PaymentVerificationService();
  final _picker = ImagePicker();
  final _referenceController = TextEditingController();
  File? _newProof;
  bool _resubmitting = false;

  @override
  void dispose() {
    _referenceController.dispose();
    super.dispose();
  }

  String get _statusLabel => switch (widget.verification.status) {
    'submitted' => 'Payment Submitted',
    'under_review' => 'Under Review',
    'confirmed' => 'Payment Confirmed',
    'rejected' => 'Payment Rejected',
    _ => 'Pending',
  };

  Future<void> _pickProof() async {
    final picked = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (picked != null && mounted) {
      setState(() => _newProof = File(picked.path));
    }
  }

  Future<void> _resubmit() async {
    if (_referenceController.text.trim().isEmpty || _newProof == null) {
      AppSnackBar.info(
        context,
        'Enter a reference number and choose a new screenshot.',
      );
      return;
    }
    setState(() => _resubmitting = true);
    try {
      final path = await _service.uploadProof(_newProof!);
      await _service.submit(
        orderId: widget.verification.orderId,
        referenceNumber: _referenceController.text.trim(),
        proofPath: path,
      );
      if (!mounted) return;
      AppSnackBar.success(context, 'Payment resubmitted for review.');
      widget.onResubmitted();
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not resubmit: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _resubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = widget.verification;
    final rejected = v.status == 'rejected';
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.pageBackground,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 12,
            runSpacing: 8,
            children: [
              StatusBadge(status: v.status, label: _statusLabel),
              Text('Ref# ${v.referenceNumber}', style: AppTypography.caption),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.network(v.proofUrl, height: 120, fit: BoxFit.cover),
          ),
          if (rejected) ...[
            const SizedBox(height: 10),
            if (v.rejectionReason != null)
              Text(
                'Reason: ${v.rejectionReason}',
                style: TextStyle(color: AppColors.danger, fontSize: 12.5),
              ),
            const SizedBox(height: 10),
            TextField(
              controller: _referenceController,
              decoration: const InputDecoration(
                labelText: 'New reference number',
              ),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _pickProof,
              icon: const Icon(Icons.upload_outlined, size: 18),
              label: Text(
                _newProof == null
                    ? 'Choose new screenshot'
                    : 'Screenshot selected',
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: PrimaryButton(
                label: 'Resubmit Payment',
                loading: _resubmitting,
                onPressed: _resubmit,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
