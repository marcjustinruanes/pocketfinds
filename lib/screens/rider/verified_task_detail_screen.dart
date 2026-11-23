import 'package:flutter/material.dart';

import '../../widgets/status_badge.dart';

import 'package:url_launcher/url_launcher.dart';

import '../../models/chat_ui.dart';
import '../../models/shipment.dart';
import '../../services/conversation_service.dart';
import '../../services/delivery_service.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/primary_button.dart';
import '../buyer/conversation_detail_screen.dart';
import 'parcel_scanner_screen.dart';
import 'delivery_completion_screen.dart';

const failureReasons = {
  'buyer_unavailable': 'Buyer not home',
  'incorrect_address': 'Wrong address',
  'refused': 'Refused',
};

class TaskDetailScreen extends StatefulWidget {
  final Shipment shipment;
  final int riderId;
  const TaskDetailScreen({
    super.key,
    required this.shipment,
    required this.riderId,
  });
  @override
  State<TaskDetailScreen> createState() => _TaskDetailScreenState();
}

class _TaskDetailScreenState extends State<TaskDetailScreen>
    with WidgetsBindingObserver, DeliveryRefresh<TaskDetailScreen> {
  final _shipments = ShipmentService();
  final _delivery = DeliveryService();
  late String _status = widget.shipment.shippingStatus;
  Map<String, dynamic>? _details;
  ({String name, String contact})? _contact;
  String? _error;
  bool _busy = false;
  String? _failureReason;
  String _failureNotes = '';
  bool get _mine => widget.shipment.courierId == widget.riderId;
  @override
  void initState() {
    super.initState();
    if (_mine) _load();
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (_mine && !_busy) await _load();
  }

  Future<void> _load() async {
    try {
      final details = await _delivery.details(widget.shipment.id);
      final contact = await _shipments.fetchBuyerContact(widget.shipment.id);
      if (mounted) {
        setState(() {
          _details = details;
          _status = '${details['status']}';
          _contact = contact;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    }
  }

  Future<void> _act(
    String action, [
    Map<String, dynamic> fields = const {},
  ]) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _delivery.act(widget.shipment.id, action, fields);
      if (!mounted) return;
      setState(() => _status = '${result['status']}');
      AppSnackBar.success(
        context,
        action == 'request-otp'
            ? 'Delivery code sent to the buyer’s email.'
            : 'Delivery updated.',
      );
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _accept() async {
    setState(() => _busy = true);
    try {
      await _shipments.claim(widget.shipment.id);
      DeliveryService.updates.add(null);
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<String?> _input(
    String title,
    String label, {
    bool numeric = false,
  }) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: numeric ? TextInputType.number : TextInputType.text,
          maxLength: numeric ? 6 : 100,
          decoration: InputDecoration(labelText: label),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              if (controller.text.trim().isNotEmpty) {
                Navigator.pop(ctx, controller.text.trim());
              }
            },
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    Future<void>.delayed(const Duration(seconds: 1), controller.dispose);
    return result;
  }

  Future<void> _pickup({bool manual = false}) async {
    final code = manual
        ? await _input('Parcel pickup', 'Tracking ID on parcel')
        : await Navigator.push<String>(
            context,
            MaterialPageRoute(builder: (_) => const ParcelScannerScreen()),
          );
    if (code == null || !mounted) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm physical pickup?'),
        content: const Text(
          'Confirm only after receiving this parcel from the sorting center. The server will validate its code.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirm pickup'),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await _act('pickup', {'parcel_code': code});
    }
  }

  Future<void> _failure() async {
    String? reason = _failureReason;
    final notes = TextEditingController(text: _failureNotes);
    final form = GlobalKey<FormState>();
    final data = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, change) => AlertDialog(
          title: const Text('Delivery attempt failed'),
          content: SingleChildScrollView(
            child: Form(
              key: form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: reason,
                    decoration: const InputDecoration(labelText: 'Reason'),
                    items: failureReasons.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(
                              e.value,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => change(() => reason = value),
                    validator: (v) => v == null ? 'Select a reason.' : null,
                  ),
                  TextFormField(
                    controller: notes,
                    maxLength: 300,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Notes'),
                    validator: (v) =>
                        (reason == 'other' || (v ?? '').trim().isNotEmpty) &&
                            (v ?? '').trim().length < 10
                        ? 'Explain in at least 10 characters.'
                        : null,
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
                    'reason': reason,
                    'notes': notes.text.trim(),
                  });
                }
              },
              child: const Text('Record attempt'),
            ),
          ],
        ),
      ),
    );
    Future<void>.delayed(const Duration(seconds: 1), notes.dispose);
    if (data != null && mounted) {
      _failureReason = '${data['reason']}';
      _failureNotes = '${data['notes']}';
      await _act('fail', data);
    }
  }

  Future<void> _open(Uri uri) async {
    try {
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
          mounted) {
        AppSnackBar.error(context, 'Could not open this action.');
      }
    } catch (_) {
      if (mounted) AppSnackBar.error(context, 'Could not open this action.');
    }
  }

  void _message() {
    final id = widget.shipment.buyerId;
    if (id == null || _contact == null) return;
    final name = _contact!.name;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: '$id',
            participantName: name,
            participantAvatarInitial: name.isEmpty ? '?' : name[0],
            lastMessagePreview: '',
            lastMessageAt: DateTime.now(),
          ),
          service: BackendConversationService(myUserId: widget.riderId),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.shipment;
    final address = s.shippingAddress;
    final fullAddress =
        ['house_no', 'street', 'barangay', 'municipality', 'province', 'region']
            .map((k) => address[k]?.toString() ?? '')
            .where((v) => v.isNotEmpty && v != 'Not provided')
            .join(', ');
    final attempts = (_details?['attempts'] as List?) ?? [];
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery details')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order #${s.orderNumber}',
                      style: AppTypography.sectionTitle,
                    ),
                    const SizedBox(height: 8),
                    StatusBadge(
                      status: _status,
                      label: titleCase(_status.replaceAll('_', ' ')),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Tracking: ${_details?['tracking_number'] ?? s.trackingNumber ?? 'Available after assignment'}',
                      style: AppTypography.body,
                    ),
                  ],
                ),
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (_mine && _details == null && _error == null)
              const LinearProgressIndicator(),
            const Divider(height: 32),
            if (_mine) ...[
              Text(
                _contact?.name ?? 'Recipient',
                style: AppTypography.sectionTitle,
              ),
              const SizedBox(height: 8),
              Text(fullAddress.isEmpty ? 'Address unavailable' : fullAddress),
              if ((address['delivery_instructions'] ?? address['notes']) !=
                  null)
                Text('${address['delivery_instructions'] ?? address['notes']}'),
              Wrap(
                spacing: 8,
                children: [
                  TextButton.icon(
                    onPressed: () => _open(
                      Uri.parse(
                        'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(fullAddress)}',
                      ),
                    ),
                    icon: const Icon(Icons.navigation_outlined),
                    label: const Text('Navigate'),
                  ),
                  if (_contact?.contact.isNotEmpty == true)
                    TextButton.icon(
                      onPressed: () =>
                          _open(Uri(scheme: 'tel', path: _contact!.contact)),
                      icon: const Icon(Icons.call_outlined),
                      label: const Text('Call'),
                    ),
                  if (_contact != null)
                    TextButton.icon(
                      onPressed: _message,
                      icon: const Icon(Icons.chat_outlined),
                      label: const Text('Message'),
                    ),
                ],
              ),
            ] else
              const Text(
                'Customer contact is available after accepting this task.',
              ),
            const Divider(height: 32),
            Text('Package', style: AppTypography.sectionTitle),
            if (_details != null)
              Text(
                'Payment method: ${_details!['payment_method'] ?? 'Not recorded'}',
              ),
            if (_details != null && _details!['cod_amount'] == null)
              const Text('Prepaid: do not collect the order payment again.'),
            ...s.items.map(
              (item) => Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text('${item.qty} × ${item.name}'),
              ),
            ),
            if (_details?['cod_amount'] != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  'Collect COD: ${formatPeso(double.parse('${_details!['cod_amount']}'))}',
                  style: AppTypography.sectionTitle,
                ),
              ),
            const SizedBox(height: 24),
            if (!_mine && _status == 'sorted')
              PrimaryButton(
                label: 'Accept task',
                loading: _busy,
                onPressed: _accept,
              ),
            if (_mine && _details != null) ...[
              if ((_status == 'assigned_to_rider') &&
                  _details!['pickup_verified_at'] == null) ...[
                if (_details!['assignment_accepted_at'] == null)
                  PrimaryButton(
                    label: 'Accept assignment',
                    loading: _busy,
                    onPressed: () => _act('assignment-accept'),
                  ),
                if (_details!['assignment_accepted_at'] != null &&
                    _details!['arrived_at'] == null)
                  OutlinedButton(
                    onPressed: _busy ? null : () => _act('arrive'),
                    child: const Text('Arrived at pickup'),
                  ),
                if (_details!['arrived_at'] != null)
                  Text('Arrival recorded: ${_details!['arrived_at']}'),
                Text('Collect from: ${_details!['pickup_location']}'),
                const SizedBox(height: 12),
                PrimaryButton(
                  label: 'Scan parcel and confirm pickup',
                  loading: _busy,
                  onPressed: () => _pickup(),
                ),
                TextButton(
                  onPressed: _busy ? null : () => _pickup(manual: true),
                  child: const Text('Enter tracking ID instead'),
                ),
              ],
              if (_details!['can_start'] == true) ...[
                PrimaryButton(
                  label: 'Mark Out for Delivery',
                  loading: _busy,
                  onPressed: _details!['can_start'] == true
                      ? () => _act('start')
                      : null,
                ),
                if (_status == 'rescheduled')
                  Text('Next attempt: ${_details!['scheduled_at']}'),
              ],
              if (_status == 'out_for_delivery') ...[
                PrimaryButton(
                  label: 'Complete Delivery',
                  loading: _busy,
                  onPressed: _details!['can_verify_otp'] != true
                      ? null
                      : () async {
                          final done = await Navigator.push<bool>(
                            context,
                            MaterialPageRoute(
                              builder: (_) => DeliveryCompletionScreen(
                                shipmentId: widget.shipment.id,
                                codAmount:
                                    double.tryParse(
                                      '${_details?['cod_amount'] ?? 0}',
                                    ) ??
                                    0,
                              ),
                            ),
                          );
                          if (done == true && mounted) await _load();
                        },
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'Take a live photo at handover and confirm the collected payment. Location must be enabled to record delivery.',
                  ),
                ),
                TextButton(
                  onPressed: _busy ? null : _failure,
                  child: const Text('Delivery Failed'),
                ),
              ],
              if (_status == 'delivery_failed') ...[
                const Text(
                  'Awaiting logistics instructions. Contact your hub before retrying or returning this parcel. Keep the parcel secure until the next step is assigned. Failed attempts do not earn successful-delivery compensation.',
                ),
              ],
              if (_status == 'return_in_transit')
                Text(
                  _details!['return_received_at'] != null
                      ? 'Sorting center received the return. Awaiting physical return to the seller.'
                      : 'Return in transit. Awaiting sorting-center receipt confirmation.',
                ),
              if (_status == 'delivered' &&
                  _details!['order_status'] != 'completed')
                const Text(
                  'Handover verified. Awaiting the buyer’s Confirm Received action.',
                ),
              if (_status == 'completed' ||
                  _details!['order_status'] == 'completed')
                const Text('Delivery verified. Order completed by the buyer.'),
              if (_status == 'returned')
                const Text('Physical return received by the seller.'),
            ],
            if (attempts.isNotEmpty) ...[
              const Divider(height: 32),
              Text('Delivery attempts', style: AppTypography.sectionTitle),
              ...attempts.map(
                (a) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '#${a['attempt_number']} · ${failureReasons[a['reason']] ?? a['reason']}',
                  ),
                  subtitle: Text(
                    '${a['attempted_at']} · ${a['decision']}\n${a['notes'] ?? ''}${a['scheduled_at'] == null ? '' : '\nNext attempt: ${a['scheduled_at']}'}',
                  ),
                ),
              ),
            ],
            if (_details != null) ...[
              const Divider(height: 32),
              Text('Tracking history', style: AppTypography.sectionTitle),
              ...((_details!['events'] as List?) ?? []).map(
                (e) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(titleCase('${e['action']}'.replaceAll('-', ' '))),
                  subtitle: Text('${e['created_at']} · ${e['status']}'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
