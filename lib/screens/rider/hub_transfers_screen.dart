import 'package:flutter/material.dart';

import '../../services/delivery_service.dart';
import '../../services/rider_web_module_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';

class HubTransfersScreen extends StatefulWidget {
  final RiderWebModuleService? service;
  const HubTransfersScreen({super.key, this.service});
  @override
  State<HubTransfersScreen> createState() => _HubTransfersScreenState();
}

class _HubTransfersScreenState extends State<HubTransfersScreen>
    with WidgetsBindingObserver, DeliveryRefresh<HubTransfersScreen> {
  late final RiderWebModuleService _service;
  final _requests = <String, String>{};
  late Future<Map<String, dynamic>> _future;
  String? _busy;
  @override
  void initState() {
    super.initState();
    _service = widget.service ?? RiderWebModuleService();
    _future = _service.transfers();
  }

  @override
  Future<void> refreshDeliveryData() => _refresh();
  Future<void> _refresh() async {
    final next = _service.transfers();
    setState(() {
      _future = next;
    });
    try {
      await next;
    } catch (_) {
      /* Render the retry state below. */
    }
  }

  Future<void> _confirm(Map item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm parcel pickup'),
        content: Text(
          'Have you physically collected ${item['tracking_number']} from ${item['from_hub']}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('I have the parcel'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final id = '${item['id']}';
    setState(() => _busy = id);
    try {
      await _service.confirmTransfer(
        id,
        _requests.putIfAbsent(id, DeliveryService.requestId),
      );
      if (!mounted) return;
      AppSnackBar.success(
        context,
        'Pickup confirmed. The destination hub can now record arrival.',
      );
      await _refresh();
    } catch (error) {
      if (mounted) AppSnackBar.error(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My Hub Transfers')),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(friendlyError(snapshot.error!)),
                TextButton(onPressed: _refresh, child: const Text('Try again')),
              ],
            );
          }
          final rows = snapshot.data?['transfers'] as List? ?? [];
          return ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const Text(
                'Confirm collection from the origin hub before heading to the destination hub.',
              ),
              const SizedBox(height: 16),
              if (rows.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 48),
                  child: Text(
                    'No hub transfers assigned. Transfers assigned by your hub will appear here.',
                    textAlign: TextAlign.center,
                  ),
                ),
              for (final item in rows)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          '${item['tracking_number'] ?? item['id']}',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Text('${item['from_hub']} → ${item['to_hub']}'),
                        Text(
                          'Leg: ${'${item['leg_type']}'.replaceAll('_', ' ')}',
                        ),
                        const SizedBox(height: 12),
                        if (item['batch'] is Map) ...[
                          Text(
                            'Transfer batch: ${item['batch']['parcel_count']} parcels',
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${item['batch']['received_count']} received at destination',
                          ),
                          ExpansionTile(
                            tilePadding: EdgeInsets.zero,
                            title: const Text('View batch manifest'),
                            children: [
                              for (final parcel
                                  in item['batch']['parcels'] as List? ?? [])
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  leading: Icon(
                                    parcel['received_at'] != null
                                        ? Icons.check_circle_outline
                                        : Icons.inventory_2_outlined,
                                  ),
                                  title: Text('${parcel['tracking_number']}'),
                                  subtitle: Text(
                                    parcel['received_at'] != null
                                        ? 'Received by destination hub'
                                        : 'Awaiting destination receipt',
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (item['picked_up_at'] != null)
                          const Text('Picked up · En route to destination hub')
                        else
                          FilledButton(
                            onPressed: _busy != null
                                ? null
                                : () => _confirm(item as Map),
                            child: Text(
                              _busy == item['id']
                                  ? 'Confirming…'
                                  : 'Confirm pickup',
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    ),
  );
}
