import 'package:flutter/material.dart';

import '../../models/shipment.dart';
import '../../services/backend_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/titled_card.dart';
import '../../widgets/app_snackbar.dart';
import 'task_detail_screen.dart';

class AssignedDeliveriesScreen extends StatefulWidget {
  const AssignedDeliveriesScreen({super.key});
  @override
  State<AssignedDeliveriesScreen> createState() =>
      _AssignedDeliveriesScreenState();
}

class _AssignedDeliveriesScreenState extends State<AssignedDeliveriesScreen>
    with WidgetsBindingObserver, DeliveryRefresh<AssignedDeliveriesScreen> {
  late Future<List<dynamic>> _future = _load();
  final _delivery = DeliveryService();
  bool _busy = false;
  Future<List<dynamic>> _load() async => List<dynamic>.from(
    await BackendService.client.request('delivery/assigned-list') as List,
  );
  @override
  Future<void> refreshDeliveryData() async {
    if (_busy) return;
    setState(() {
      _future = _load();
    });
    await _future;
  }

  Future<void> _act(String id, String action) async {
    setState(() => _busy = true);
    try {
      await _delivery.act(id, action);
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        await refreshDeliveryData();
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Assigned deliveries')),
    body: RefreshIndicator(
      onRefresh: refreshDeliveryData,
      child: FutureBuilder<List<dynamic>>(
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
            return const Center(child: CircularProgressIndicator());
          }
          final rows = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              TitledCard(
                title: 'Parcel verification checklist',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Open each accepted parcel to scan or enter its exact barcode before starting delivery. Verification is separate from buyer handover.',
                    ),
                    const SizedBox(height: 12),
                    for (final row in rows.where(
                      (r) =>
                          r['section'] == 'active' &&
                          r['status'] == 'assigned_to_rider',
                    ))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          row['parcel_verified'] == true
                              ? Icons.check_circle_outline
                              : Icons.qr_code_scanner,
                        ),
                        title: Text('Order #${row['order_number']}'),
                        subtitle: Text(
                          row['parcel_verified'] == true
                              ? 'Parcel verified'
                              : 'Verification required - open delivery below',
                        ),
                      ),
                    if (!rows.any(
                      (r) =>
                          r['section'] == 'active' &&
                          r['status'] == 'assigned_to_rider',
                    ))
                      const Text('No parcels waiting for verification.'),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              for (final section in {
                'new': 'New Assignments',
                'active': 'Active Deliveries',
                'completed': 'Completed Deliveries',
                'cancelled': 'Cancelled or Reassigned Deliveries',
              }.entries) ...[
                Text(
                  section.value,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 12),
                if (!rows.any((r) => r['section'] == section.key))
                  const Padding(
                    padding: EdgeInsets.only(bottom: 20),
                    child: Text('No deliveries here.'),
                  ),
                for (final r in rows.where((r) => r['section'] == section.key))
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: TitledCard(
                      title: '${r['order_number']}',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            '${r['seller_name'] ?? 'Store'} • ${r['status'].toString().replaceAll('_', ' ')}',
                          ),
                          if (r['pickup_location'] != null)
                            Text('Pickup: ${r['pickup_location']}'),
                          if (r['buyer_name'] != null)
                            Text('Buyer: ${r['buyer_name']}'),
                          if (r['address'] is Map)
                            Text(
                              'Deliver to: ${(r['address'] as Map).values.where((v) => v != null && '$v'.isNotEmpty).join(', ')}',
                            ),
                          Text('Items: ${r['item_count']}'),
                          if (r['delivery_fee'] != null)
                            Text(
                              'Order delivery fee: ${formatPeso(double.parse('${r['delivery_fee']}'))}',
                            ),
                          const Text(
                            'Your credited earnings and payout status are shown in Earnings.',
                          ),
                          Text(
                            'Assigned: ${r['assigned_at'] ?? 'Not recorded'}',
                          ),
                          if (section.key == 'new')
                            Wrap(
                              spacing: 12,
                              children: [
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                          '${r['id']}',
                                          'assignment-accept',
                                        ),
                                  child: const Text('Accept'),
                                ),
                                TextButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                          '${r['id']}',
                                          'assignment-reject',
                                        ),
                                  child: const Text('Reject'),
                                ),
                              ],
                            ),
                          if (r['shipment'] != null && section.key != 'new')
                            OutlinedButton(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => TaskDetailScreen(
                                    shipment: Shipment.fromRow(
                                      Map<String, dynamic>.from(
                                        r['shipment'] as Map,
                                      ),
                                      Map<String, dynamic>.from(
                                        r['order'] as Map,
                                      ),
                                    ),
                                    riderId: int.parse(
                                      BackendService
                                          .client
                                          .auth
                                          .currentUser!
                                          .id,
                                    ),
                                  ),
                                ),
                              ),
                              child: const Text('View delivery'),
                            ),
                        ],
                      ),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    ),
  );
}
