import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../utils/friendly_error.dart';
import '../../utils/status_style.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/pressable.dart';
import 'delivery_detail_screen.dart';

/// Mobile translation of the rider-relevant slice of
/// `LogisticsController::dashboard()` — counts + current delivery, scoped
/// to what THIS rider is authorized to see (not the staff dispatcher view
/// of every shipment in the system).
class RiderDashboardTab extends StatefulWidget {
  final AppUser user;

  const RiderDashboardTab({super.key, required this.user});

  @override
  State<RiderDashboardTab> createState() => _RiderDashboardTabState();
}

class _RiderDashboardTabState extends State<RiderDashboardTab> {
  final _shipmentService = ShipmentService();
  late Future<_DashboardData> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_DashboardData> _load() async {
    final results = await Future.wait([
      _shipmentService.fetchAvailable(),
      _shipmentService.fetchMyActive(),
      _shipmentService.fetchMyHistory(),
    ]);
    return _DashboardData(
      available: results[0],
      active: results[1],
      completed: results[2],
    );
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              pinned: true,
              floating: true,
              backgroundColor: AppColors.white,
              title: const Text(
                'Rider Dashboard',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              sliver: SliverToBoxAdapter(child: _buildWelcomeBanner()),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
              sliver: SliverToBoxAdapter(child: _buildBody()),
            ),
            const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
          ],
        ),
      ),
    );
  }

  Widget _buildWelcomeBanner() {
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : hour < 18
            ? 'Good afternoon'
            : 'Good evening';
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.xl),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.xl),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF4A1836), Color(0xFFA52B6C), Color(0xFFEF7EB5)],
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
                width: 140,
                height: 140,
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
                  children: [
                    const Icon(Icons.two_wheeler, color: Colors.white, size: 18),
                    const SizedBox(width: 6),
                    Text(
                      '$greeting, ${widget.user.givenNames} 👋',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.3,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                const Text(
                  'Ready to make deliveries today?',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    return FutureBuilder<_DashboardData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 60),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (snapshot.hasError) {
          return ErrorState(
            message: 'Could not load dashboard.',
            error: friendlyError(snapshot.error!),
          );
        }
        final data = snapshot.data!;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: _statTile(
                    'Available',
                    data.available.length,
                    Icons.inbox_outlined,
                    const Color(0xFFB45309),
                    const Color(0xFFFEF3C7),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _statTile(
                    'Active',
                    data.active.length,
                    Icons.local_shipping_outlined,
                    const Color(0xFF1D4ED8),
                    const Color(0xFFDBEAFE),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _statTile(
                    'Completed',
                    data.completed.length,
                    Icons.check_circle_outline,
                    AppColors.success,
                    const Color(0xFFDCFCE7),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.xl),
            const Text('Current Delivery', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            const SizedBox(height: AppSpacing.sm),
            if (data.active.isEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(AppRadius.card),
                  border: Border.all(color: AppColors.border),
                ),
                child: const Text(
                  'No active delivery right now. Check the Deliveries tab for available orders.',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 13),
                ),
              )
            else
              ...data.active.map((shipment) => Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: _activeDeliveryCard(shipment),
                  )),
          ],
        );
      },
    );
  }

  Widget _activeDeliveryCard(Shipment shipment) {
    final style = StatusStyle.forOrderStatus(shipment.shippingStatus);
    return Pressable(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => DeliveryDetailScreen(shipment: shipment)),
      ),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: style.background,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(Icons.local_shipping_outlined, color: style.color, size: 22),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(shipment.orderNumber, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                  const SizedBox(height: 2),
                  Text(
                    shipment.statusLabel,
                    style: TextStyle(color: style.color, fontWeight: FontWeight.w600, fontSize: 12),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }

  Widget _statTile(String label, int value, IconData icon, Color color, Color soft) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg, horizontal: AppSpacing.sm),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(color: soft, shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 8),
          Text('$value', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
          Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textMuted)),
        ],
      ),
    );
  }
}

class _DashboardData {
  final List<Shipment> available;
  final List<Shipment> active;
  final List<Shipment> completed;

  const _DashboardData({
    required this.available,
    required this.active,
    required this.completed,
  });
}
