import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/backend_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';
import 'parcel_scanner_screen.dart';
import '../../models/chat_ui.dart';
import '../../services/conversation_service.dart';
import '../buyer/conversation_detail_screen.dart';

/// Native seller pickup assignments, kept separate from final delivery tasks.
class PickupTasksScreen extends StatefulWidget {
  const PickupTasksScreen({super.key});
  @override
  State<PickupTasksScreen> createState() => _PickupTasksScreenState();
}

class _PickupTasksScreenState extends State<PickupTasksScreen>
    with WidgetsBindingObserver, DeliveryRefresh<PickupTasksScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  final _requests = <String, String>{};
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (!_busy) await _load();
  }

  Future<void> _load() async {
    try {
      final result = await BackendService.client.request('pickup/list', {});
      if (mounted) {
        setState(() {
          _data = Map<String, dynamic>.from(result as Map);
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  Future<void> _act(
    Map<String, dynamic> task,
    String action, [
    String? code,
  ]) async {
    if (_busy) return;
    final key = jsonEncode([task['id'], action, code]);
    final request = _requests.putIfAbsent(key, DeliveryService.requestId);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await BackendService.client.request('pickup/$action', {
        'shipment_id': task['id'],
        'request_id': request,
        'parcel_code': ?code,
      });
      _requests.remove(key);
      DeliveryService.updates.add(null);
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _manual(Map<String, dynamic> task) async {
    final controller = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Verify seller parcel'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Tracking ID on parcel'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.pop(dialogContext, controller.text.trim()),
            child: const Text('Verify'),
          ),
        ],
      ),
    );
    // Dialog exit animations may still use the controller until the next frame.
    if (mounted && code != null && code.isNotEmpty) await _confirm(task, code);
  }

  Future<void> _confirm(Map<String, dynamic> task, String code) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Have you received this parcel?'),
        content: const Text(
          'Confirm only after physically receiving the seller’s parcel. The seller must separately confirm handover.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm receipt'),
          ),
        ],
      ),
    );
    if (accepted == true && mounted) await _act(task, 'confirm', code);
  }

  Future<void> _open(Uri uri) async {
    try {
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw StateError('Unable to open application.');
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  void _messageSeller(Map<String, dynamic> task) {
    final current = BackendService.client.auth.currentUser;
    if (current == null || task['seller_id'] == null) return;
    final name = '${task['seller_name']}';
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: '${task['seller_id']}',
            participantName: name,
            participantAvatarInitial: name.isEmpty ? '?' : name[0],
            lastMessagePreview: '',
            lastMessageAt: DateTime.now(),
          ),
          service: BackendConversationService(myUserId: int.parse(current.id)),
        ),
      ),
    );
  }

  Widget _task(Map<String, dynamic> task, bool mine) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Order #${task['order_number']}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          Text('${task['seller_name']}'),

          Text('${task['seller_address']}'),
          Text('Sorting center: ${task['center']}'),
          Text('Pickup: ${task['pickup_stage'] ?? task['status']}'),
          if (mine) ...[
            Text(
              'Tracking: ${task['tracking_number'] ?? 'Waiting for shipping label'}',
            ),
            Wrap(
              children: [
                TextButton(
                  onPressed: () => _open(
                    Uri.parse(
                      'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent('${task['seller_address']}')}',
                    ),
                  ),
                  child: const Text('Navigate to seller'),
                ),
                if (task['seller_contact'] != null)
                  TextButton(
                    onPressed: () => _open(
                      Uri(scheme: 'tel', path: '${task['seller_contact']}'),
                    ),
                    child: const Text('Call seller'),
                  ),
                TextButton(
                  onPressed: () => _messageSeller(task),
                  child: const Text('Message seller'),
                ),
              ],
            ),
            if (task['can_arrive'] == true)
              FilledButton.icon(
                onPressed: _busy ? null : () => _act(task, 'arrive'),
                icon: const Icon(Icons.location_on_outlined),
                label: const Text('I have arrived at the seller'),
              )
            else if (task['can_confirm'] == true) ...[
              FilledButton(
                onPressed: _busy
                    ? null
                    : () async {
                        final code = await Navigator.of(context).push<String>(
                          MaterialPageRoute(
                            builder: (_) => const ParcelScannerScreen(),
                          ),
                        );
                        if (mounted && code != null) await _confirm(task, code);
                      },
                child: const Text('Scan and confirm seller pickup'),
              ),
              TextButton(
                onPressed: _busy ? null : () => _manual(task),
                child: const Text('Enter tracking ID instead'),
              ),
            ] else if (task['status'] == 'ready_for_pickup')
              const Text(
                'Receipt recorded. Waiting for the seller to confirm handover.',
              )
            else if (task['status'] == 'picked_up')
              const Text(
                'Bring this parcel to the sorting center. Center staff must scan and confirm arrival.',
              )
            else
              const Text(
                'Pickup history: the parcel has moved to the next stage.',
              ),
          ] else
            FilledButton(
              onPressed: _busy ? null : () => _act(task, 'accept'),
              child: const Text('Accept seller pickup'),
            ),

        ],
      ),
    ),
  );
  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Seller Pickups'),
        bottom: const TabBar(
          tabs: [
            Tab(text: 'Pickup Requests'),
            Tab(text: 'My Pickups'),
          ],
        ),
      ),
      body: Column(
        children: [
          if (_busy) const LinearProgressIndicator(),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          if (_data == null && _error == null) const LinearProgressIndicator(),
          Expanded(
            child: TabBarView(
              children: [
                for (final mine in [false, true])
                  RefreshIndicator(
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        for (final row
                            in (_data?[mine ? 'mine' : 'available'] as List? ??
                                []))
                          _task(Map<String, dynamic>.from(row as Map), mine),
                        if ((_data?[mine ? 'mine' : 'available'] as List? ?? [])
                            .isEmpty)
                          Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(
                              mine ? 'No pickup assignments yet.' : 'No eligible seller pickups right now. Pull to refresh.',
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
