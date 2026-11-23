import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/buyer_order.dart';
import '../../services/backend_service.dart';
import '../../services/delivery_service.dart';
import '../../services/order_service.dart';
import '../../services/platform_payment_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/status_badge.dart';
import 'buyer_order_actions.dart';
import 'gcash_purchases_screen.dart';
import 'order_detail_screen.dart';
import 'order_presentation.dart';

class OrdersTab extends StatefulWidget {
  final AppUser user;
  final String initialStatus;
  final BackendClient? client;
  final VoidCallback? onContinueShopping;
  const OrdersTab({
    super.key,
    required this.user,
    this.initialStatus = 'all',
    this.client,
    this.onContinueShopping,
  });
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab>
    with
        SingleTickerProviderStateMixin,
        WidgetsBindingObserver,
        DeliveryRefresh<OrdersTab> {
  late final TabController _tabs;
  late final _service = OrderService(client: widget.client);
  List<BuyerOrder>? _orders;
  Object? _error;
  bool _loading = false;
  @override
  void initState() {
    super.initState();
    final initial = switch (widget.initialStatus) {
      'pending_payment' || 'pending_payment_verification' => 1,
      'placed' || 'preparing' || 'ready_for_pickup' || 'to_ship' => 2,
      'in_transit' || 'out_for_delivery' || 'delivered' => 3,
      'completed' => 4,
      'cancelled' || 'refunded' => 5,
      _ => 0,
    };
    _tabs = TabController(
      length: buyerOrderFilters.length,
      vsync: this,
      initialIndex: initial,
    );
    _tabs.addListener(() {
      if (mounted) setState(() {});
    });
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Future<void> refreshDeliveryData() => _load();
  Future<void> _load() async {
    if (_loading) return;
    _loading = true;
    try {
      final orders = await _service.fetchOrders(
        buyerId: widget.user.id,
        status: 'all',
      );
      if (mounted) {
        setState(() {
          _orders = orders;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
        });
      }
    } finally {
      _loading = false;
    }
  }

  List<BuyerOrder> _filtered(int index) => (_orders ?? [])
      .where((o) => index == 0 || buyerOrderFilter(o) == index)
      .toList();
  Future<void> _open(BuyerOrder order, {bool tracking = false}) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderDetailScreen(
          order: order,
          myUserId: widget.user.id,
          client: widget.client,
          showTracking: tracking,
        ),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text(
        'My Orders',
        style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
      ),
      actions: [
        IconButton(
          tooltip: 'GCash purchases',
          icon: const Icon(Icons.account_balance_wallet_outlined),
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const GcashPurchasesScreen()),
          ),
        ),
      ],
      bottom: TabBar(
        controller: _tabs,
        isScrollable: true,
        tabAlignment: TabAlignment.start,
        labelPadding: const EdgeInsets.symmetric(horizontal: 14),
        indicatorSize: TabBarIndicatorSize.label,
        tabs: [
          for (var i = 0; i < buyerOrderFilters.length; i++)
            Tab(
              child: Text(
                '${buyerOrderFilters[i]}${_orders == null ? '' : ' (${_filtered(i).length})'}',
              ),
            ),
        ],
      ),
    ),
    body: TabBarView(
      controller: _tabs,
      children: [
        for (var i = 0; i < buyerOrderFilters.length; i++)
          RefreshIndicator(onRefresh: _load, child: _list(i)),
      ],
    ),
  );

  Widget _list(int index) {
    if (_orders == null && _error == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_orders == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const Icon(Icons.cloud_off_outlined, size: 32),
          const SizedBox(height: 12),
          const Text('Could not load orders.', textAlign: TextAlign.center),
          Text(friendlyError(_error!), textAlign: TextAlign.center),
          TextButton(onPressed: _load, child: const Text('Retry')),
        ],
      );
    }
    final orders = _filtered(index);
    if (orders.isEmpty) {
      final titles = [
        'No orders yet',
        'No pending payments',
        'No orders to ship',
        'No orders to receive',
        'No completed orders',
        'No cancelled orders',
      ];
      final descriptions = [
        'Your purchases will appear here. Find something you love from our shops.',
        'Orders awaiting payment or verification will appear here.',
        'Orders being prepared by sellers will appear here.',
        'Follow your parcels here once delivery begins.',
        'Your completed purchases will appear here.',
        'Cancelled, returned and refunded orders will appear here.',
      ];
      return LayoutBuilder(
        builder: (context, constraints) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 34,
                      backgroundColor: AppColors.pinkSoft,
                      child: Icon(
                        Icons.receipt_long_outlined,
                        color: AppColors.pink,
                        size: 30,
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      titles[index],
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      descriptions[index],
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    FilledButton(
                      onPressed:
                          widget.onContinueShopping ??
                          () => Navigator.of(context).maybePop(),
                      child: const Text('Continue Shopping'),
                    ),
                    if (_error != null)
                      TextButton(
                        onPressed: _load,
                        child: const Text('Refresh orders'),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
      itemCount: orders.length + (_error == null ? 0 : 1),
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, i) {
        if (_error != null && i == 0) {
          return MaterialBanner(
            content: Text(
              'Could not refresh orders. ${friendlyError(_error!)}',
            ),
            actions: [TextButton(onPressed: _load, child: const Text('Retry'))],
          );
        }
        final order = orders[i - (_error == null ? 0 : 1)];
        return _card(order);
      },
    );
  }

  Widget _card(BuyerOrder order) {
    final shipping = buyerOrderFilter(order) == 3;
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.storefront_outlined,
                  size: 20,
                  color: AppColors.textMuted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    order.sellerName,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                StatusBadge(
                  status: order.status == 'confirmed'
                      ? 'processing'
                      : order.status,
                  label: order.statusLabel,
                ),
                if (order.paymentStatus != null)
                  StatusBadge(
                    status: order.paymentStatus!,
                    label: paymentStatusLabel(order.paymentStatus!),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Order ${order.orderNumber}',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            const Divider(height: 16),
            for (var i = 0; i < order.items.length; i++) ...[
              if (i > 0) const Divider(height: 1),
              PurchasedProductRow(item: order.items[i], compact: true),
            ],
            const Divider(height: 12),
            Text(
              '${order.items.length} products · ${order.items.fold(0, (sum, item) => sum + item.qty)} items',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            OrderAmountRow('Order total', order.total, prominent: true),
            Text(
              'Payment: ${order.paymentMethodName ?? 'Not recorded'}',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            const Divider(height: 16),
            BuyerOrderActions(
              key: ValueKey(
                'actions_${order.id}_${order.status}_${order.paymentStatus}',
              ),
              orderId: order.id,
              user: widget.user,
              client: widget.client,
              platformPayment: order.platformPayment,
              compact: true,
              onViewDetails: () => _open(order),
              onTrack: shipping ? () => _open(order, tracking: true) : null,
            ),
          ],
        ),
      ),
    );
  }
}
