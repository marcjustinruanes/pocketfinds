import 'package:flutter/material.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../utils/status_style.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/pressable.dart';
import 'delivery_detail_screen.dart';

/// "Available" (unclaimed shipments anyone can accept) and "My Deliveries"
/// (already assigned to this rider, in progress) — the self-service rider
/// flow the website never had (only a staff dispatcher view), built on the
/// same `shipments` table.
class DeliveriesTab extends StatefulWidget {
  const DeliveriesTab({super.key});

  @override
  State<DeliveriesTab> createState() => _DeliveriesTabState();
}

class _DeliveriesTabState extends State<DeliveriesTab>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _shipmentService = ShipmentService();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Deliveries'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [Tab(text: 'Available'), Tab(text: 'My Deliveries')],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _ShipmentListView(
            fetcher: _shipmentService.fetchAvailable,
            emptyIcon: Icons.inbox_outlined,
            emptyTitle: 'No available deliveries right now',
            // Only refresh if something was actually claimed (the detail
            // screen pops `true`) — otherwise a plain back-tap would
            // trigger a pointless refetch.
            refreshOnReturn: false,
          ),
          _ShipmentListView(
            fetcher: _shipmentService.fetchMyActive,
            emptyIcon: Icons.local_shipping_outlined,
            emptyTitle: 'No active deliveries',
            emptySubtitle: 'Accept a delivery from the Available tab to get started.',
            // Status updates (picked_up/out_for_delivery) don't pop the
            // detail screen — only "delivered" does — so refresh
            // unconditionally on return to pick up any in-place change.
            refreshOnReturn: true,
          ),
        ],
      ),
    );
  }
}

/// One tab's list of shipments — shared by "Available" and "My Deliveries",
/// which previously had two near-identical copies of this same
/// fetch/loading/empty/error/list structure.
class _ShipmentListView extends StatefulWidget {
  final Future<List<Shipment>> Function() fetcher;
  final IconData emptyIcon;
  final String emptyTitle;
  final String? emptySubtitle;
  final bool refreshOnReturn;

  const _ShipmentListView({
    required this.fetcher,
    required this.emptyIcon,
    required this.emptyTitle,
    this.emptySubtitle,
    this.refreshOnReturn = false,
  });

  @override
  State<_ShipmentListView> createState() => _ShipmentListViewState();
}

class _ShipmentListViewState extends State<_ShipmentListView> {
  late Future<List<Shipment>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.fetcher();
  }

  Future<void> _refresh() async {
    setState(() => _future = widget.fetcher());
    await _future;
  }

  Future<void> _open(Shipment shipment) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => DeliveryDetailScreen(shipment: shipment)),
    );
    if (widget.refreshOnReturn || result == true) _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
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
                ErrorState(message: 'Could not load deliveries.', error: friendlyError(snapshot.error!)),
              ],
            );
          }
          final shipments = snapshot.data ?? [];
          if (shipments.isEmpty) {
            return ListView(
              children: [
                EmptyState(icon: widget.emptyIcon, title: widget.emptyTitle, subtitle: widget.emptySubtitle),
              ],
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: shipments.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) => _ShipmentCard(
              shipment: shipments[index],
              onTap: () => _open(shipments[index]),
            ),
          );
        },
      ),
    );
  }
}

class _ShipmentCard extends StatelessWidget {
  final Shipment shipment;
  final VoidCallback onTap;

  const _ShipmentCard({required this.shipment, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.forOrderStatus(shipment.shippingStatus);
    return Pressable(
      onTap: onTap,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(shipment.orderNumber, style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: style.background,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      shipment.statusLabel,
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: style.color),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(Icons.location_on_outlined, size: 14, color: AppColors.textMuted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      shipment.deliverTo.isEmpty ? 'Address not provided' : shipment.deliverTo,
                      style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('${shipment.items.length} item(s)', style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  Text(formatPeso(shipment.total), style: const TextStyle(fontWeight: FontWeight.w800)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
