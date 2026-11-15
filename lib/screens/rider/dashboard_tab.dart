import 'package:flutter/material.dart';

import '../../widgets/rider_dashboard_header.dart';
import '../../widgets/adaptive_card_grid.dart';
import '../../widgets/rider_availability.dart';
import 'pickup_tasks_screen.dart';
import 'assigned_deliveries_screen.dart';
import '../buyer/notifications_screen.dart';

import '../../services/delivery_service.dart';
import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../models/rider_profile.dart';
import '../../models/shipment.dart';
import '../../services/conversation_service.dart';
import '../../services/shipment_service.dart';
import '../../services/rider_service.dart';
import '../../services/backend_service.dart';
import '../../services/rider_web_module_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/rider_stat_tile.dart';
import '../../widgets/skeleton.dart';
import '../../widgets/task_card.dart';
import 'messages_tab.dart';
import 'task_detail_screen.dart';

class RiderDashboardTab extends StatefulWidget {
  final AppUser user;
  final VoidCallback onOpenTasks;
  final VoidCallback onOpenEarnings;

  const RiderDashboardTab({
    super.key,
    required this.user,
    required this.onOpenTasks,
    required this.onOpenEarnings,
  });

  @override
  State<RiderDashboardTab> createState() => _RiderDashboardTabState();
}

class _RiderDashboardTabState extends State<RiderDashboardTab>
    with WidgetsBindingObserver, DeliveryRefresh<RiderDashboardTab> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  final _shipmentService = ShipmentService();
  late final ConversationService _conversationService =
      BackendConversationService(myUserId: widget.user.id);
  late Future<_DashboardData> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
    // Availability is explicitly controlled by the rider and read from the server.
  }

  Future<_DashboardData> _load() async {
    final results = await Future.wait([
      _shipmentService.fetchAvailable(),
      _shipmentService.fetchMyActive(),
      _shipmentService.fetchMyHistory(),
      _shipmentService.fetchMyRiderProfile(),
      _conversationService.fetchConversations(widget.user.id.toString()),
      RiderService.earnings(),
      BackendService.client.request('pickup/list', {}),
      RiderWebModuleService().call('overview'),
    ]);
    final unread = (results[4] as List<ConversationSummary>).fold<int>(
      0,
      (sum, c) => sum + c.unreadCount,
    );
    return _DashboardData(
      available: results[0] as List<Shipment>,
      active: results[1] as List<Shipment>,
      completed: results[2] as List<Shipment>,
      riderProfile: results[3] as RiderProfile?,
      unreadMessages: unread,
      pickups: List<Map<String, dynamic>>.from(
        ((results[6] as Map)['mine'] as List).map(
          (r) => Map<String, dynamic>.from(r as Map),
        ),
      ),
      todayCredited: ((results[7] as Map)['jobs'] as List).fold<double>(0, (
        sum,
        job,
      ) {
        final date = DateTime.tryParse('${job['created_at']}')?.toLocal();
        final today = DateTime.now();
        return sum +
            (date != null &&
                    date.year == today.year &&
                    date.month == today.month &&
                    date.day == today.day
                ? double.tryParse('${job['amount']}') ?? 0
                : 0);
      }),
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _future = _load();
    });
    await _future;
  }

  void _openTask(Shipment shipment) {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) =>
                TaskDetailScreen(shipment: shipment, riderId: widget.user.id),
          ),
        )
        .then((_) => _refresh());
  }

  void _openMessages() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            RiderMessagesTab(user: widget.user, service: _conversationService),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              _buildTopBar(),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildAvailability()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildHero()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildQuickActions()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildBody()),
              ),
              const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildAvailability() => Container(
    padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(AppRadius.card),
      border: Border.all(color: AppColors.border),
    ),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final status = ValueListenableBuilder<bool>(
          valueListenable: RiderService.isAvailable,
          builder: (_, available, _) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Availability', style: AppTypography.caption),
              const SizedBox(height: 4),
              Text(
                available
                    ? 'Accepting assignments'
                    : 'Not accepting assignments',
                style: AppTypography.label,
              ),
            ],
          ),
        );
        if (constraints.maxWidth < 290 ||
            MediaQuery.textScalerOf(context).scale(14) > 18) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              status,
              const SizedBox(height: 8),
              const Align(
                alignment: Alignment.centerRight,
                child: RiderAvailability(),
              ),
            ],
          );
        }
        return Row(
          children: [
            Expanded(child: status),
            const SizedBox(width: 12),
            const RiderAvailability(),
          ],
        );
      },
    ),
  );

  /// A slim top bar matching the rest of the app's header (storefront icon
  /// + "PocketFinds" title, e.g. Buyer Home's) — the messages bell lives
  /// here now instead of inside the gradient greeting card below.
  Widget _buildTopBar() {
    return FutureBuilder<_DashboardData>(
      future: _future,
      builder: (context, snapshot) => RiderDashboardHeader(
        unreadCount: snapshot.data?.unreadMessages ?? 0,
        onMessages: _openMessages,
        onNotifications: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
        ),
        onAssigned: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const AssignedDeliveriesScreen()),
        ),
        onPickup: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const PickupTasksScreen()),
        ),
      ),
    );
  }

  Widget _buildHero() {
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : (hour < 18 ? 'Good afternoon' : 'Good evening');

    return FutureBuilder<_DashboardData>(
      future: _future,
      builder: (context, snapshot) {
        final activeCount = snapshot.data?.active.length ?? 0;

        return ClipRRect(
          borderRadius: BorderRadius.circular(AppRadius.xl),
          child: Container(
            padding: const EdgeInsets.all(AppSpacing.lg),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  Color(0xFF4A1836),
                  Color(0xFFA52B6C),
                  Color(0xFFEF7EB5),
                ],
                stops: [0, 0.55, 1],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Stack(
              children: [
                Positioned(
                  right: -50,
                  top: -50,
                  child: Container(
                    width: 150,
                    height: 150,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.10),
                    ),
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.white.withValues(alpha: 0.16),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.4),
                            ),
                          ),
                          child: Text(
                            widget.user.givenNames.isNotEmpty
                                ? widget.user.givenNames[0].toUpperCase()
                                : '?',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 18,
                            ),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                greeting,
                                style: AppTypography.body.copyWith(
                                  color: Colors.white70,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                widget.user.givenNames,
                                style: AppTypography.sectionTitle.copyWith(
                                  color: Colors.white,
                                  fontSize: 20,
                                ),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                activeCount > 0
                                    ? 'You have $activeCount active task${activeCount == 1 ? '' : 's'}'
                                    : 'Your next assignment will appear here.',
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 13,
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildQuickActions() {
    return AdaptiveCardGrid(
      maxColumns: 2,
      minimumWidth: 128,
      children: [
        _QuickActionChip(
          icon: Icons.assignment_outlined,
          label: 'Browse Tasks',
          onTap: widget.onOpenTasks,
        ),
        _QuickActionChip(
          icon: Icons.payments_outlined,
          label: 'Earnings',
          onTap: widget.onOpenEarnings,
        ),
      ],
    );
  }

  Widget _buildBody() {
    return FutureBuilder<_DashboardData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 84, child: Skeleton()),
              const SizedBox(height: 10),
              Row(
                children: List.generate(3, (index) {
                  return Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                        left: index == 0 ? 0 : 6,
                        right: index == 2 ? 0 : 6,
                      ),
                      child: const SizedBox(height: 88, child: Skeleton()),
                    ),
                  );
                }),
              ),
              const SizedBox(height: AppSpacing.xl),
              const SkeletonShipmentCard(),
            ],
          );
        }
        if (snapshot.hasError) {
          return ErrorState(
            message: 'Could not load dashboard.',
            error: friendlyError(snapshot.error!),
          );
        }
        final data = snapshot.data!;
        final deliveredToday = data.completed.where((s) {
          if (s.shippingStatus != 'delivered' &&
              s.shippingStatus != 'completed') {
            return false;
          }
          final at = s.deliveredAt;
          if (at == null) return false;
          final now = DateTime.now();
          return at.year == now.year &&
              at.month == now.month &&
              at.day == now.day;
        }).toList();
        final earningsToday = data.todayCredited;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Your work',
              style: AppTypography.sectionTitle.copyWith(fontSize: 16),
            ),
            const SizedBox(height: 12),
            AdaptiveCardGrid(
              maxColumns: 2,
              minimumWidth: 128,
              children: [
                _workGroup(
                  'Seller pickups',
                  data.pickups
                      .where(
                        (p) =>
                            p['status'] == 'ready_for_pickup' &&
                            p['rider_confirmed_at'] == null,
                      )
                      .length,
                  Icons.storefront_outlined,
                  () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const PickupTasksScreen(),
                    ),
                  ),
                ),
                _workGroup(
                  'Hub handoffs',
                  data.pickups
                      .where(
                        (p) =>
                            p['status'] == 'picked_up' ||
                            (p['status'] == 'ready_for_pickup' &&
                                p['rider_confirmed_at'] != null),
                      )
                      .length,
                  Icons.warehouse_outlined,
                  () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const PickupTasksScreen(),
                    ),
                  ),
                ),
                _workGroup(
                  'Active deliveries',
                  data.active
                      .where(
                        (s) => ![
                          'delivery_failed',
                          'return_in_transit',
                        ].contains(s.shippingStatus),
                      )
                      .length,
                  Icons.local_shipping_outlined,
                  widget.onOpenTasks,
                ),
                _workGroup(
                  'Needs attention',
                  data.active
                      .where(
                        (s) => [
                          'delivery_failed',
                          'return_in_transit',
                        ].contains(s.shippingStatus),
                      )
                      .length,
                  Icons.info_outline,
                  widget.onOpenTasks,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'Going offline pauses new assignments. Your current jobs remain available.',
              style: AppTypography.caption,
            ),
            const SizedBox(height: 24),
            Text(
              'Current Task',
              style: AppTypography.sectionTitle.copyWith(fontSize: 15),
            ),
            const SizedBox(height: AppSpacing.sm),
            if (data.active.isEmpty)
              _NoActiveTaskCard(onBrowseTasks: widget.onOpenTasks)
            else
              TaskCard(
                shipment: data.active.first,
                onTap: () => _openTask(data.active.first),
              ),
            const SizedBox(height: AppSpacing.xl),
            Text(
              'Overview',
              style: AppTypography.sectionTitle.copyWith(fontSize: 16),
            ),
            const SizedBox(height: 12),
            _EarningsHighlightCard(
              amount: earningsToday,
              deliveredCount: deliveredToday.length,
            ),
            const SizedBox(height: 12),
            AdaptiveCardGrid(
              children: [
                RiderStatTile(
                  label: 'Delivered',
                  value: '${deliveredToday.length}',
                  icon: Icons.local_shipping_outlined,
                  color: AppColors.pinkDark,
                  background: AppColors.pinkSoft,
                ),
                RiderStatTile(
                  label: 'Completed',
                  value:
                      '${data.completed.where((s) => s.shippingStatus == 'delivered' || s.shippingStatus == 'completed').length}',
                  icon: Icons.check_circle_outline,
                  color: AppColors.success,
                  background: AppColors.successSoft,
                ),
                RiderStatTile(
                  label: 'Pending',
                  value: '${data.active.length}',
                  icon: Icons.pending_actions_outlined,
                  color: AppColors.warning,
                  background: AppColors.warningSoft,
                ),
              ],
            ),
            if (data.active.length > 1) ...[
              const SizedBox(height: AppSpacing.xl),
              Text(
                "Today's Route",
                style: AppTypography.sectionTitle.copyWith(fontSize: 15),
              ),
              const SizedBox(height: AppSpacing.sm),
              ...data.active
                  .skip(1)
                  .map(
                    (shipment) => Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: TaskCard(
                        shipment: shipment,
                        onTap: () => _openTask(shipment),
                      ),
                    ),
                  ),
            ],
          ],
        );
      },
    );
  }

  Widget _workGroup(
    String label,
    int count,
    IconData icon,
    VoidCallback onTap,
  ) => OutlinedButton(
    onPressed: onTap,
    style: OutlinedButton.styleFrom(
      backgroundColor: AppColors.white,
      foregroundColor: AppColors.textPrimary,
      padding: const EdgeInsets.all(16),
      side: BorderSide(color: AppColors.border),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.card),
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 22, color: AppColors.pinkDark),
            const Spacer(),
            Text('$count', style: AppTypography.sectionTitle),
          ],
        ),
        const SizedBox(height: 12),
        Text(label, style: AppTypography.label),
      ],
    ),
  );
}

/// The real, driving number on the dashboard — today's earnings from
/// delivered shipments' real `delivery_fee`s, given visual priority over
/// the three supporting stats instead of sitting flat among four
/// identical boxes.
class _EarningsHighlightCard extends StatelessWidget {
  final double amount;
  final int deliveredCount;

  const _EarningsHighlightCard({
    required this.amount,
    required this.deliveredCount,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.pinkBorder),
        boxShadow: AppShadows.sm,
        gradient: LinearGradient(
          colors: [AppColors.pinkSoft, AppColors.white],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: AppColors.pinkGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: Icon(
              Icons.payments_outlined,
              color: AppColors.white,
              size: 26,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Credited today', style: AppTypography.caption),
                const SizedBox(height: 2),
                Text(
                  formatPeso(amount),
                  style: AppTypography.price.copyWith(fontSize: 24),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  '$deliveredCount delivered today',
                  style: AppTypography.caption,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NoActiveTaskCard extends StatelessWidget {
  final VoidCallback onBrowseTasks;

  const _NoActiveTaskCard({required this.onBrowseTasks});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        vertical: 20,
        horizontal: AppSpacing.lg,
      ),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.pinkSoft,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.local_shipping_outlined,
              size: 26,
              color: AppColors.pink,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'No active task right now',
            style: AppTypography.productTitle,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 4),
          Text(
            'Check Tasks for available pickups and deliveries near you.',
            style: AppTypography.caption,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          SecondaryButton(
            label: 'Browse Tasks',
            onPressed: onBrowseTasks,
            fullWidth: false,
          ),
        ],
      ),
    );
  }
}

class _QuickActionChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _QuickActionChip({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 20),
      label: Text(label, textAlign: TextAlign.center),
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.pinkDark,
        backgroundColor: AppColors.white,
        minimumSize: const Size(48, 52),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        side: BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        textStyle: AppTypography.label,
      ),
    );
  }
}

class _DashboardData {
  final List<Map<String, dynamic>> pickups;
  final List<Shipment> available;
  final List<Shipment> active;
  final List<Shipment> completed;
  final RiderProfile? riderProfile;
  final int unreadMessages;
  final double todayCredited;

  const _DashboardData({
    required this.pickups,
    required this.available,
    required this.active,
    required this.completed,
    required this.riderProfile,
    required this.unreadMessages,
    required this.todayCredited,
  });
}
