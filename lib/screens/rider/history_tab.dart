import 'package:flutter/material.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../utils/status_style.dart';
import '../../widgets/empty_state.dart';
import 'delivery_detail_screen.dart';

/// Completed delivery history — mirrors `LogisticsController::history()`'s
/// delivered+completed grouping, scoped to this rider only.
class HistoryTab extends StatefulWidget {
  const HistoryTab({super.key});

  @override
  State<HistoryTab> createState() => _HistoryTabState();
}

class _HistoryTabState extends State<HistoryTab> {
  final _shipmentService = ShipmentService();
  late Future<List<Shipment>> _future;

  @override
  void initState() {
    super.initState();
    _future = _shipmentService.fetchMyHistory();
  }

  Future<void> _refresh() async {
    setState(() => _future = _shipmentService.fetchMyHistory());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery History')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Shipment>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(message: 'Could not load delivery history.', error: friendlyError(snapshot.error!)),
                ],
              );
            }
            final shipments = snapshot.data ?? [];
            if (shipments.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(icon: Icons.check_circle_outline, title: 'No completed deliveries yet'),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: shipments.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final shipment = shipments[index];
                final style = StatusStyle.forOrderStatus(shipment.shippingStatus);
                return Card(
                  child: ListTile(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => DeliveryDetailScreen(shipment: shipment)),
                    ),
                    leading: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(color: style.background, shape: BoxShape.circle),
                      child: Icon(Icons.check_circle_outline, color: style.color, size: 20),
                    ),
                    title: Text(shipment.orderNumber, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(
                      shipment.deliverTo.isEmpty ? 'Address not provided' : shipment.deliverTo,
                      style: const TextStyle(fontSize: 12),
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(formatPeso(shipment.total), style: const TextStyle(fontWeight: FontWeight.w800)),
                        Text(shipment.statusLabel, style: TextStyle(fontSize: 11, color: style.color, fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
