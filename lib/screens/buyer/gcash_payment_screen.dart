import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image/image.dart' as image;
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/delivery_service.dart';
import '../../services/platform_payment_service.dart';
import '../../services/payment_verification_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/titled_card.dart';

class GcashPaymentScreen extends StatefulWidget {
  final List<String> orderIds;
  final PlatformPaymentService? service;
  final bool startAtSubmission;
  const GcashPaymentScreen({
    super.key,
    required this.orderIds,
    this.service,
    this.startAtSubmission = false,
  });
  @override
  State<GcashPaymentScreen> createState() => _GcashPaymentScreenState();
}

class _GcashPaymentScreenState extends State<GcashPaymentScreen>
    with WidgetsBindingObserver, DeliveryRefresh<GcashPaymentScreen> {
  late final _service = widget.service ?? PlatformPaymentService();
  final _reference = TextEditingController();
  final _form = GlobalKey<FormState>();
  Map<String, dynamic>? _detail, _config;
  Object? _error;
  int _index = 0;
  late bool _next = widget.startAtSubmission;
  bool _busy = false, _loading = true;
  File? _receipt;
  String? _path;
  String _submission = DeliveryService.requestId();
  String get _id => widget.orderIds[_index];
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (!_busy) await _load();
  }

  Future<void> _load() async {
    try {
      final detail = await _service.detail(_id);
      final config = detail['can_submit'] == true
          ? await _service.configuration()
          : null;
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _config = config;
        _loading = false;
        _error = null;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _pick() async {
    try {
      final picked = await ImagePicker().pickImage(source: ImageSource.gallery);
      if (picked == null) return;
      final bytes = await picked.readAsBytes();
      final ext = picked.path.split('.').last.toLowerCase();
      if (!['jpg', 'jpeg', 'png'].contains(ext) ||
          bytes.isEmpty ||
          bytes.length > 5 * 1024 * 1024 ||
          image.decodeImage(bytes) == null) {
        throw const FormatException(
          'Choose a valid JPG or PNG screenshot up to 5 MB.',
        );
      }
      if (mounted) {
        setState(() {
          _receipt = File(picked.path);
          _path = null;
        });
      }
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  Future<void> _submit() async {
    if (_busy || _form.currentState?.validate() != true) return;
    if (_receipt == null) {
      AppSnackBar.info(context, 'Choose your transaction screenshot.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      _path ??= await PaymentVerificationService().uploadProof(_receipt!);
      final detail = await _service.submit(
        orderId: _id,
        submissionId: _submission,
        reference: _reference.text.trim(),
        path: _path!,
        amount: '${_detail!['amount_due']}',
      );
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _submission = DeliveryService.requestId();
        _path = null;
        _receipt = null;
        _reference.clear();
      });
      DeliveryService.updates.add(null);
      AppSnackBar.success(
        context,
        'Receipt submitted. Payment is awaiting admin verification.',
      );
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _date(dynamic value) {
    final date = DateTime.tryParse('$value')?.toLocal();
    return date == null
        ? 'Not recorded'
        : '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('GCash payment')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          if (widget.orderIds.length > 1)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Text(
                'Payment ${_index + 1} of ${widget.orderIds.length}. Each seller order needs its own transfer and reference.',
              ),
            ),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (_error != null) ...[
            Text(friendlyError(_error!)),
            TextButton(
              onPressed: _busy ? null : _load,
              child: const Text('Refresh payment status'),
            ),
          ],
          if (_detail case final d?) ...[
            TitledCard(
              title: '${d['order_number']}',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    paymentStatusLabel('${d['status']}'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Exact amount payable',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  Text(
                    formatPeso(double.parse('${d['amount_due']}')),
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  if (d['submitted_at'] != null) ...[
                    const SizedBox(height: 12),
                    const Text(
                      'Payment submitted successfully. Receipt submission is not proof that payment has been received.',
                    ),
                    Text('Submitted: ${_date(d['submitted_at'])}'),
                    if (d['verification_deadline'] != null)
                      Text(
                        'Expected verification by: ${_date(d['verification_deadline'])}',
                      ),
                  ],
                  if (d['status'] == 'pending_verification')
                    const Text(
                      'PocketFinds admin is checking your transaction. Fulfillment starts after payment is verified.',
                    ),
                  if (d['rejection_reason'] != null)
                    Text('Reason: ${d['rejection_reason']}'),
                  if (d['support_email'] != null)
                    TextButton.icon(
                      onPressed: () => launchUrl(
                        Uri(
                          scheme: 'mailto',
                          path: '${d['support_email']}',
                          queryParameters: {
                            'subject': 'Payment support: ${d['order_number']}',
                          },
                        ),
                      ),
                      icon: const Icon(Icons.support_agent),
                      label: const Text('Contact support'),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_config case final c? when d['can_submit'] == true) ...[
              if (!_next)
                TitledCard(
                  title: 'Pay PocketFinds',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (c['qr_data'] != null)
                        Image.memory(
                          base64Decode('${c['qr_data']}'.split(',').last),
                          height: 240,
                          fit: BoxFit.contain,
                          semanticLabel: 'Official PocketFinds GCash QR code',
                        ),
                      const SizedBox(height: 12),
                      SelectableText(
                        '${c['account_name']}',
                        textAlign: TextAlign.center,
                      ),
                      SelectableText(
                        '${c['account_number']}',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      for (final instruction in c['instructions'] as List)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Text('$instruction'),
                        ),
                      FilledButton(
                        onPressed: () => setState(() => _next = true),
                        child: const Text('Next'),
                      ),
                    ],
                  ),
                ),
              if (_next)
                Form(
                  key: _form,
                  child: TitledCard(
                    title: d['status'] == 'rejected'
                        ? 'Additional payment evidence'
                        : 'Submit payment receipt',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_receipt != null)
                          Image.file(
                            _receipt!,
                            height: 220,
                            fit: BoxFit.contain,
                            semanticLabel: 'Selected payment receipt',
                          ),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : _pick,
                          icon: const Icon(Icons.photo_library_outlined),
                          label: Text(
                            _receipt == null
                                ? 'Choose screenshot'
                                : 'Replace screenshot',
                          ),
                        ),
                        const Text('JPG or PNG, up to 5 MB.'),
                        const SizedBox(height: 16),
                        if (d['reference_required'] != false)
                          TextFormField(
                            controller: _reference,
                            enabled: !_busy,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'GCash reference number',
                            ),
                            validator: (v) =>
                                RegExp(r'^[0-9]{10,20}$')
                                    .hasMatch(v?.trim() ?? '')
                                ? null
                                : 'Enter the 10–20 digit reference number.',
                          ),
                        const SizedBox(height: 16),
                        Text(
                          'Amount paid: ${formatPeso(double.parse('${d['amount_due']}'))}',
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _busy ? null : _submit,
                          child: Text(
                            _busy ? 'Submitting…' : 'Submit for verification',
                          ),
                        ),
                        TextButton(
                          onPressed: _busy
                              ? null
                              : () => setState(() => _next = false),
                          child: const Text('View payment account details'),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
            if (d['can_submit'] != true && _index + 1 < widget.orderIds.length)
              FilledButton(
                onPressed: () {
                  setState(() {
                    _index++;
                    _next = false;
                    _receipt = null;
                    _path = null;
                    _reference.clear();
                    _submission = DeliveryService.requestId();
                    _detail = null;
                    _loading = true;
                  });
                  _load();
                },
                child: const Text('Continue to next seller order'),
              ),
          ],
        ],
      ),
    ),
  );
}
