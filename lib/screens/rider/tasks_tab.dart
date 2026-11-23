import 'package:flutter/material.dart';

import '../../services/delivery_service.dart';
import '../../services/rider_service.dart';
import '../../models/app_user.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/segmented_control.dart';
import '../../widgets/skeleton.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/staggered_fade_in.dart';
import '../../widgets/task_card.dart';
import 'task_detail_screen.dart';
import 'pickup_tasks_screen.dart';
import 'hub_transfers_screen.dart';

const _tabLabels = ['Delivery Requests', 'Deliveries'];

/// "My Tasks" — Available (unclaimed) and My Tasks (already assigned),
/// built on the real `shipments` table via `ShipmentService`, same backend
/// verified two redesigns ago.
class TasksTab extends StatefulWidget {
  final AppUser user;

  const TasksTab({super.key, required this.user});

  @override
  State<TasksTab> createState() => _TasksTabState();
}

class _TasksTabState extends State<TasksTab>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _shipmentService = ShipmentService();
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      if (_tabController.index != _currentIndex) {
        setState(() => _currentIndex = _tabController.index);
      }
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('My Tasks', style: AppTypography.pageTitle)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: OutlinedButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PickupTasksScreen()),
              ),
              icon: const Icon(Icons.inventory_2_outlined),
              label: const Text('Pickup Requests / My Pickups'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: OutlinedButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const HubTransfersScreen()),
              ),
              icon: const Icon(Icons.hub_outlined),
              label: const Text('Hub Transfers'),
            ),
          ),
          SegmentedControl<int>(
            value: _currentIndex,
            onChanged: (index) => _tabController.animateTo(index),
            options: List.generate(
              _tabLabels.length,
              (i) => SegmentedControlOption(i, _tabLabels[i]),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _AvailableList(
                  shipmentService: _shipmentService,
                  riderId: widget.user.id,
                ),
                _MyTasksList(
                  shipmentService: _shipmentService,
                  riderId: widget.user.id,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AvailableList extends StatefulWidget {
  final ShipmentService shipmentService;
  final int riderId;

  const _AvailableList({required this.shipmentService, required this.riderId});

  @override
  State<_AvailableList> createState() => _AvailableListState();
}

class _AvailableListState extends State<_AvailableList>
    with WidgetsBindingObserver, DeliveryRefresh<_AvailableList> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  late Future<List<Shipment>> _future;
  final Set<String> _declined = {};
  String? _acceptingId;

  @override
  void initState() {
    super.initState();
    _future = widget.shipmentService.fetchAvailable();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = widget.shipmentService.fetchAvailable();
      _declined.clear();
    });
    await _future;
  }

  Future<void> _open(Shipment shipment) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            TaskDetailScreen(shipment: shipment, riderId: widget.riderId),
      ),
    );
    if (result == true) _refresh();
  }

  Future<void> _accept(Shipment shipment) async {
    setState(() => _acceptingId = shipment.id);
    try {
      await widget.shipmentService.claim(shipment.id);
      DeliveryService.updates.add(null);
      if (!mounted) return;
      AppSnackBar.success(context, 'Task accepted!');
      _refresh();
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not accept task: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _acceptingId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
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
                  message: 'Could not load tasks.',
                  error: friendlyError(snapshot.error!),
                ),
              ],
            );
          }
          final shipments = (snapshot.data ?? [])
              .where((s) => !_declined.contains(s.id))
              .toList();
          if (shipments.isEmpty) {
            return ListView(
              children: const [
                EmptyState(
                  icon: Icons.assignment_outlined,
                  title: 'No available tasks right now',
                ),
              ],
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: shipments.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final shipment = shipments[index];
              final accepting = _acceptingId == shipment.id;
              return StaggeredFadeIn(
                index: index,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TaskCard(shipment: shipment, onTap: () => _open(shipment)),
                    const SizedBox(height: AppSpacing.sm),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: accepting
                                ? null
                                : () async {
                                    try {
                                      await RiderService.decline(shipment.id);
                                      if (mounted) await _refresh();
                                    } catch (e) {
                                      if (context.mounted) {
                                        AppSnackBar.error(
                                          context,
                                          friendlyError(e),
                                        );
                                      }
                                    }
                                  },
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.textMuted,
                              side: BorderSide(color: AppColors.border),
                            ),
                            child: const Text('Decline'),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          flex: 2,
                          child: PrimaryButton(
                            label: 'Accept',
                            loading: accepting,
                            fullWidth: false,
                            onPressed: () => _accept(shipment),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _MyTasksList extends StatefulWidget {
  final ShipmentService shipmentService;
  final int riderId;

  const _MyTasksList({required this.shipmentService, required this.riderId});

  @override
  State<_MyTasksList> createState() => _MyTasksListState();
}

class _MyTasksListState extends State<_MyTasksList>
    with WidgetsBindingObserver, DeliveryRefresh<_MyTasksList> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  late Future<List<Shipment>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.shipmentService.fetchMyActive();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = widget.shipmentService.fetchMyActive();
    });
    await _future;
  }

  Future<void> _open(Shipment shipment) async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            TaskDetailScreen(shipment: shipment, riderId: widget.riderId),
      ),
    );
    _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
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
                  message: 'Could not load tasks.',
                  error: friendlyError(snapshot.error!),
                ),
              ],
            );
          }
          final shipments = snapshot.data ?? [];
          if (shipments.isEmpty) {
            return ListView(
              children: const [
                EmptyState(
                  icon: Icons.local_shipping_outlined,
                  title: 'No active tasks',
                  subtitle:
                      'Accept an offer from Delivery Requests to get started.',
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
                onTap: () => _open(shipments[index]),
              ),
            ),
          );
        },
      ),
    );
  }
}
