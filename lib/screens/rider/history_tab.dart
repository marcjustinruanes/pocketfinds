import 'package:flutter/material.dart';

import 'rider_job_history_screen.dart';

import '../../services/delivery_service.dart';
import '../../models/app_user.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/segmented_control.dart';
import '../../widgets/skeleton.dart';
import '../../widgets/staggered_fade_in.dart';
import '../../widgets/task_card.dart';
import 'task_detail_screen.dart';

const _filterLabels = ['All', 'Delivered', 'Returns', 'Attempts'];

/// Includes earlier failed attempts even after a retry succeeds or resumes.
class RiderHistoryTab extends StatefulWidget {
  final AppUser user;

  const RiderHistoryTab({super.key, required this.user});

  @override
  State<RiderHistoryTab> createState() => _RiderHistoryTabState();
}

class _RiderHistoryTabState extends State<RiderHistoryTab>
    with WidgetsBindingObserver, DeliveryRefresh<RiderHistoryTab> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  final _shipmentService = ShipmentService();
  late Future<List<Shipment>> _future;
  int _filterIndex = 0;

  @override
  void initState() {
    super.initState();
    _future = _shipmentService.fetchMyHistory();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = _shipmentService.fetchMyHistory();
    });
    await _future;
  }

  List<Shipment> _applyFilter(List<Shipment> shipments) {
    switch (_filterIndex) {
      case 1:
        return shipments
            .where(
              (s) =>
                  s.shippingStatus == 'delivered' ||
                  s.shippingStatus == 'completed',
            )
            .toList();
      case 2:
        return shipments
            .where(
              (s) =>
                  s.shippingStatus == 'returned' ||
                  s.shippingStatus == 'return_in_transit',
            )
            .toList();
      case 3:
        return shipments
            .where(
              (s) =>
                  s.attemptCount > 0 ||
                  s.shippingStatus == 'delivery_failed' ||
                  s.shippingStatus == 'rescheduled',
            )
            .toList();
      default:
        return shipments;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('History', style: AppTypography.pageTitle),
        actions: [
          IconButton(
            tooltip: 'Pickup and delivery ledger',
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const RiderJobHistoryScreen()),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          SegmentedControl<int>(
            value: _filterIndex,
            onChanged: (index) => setState(() => _filterIndex = index),
            options: List.generate(
              _filterLabels.length,
              (i) => SegmentedControlOption(i, _filterLabels[i]),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _refresh,
              child: FutureBuilder<List<Shipment>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: 4,
                      separatorBuilder: (_, _) => const SizedBox(height: 12),
                      itemBuilder: (_, _) => const SkeletonShipmentCard(),
                    );
                  }
                  if (snapshot.hasError) {
                    return ListView(
                      children: [
                        ErrorState(
                          message: 'Could not load history.',
                          error: friendlyError(snapshot.error!),
                        ),
                      ],
                    );
                  }
                  final shipments = _applyFilter(snapshot.data ?? []);
                  if (shipments.isEmpty) {
                    return ListView(
                      children: [
                        EmptyState(
                          icon: Icons.check_circle_outline,
                          title: _filterIndex == 0
                              ? 'No task history yet'
                              : 'No ${_filterLabels[_filterIndex].toLowerCase()} tasks',
                        ),
                      ],
                    );
                  }
                  return ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: shipments.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) => StaggeredFadeIn(
                      index: index,
                      child: TaskCard(
                        shipment: shipments[index],
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => TaskDetailScreen(
                              shipment: shipments[index],
                              riderId: widget.user.id,
                            ),
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
