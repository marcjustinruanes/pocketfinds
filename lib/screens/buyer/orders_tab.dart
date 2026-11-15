import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/buyer_order.dart';
import '../../services/order_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../utils/status_style.dart';
import '../../widgets/empty_state.dart';
import 'order_detail_screen.dart';

const _tabLabels = {
  'to_ship': 'To Ship',
  'in_transit': 'In Transit',
  'out_for_delivery': 'Out for Delivery',
  'completed': 'Completed',
  'cancelled': 'Cancelled',
};

/// Mobile translation of `resources/views/buyer/orders.blade.php` — the
/// same 5 status tabs as the website, backed by real `orders` rows.
class OrdersTab extends StatefulWidget {
  final AppUser user;

  const OrdersTab({super.key, required this.user});

  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: orderStatusTabs.length, vsync: this);
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
        title: const Text('My Orders'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: orderStatusTabs
              .map((status) => Tab(text: _tabLabels[status]))
              .toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: orderStatusTabs
            .map((status) => _OrdersList(user: widget.user, status: status))
            .toList(),
      ),
    );
  }
}

class _OrdersList extends StatefulWidget {
  final AppUser user;
  final String status;

  const _OrdersList({required this.user, required this.status});

  @override
  State<_OrdersList> createState() => _OrdersListState();
}

class _OrdersListState extends State<_OrdersList>
    with AutomaticKeepAliveClientMixin {
  final _orderService = OrderService();
  late Future<List<BuyerOrder>> _future;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _future = _orderService.fetchOrders(
      buyerId: widget.user.id,
      status: widget.status,
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _future = _orderService.fetchOrders(
        buyerId: widget.user.id,
        status: widget.status,
      );
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<BuyerOrder>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [
                ErrorState(message: 'Could not load orders.', error: friendlyError(snapshot.error!)),
              ],
            );
          }
          final orders = snapshot.data ?? [];
          if (orders.isEmpty) {
            return ListView(
              children: [
                EmptyState(
                  icon: Icons.inventory_2_outlined,
                  title: 'No orders yet',
                  subtitle: 'Orders with status "${_tabLabels[widget.status]}" will appear here.',
                ),
              ],
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) => _OrderCard(order: orders[index]),
          );
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final BuyerOrder order;

  const _OrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.forOrderStatus(order.status);
    return Card(
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => OrderDetailScreen(order: order)),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.sellerName,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: style.background,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      order.statusLabel,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: style.color,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                order.orderNumber,
                style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
              ),
              const Divider(height: 20),
              for (final item in order.items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 2),
                  child: Text(
                    '${item.name} × ${item.qty}'
                    '${item.variantLabel.isNotEmpty ? ' (${item.variantLabel})' : ''}',
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Deliver to: ${order.deliverTo.isEmpty ? 'Not provided' : order.deliverTo}',
                    style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                  ),
                  Text(
                    'Total: ${formatPeso(order.total)}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
