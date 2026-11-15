import 'dart:math';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../services/backend_service.dart';
import '../../services/website_payment_service.dart';
import '../../services/platform_payment_service.dart';
import 'gcash_payment_screen.dart';
import '../../services/delivery_service.dart';
import '../../services/conversation_service.dart';
import '../../models/chat_ui.dart';
import 'conversation_detail_screen.dart';
import '../../services/payment_verification_service.dart';
import '../../utils/friendly_error.dart';
import '../../utils/formatters.dart';
import '../../widgets/app_snackbar.dart';

/// Uses the website's reservation, seller verification and placement workflow.
class GcashPurchasesScreen extends StatefulWidget {
  final String? purchaseId;
  const GcashPurchasesScreen({super.key, this.purchaseId});
  @override
  State<GcashPurchasesScreen> createState() => _GcashPurchasesScreenState();
}

class _GcashPurchasesScreenState extends State<GcashPurchasesScreen>
    with WidgetsBindingObserver, DeliveryRefresh<GcashPurchasesScreen> {
  @override
  Future<void> refreshDeliveryData() async {
    if (_busy) return;
    setState(() => _future = _load());
    await _future;
  }

  late Future<List<dynamic>> _future = _load();
  bool _busy = false;
  final _lastPaymentStates = <String, String>{};
  String? _justVerified;
  Future<List<dynamic>> _load() async {
    final purchases = List<dynamic>.from(
      await BackendService.client.request('gcash/list') as List,
    );
    for (final p in purchases) {
      final id = '${p['id']}';
      final state = '${p['payment_state']}';
      if (_lastPaymentStates.containsKey(id) &&
          _lastPaymentStates[id] != 'verified' &&
          state == 'verified') {
        _justVerified = id;
      }
      _lastPaymentStates[id] = state;
    }
    if (_justVerified != null && mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          AppSnackBar.success(
            context,
            'Payment verified. You can continue with your saved purchase.',
          );
        }
      });
      _justVerified = null;
    }
    if (widget.purchaseId != null) {
      purchases.sort(
        (a, b) => (a['id'] == widget.purchaseId ? 0 : 1).compareTo(
          b['id'] == widget.purchaseId ? 0 : 1,
        ),
      );
    }
    return purchases;
  }

  void _refresh() {
    if (mounted) setState(() => _future = _load());
  }

  void _chat(Map<dynamic, dynamic> purchase) {
    final userId = int.tryParse(
      BackendService.client.auth.currentUser?.id ?? '',
    );
    if (userId == null) return;
    final name = '${purchase['seller_name'] ?? 'Seller'}';
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: '${purchase['seller_id']}',
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

  Future<void> _act(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      _refresh();
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _submissionId() {
    final r = Random.secure();
    final b = List<int>.generate(16, (_) => r.nextInt(256));
    b[6] = (b[6] & 15) | 64;
    b[8] = (b[8] & 63) | 128;
    final h = b.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
    return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
  }

  Future<void> _proof(Map<dynamic, dynamic> purchase) => _act(() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (picked == null) return;
    final path = await PaymentVerificationService().uploadProof(
      File(picked.path),
    );
    await BackendService.client.request('gcash/proof', {
      'id': purchase['id'],
      'path': path,
      'submission_id': _submissionId(),
    });
    if (mounted) {
      AppSnackBar.success(context, 'Receipt sent for seller verification.');
    }
  });
  Future<void> _place(Map<dynamic, dynamic> purchase) => _act(() async {
    final result = await BackendService.client.request('gcash/place', {
      'id': purchase['id'],
      'revision': purchase['revision'],
    });
    if (mounted) {
      AppSnackBar.success(context, 'Order ${result['order_number']} placed.');
    }
  });
  Future<void> _reportPaid(Map<dynamic, dynamic> purchase) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Payment sent after expiry?'),
        content: TextField(
          controller: controller,
          maxLength: 1000,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Describe the payment (at least 10 characters)',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              if (controller.text.trim().length >= 10) {
                Navigator.pop(ctx, controller.text.trim());
              }
            },
            child: const Text('Report payment'),
          ),
        ],
      ),
    );
    Future<void>.delayed(const Duration(seconds: 1), controller.dispose);
    if (reason == null || !mounted) return;
    await _act(() async {
      await BackendService.client.request('gcash/report-paid', {
        'id': purchase['id'],
        'reason': reason,
      });
      if (mounted) {
        AppSnackBar.success(
          context,
          'Reported for seller reconciliation. No automatic refund has been issued.',
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('GCash purchases'),
      actions: [
        IconButton(
          onPressed: _busy ? null : _refresh,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(child: Text(friendlyError(snapshot.error!)));
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final purchases = snapshot.data!;
        if (purchases.isEmpty) {
          return const Center(child: Text('No GCash purchases yet.'));
        }
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: purchases.length,
          itemBuilder: (context, index) {
            final p = purchases[index] as Map;
            if (p['review_flow'] == 'admin' && p['order_id'] != null) {
              final status = switch ('${p['payment_state']}') {
                'order_placed' => 'paid',
                'proof_submitted' => 'pending_verification',
                final state => state,
              };
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${p['seller_name']}',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text(formatPeso(num.parse('${p['amount_due']}'))),
                      Text(paymentStatusLabel(status)),
                      const SizedBox(height: 12),
                      FilledButton.tonal(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => GcashPaymentScreen(
                              orderIds: ['${p['order_id']}'],
                              service: WebsitePaymentService(),
                            ),
                          ),
                        ),
                        child: const Text('View payment / submit receipt'),
                      ),
                    ],
                  ),
                ),
              );
            }
            final instructions = p['instructions'];
            final canPlace = (p['placement'] as Map?)?['eligible'] == true;
            final canUpload = p['can_upload_proof'] == true;
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${p['seller_name'] ?? 'Seller'}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    SelectableText('Purchase: ${p['id']}'),
                    Text(
                      formatPeso(num.parse('${p['amount_due']}')),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    Text(
                      titleCase('${p['payment_state']}'.replaceAll('_', ' ')),
                    ),
                    if (p['expires_at'] != null)
                      Text('Reservation expires: ${p['expires_at']}'),
                    const SizedBox(height: 8),
                    if (canPlace)
                      const Text(
                        'Payment verified. Continue with this purchase; you do not need to start checkout again.',
                      ),
                    if (p['refund_notice'] != null)
                      Text('${p['refund_notice']}'),
                    if (instructions is Map)
                      ...instructions.entries.map(
                        (entry) => SelectableText(
                          '${titleCase('${entry.key}'.replaceAll('_', ' '))}: ${entry.value}',
                        ),
                      )
                    else
                      const Text(
                        'Payment details will appear here when the seller provides them. Messaging is optional; do not pay before receiving instructions.',
                      ),
                    if (canUpload)
                      TextButton.icon(
                        onPressed: _busy ? null : () => _proof(p),
                        icon: const Icon(Icons.upload),
                        label: const Text('Send payment receipt'),
                      ),
                    TextButton.icon(
                      onPressed: _busy ? null : () => _chat(p),
                      icon: const Icon(Icons.chat_outlined),
                      label: const Text('Message seller'),
                    ),
                    if (canPlace)
                      FilledButton(
                        onPressed: _busy ? null : () => _place(p),
                        child: const Text('Continue · Place order'),
                      ),
                    if (p['can_report_paid'] == true)
                      TextButton(
                        onPressed: _busy ? null : () => _reportPaid(p),
                        child: const Text(
                          'I sent payment · report for reconciliation',
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        );
      },
    ),
  );
}
