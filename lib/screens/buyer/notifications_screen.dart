import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../models/app_notification.dart';
import '../../services/delivery_service.dart';
import '../../services/incoming_navigation.dart';
import '../../services/notification_service.dart';
import '../../services/order_service.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/friendly_error.dart';
import '../../utils/notification_presentation.dart';
import '../../widgets/app_snackbar.dart';
import 'gcash_purchases_screen.dart';
import 'order_detail_screen.dart';
import 'product_detail_screen.dart';
import 'website_content_screen.dart';
import 'vouchers_screen.dart';

class NotificationsScreen extends StatefulWidget {
  final NotificationService? service;
  final DateTime Function()? now;
  final Future<void> Function(AppNotification)? onOpen;
  const NotificationsScreen({super.key, this.service, this.now, this.onOpen});
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen>
    with WidgetsBindingObserver, DeliveryRefresh<NotificationsScreen> {
  late final _service = widget.service ?? NotificationService();
  List<AppNotification>? _items;
  Object? _error;
  int _unread = 0, _revision = 0;
  bool _loading = false, _markingAll = false;
  DateTime? _lastLoaded;
  final _opening = <String>{};
  String _filter = 'All Notifications';
  DateTime get _now => (widget.now?.call() ?? DateTime.now()).toLocal();
  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  Future<void> refreshDeliveryData() async {
    // Dropdown routes can briefly hide the page; reuse the feed on return.
    if (_lastLoaded != null &&
        DateTime.now().difference(_lastLoaded!) < const Duration(seconds: 15)) {
      return;
    }
    await _refresh();
  }

  Future<void> _refresh() async {
    if (_loading || _markingAll || _opening.isNotEmpty) return;
    _loading = true;
    final revision = _revision;
    try {
      final countFuture = _service
          .fetchUnreadCount()
          .then<int?>((n) => n)
          .catchError((_) => null);
      final items = await _service.fetchAll();
      final count = await countFuture;
      if (!mounted || revision != _revision) return;
      setState(() {
        _items = {for (final item in items) item.id: item}.values.toList();
        _unread = count ?? _items!.where((n) => !n.isRead).length;
        _error = null;
        _lastLoaded = DateTime.now();
      });
    } catch (e) {
      if (mounted && revision == _revision) {
        setState(() {
          _error = e;
        });
      }
    } finally {
      _loading = false;
    }
  }

  void _setRead(Set<String> ids, bool read) {
    _items = _items
        ?.map((n) => ids.contains(n.id) ? n.withRead(read) : n)
        .toList();
  }

  Future<void> _open(AppNotification n) async {
    if (_markingAll || _opening.isNotEmpty || !_opening.add(n.id)) return;
    final oldUnread = _unread;
    setState(() {
      _revision++;
      if (!n.isRead) {
        _setRead({n.id}, true);
        _unread = (_unread - 1).clamp(0, 1 << 30);
      }
    });
    var saved = n.isRead;
    try {
      if (!n.isRead) {
        await _service.markRead(n.id);
        saved = true;
        DeliveryService.updates.add(null);
      }
      if (!mounted) return;
      if (widget.onOpen != null) {
        await widget.onOpen!(n.withRead(true));
      } else {
        await _navigate(n);
      }
    } catch (e) {
      if (!mounted) return;
      if (!saved) {
        setState(() {
          _setRead({n.id}, false);
          _unread = oldUnread;
        });
      }
      AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) {
        setState(() {
          _opening.remove(n.id);
        });
      } else {
        _opening.remove(n.id);
      }
    }
  }

  Future<void> _navigate(AppNotification n) async {
    final riderPage =
        ['rider_profile', 'rider_earnings'].contains(n.type) ||
        n.type.startsWith('hub_transfer') ||
        n.type.startsWith('rider_payout');
    if (riderPage && IncomingNavigation.instance != null) {
      await IncomingNavigation.instance!.open({
        'type': n.type,
        'reference_id': n.referenceId,
      });
      return;
    }
    final id = n.referenceId;
    if (id == null ||
        id.isEmpty ||
        (n.category == 'Account' &&
            !['chat', 'rider_profile'].contains(n.type))) {
      await _details(n);
      return;
    }
    final navigation = IncomingNavigation.instance;
    final supported =
        n.type == 'chat' ||
        n.type == 'rider_profile' ||
        n.type == 'gcash_purchase' ||
        n.type == 'announcement' ||
        n.type == 'flash_sale' ||
        n.category == 'Orders' ||
        n.category == 'Delivery' ||
        n.type.startsWith('payment') ||
        n.type.startsWith('rider_') ||
        n.type.startsWith('commission') ||
        n.type == 'cash_handover';
    if (navigation != null && supported) {
      await navigation.open({
        'type': n.type,
        'reference_id': id,
        'show_tracking': n.category == 'Delivery',
      });
      return;
    }
    if (n.type == 'gcash_purchase') {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => GcashPurchasesScreen(purchaseId: id)),
      );
    } else if (n.type == 'announcement') {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => WebsiteContentScreen(
            kind: 'announcements',
            itemId: id,
            client: _service.client,
          ),
        ),
      );
    } else if (n.type.startsWith('voucher')) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const VouchersScreen(websiteCheckout: true),
        ),
      );
    } else if (n.type == 'flash_sale') {
      final user = await AuthService().fetchMyProfile();
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProductDetailScreen(productId: id, user: user),
        ),
      );
    } else if (n.category == 'Orders' ||
        n.category == 'Delivery' ||
        n.type.startsWith('payment')) {
      final buyer = int.tryParse(_service.client.auth.currentUser?.id ?? '');
      if (buyer == null) {
        await _details(n);
        return;
      }
      final order = await OrderService(client: _service.client)
          .findOrder(buyer, id);
      if (!mounted) return;
      if (order == null) {
        throw StateError('This order is unavailable to your account.');
      }
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OrderDetailScreen(
            order: order,
            myUserId: buyer,
            client: _service.client,
            showTracking: n.category == 'Delivery',
          ),
        ),
      );
    } else {
      await _details(n);
    }
  }

  Future<void> _details(AppNotification n) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              n.title,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 12),
            Text(n.message),
            const SizedBox(height: 16),
            Text(
              _timestamp(n.createdAt),
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Close'),
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _markAllRead() async {
    if (_markingAll || _opening.isNotEmpty || _unread == 0) return;
    final unreadIds = (_items ?? [])
        .where((n) => !n.isRead)
        .map((n) => n.id)
        .toSet();
    final oldUnread = _unread;
    setState(() {
      _revision++;
      _markingAll = true;
      _setRead(unreadIds, true);
      _unread = 0;
    });
    try {
      await _service.markAllRead();
      DeliveryService.updates.add(null);
    } catch (e) {
      if (mounted) {
        setState(() {
          _setRead(unreadIds, false);
          _unread = oldUnread;
        });
        AppSnackBar.error(context, friendlyError(e));
      }
    } finally {
      if (mounted) {
        setState(() {
          _markingAll = false;
        });
      }
    }
  }

  String _timestamp(DateTime time) {
    if (time.millisecondsSinceEpoch == 0) return 'Date not available';
    final local = time.toLocal();
    return DateFormat.yMMMd().add_jm().format(local);
  }

  IconData _icon(AppNotification n) => switch (n.category) {
    'Orders' => Icons.receipt_long_outlined,
    'Delivery' => Icons.local_shipping_outlined,
    'Payments' => Icons.account_balance_wallet_outlined,
    'Promotions' => Icons.local_offer_outlined,
    _ => Icons.manage_accounts_outlined,
  };

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text(
        'Notifications',
        style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
      ),
    ),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 12,
                    runSpacing: 4,
                    children: [
                      Semantics(
                        liveRegion: true,
                        child: Text(
                          _items == null
                              ? (_error == null
                                    ? 'Loading updates…'
                                    : 'Updates unavailable')
                              : _unread == 0
                              ? 'You’re all caught up'
                              : '$_unread unread',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: _unread > 0
                                ? AppColors.pink
                                : AppColors.textMuted,
                          ),
                        ),
                      ),
                      TextButton.icon(
                        onPressed:
                            _items == null ||
                                _markingAll ||
                                _opening.isNotEmpty ||
                                _unread == 0
                            ? null
                            : _markAllRead,
                        icon: _markingAll
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.done_all, size: 18),
                        label: Text(
                          _markingAll ? 'Marking as read…' : 'Mark All as Read',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _filter,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Filter',
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),
                    ),
                    items: [
                      for (final category in notificationCategories)
                        DropdownMenuItem(
                          value: category,
                          child: Text(
                            category,
                            style: const TextStyle(fontSize: 14),
                          ),
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() {
                          _filter = value;
                        });
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
          if (_error != null)
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverToBoxAdapter(
                child: Column(
                  children: [
                    Text(
                      'Could not refresh notifications. ${friendlyError(_error!)}',
                      style: TextStyle(color: AppColors.danger),
                    ),
                    TextButton(
                      onPressed: _refresh,
                      child: const Text('Try again'),
                    ),
                  ],
                ),
              ),
            ),
          if (_items == null && _error == null)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_items != null)
            ..._feed(),
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    ),
  );

  List<Widget> _feed() {
    final groups = groupNotifications(_items!, _filter, _now);
    if (groups.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: AppColors.pinkSoft,
                  child: Icon(
                    Icons.notifications_none,
                    size: 30,
                    color: AppColors.pink,
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _items!.isEmpty
                      ? 'No notifications yet.'
                      : 'No notifications in this category.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  _items!.isEmpty
                      ? 'Order updates and account activities will appear here.'
                      : 'Choose another category to see more updates.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: AppColors.textMuted),
                ),
              ],
            ),
          ),
        ),
      ];
    }
    return [
      for (final entry in groups.entries) ...[
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          sliver: SliverToBoxAdapter(
            child: Text(
              entry.key,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.textMuted,
              ),
            ),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          sliver: SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, i) => _card(entry.value[i]),
              childCount: entry.value.length,
            ),
          ),
        ),
      ],
    ];
  }

  Widget _card(AppNotification n) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Material(
      color: n.isRead ? AppColors.white : AppColors.pinkSoft,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: n.isRead ? AppColors.border : AppColors.pinkBorder,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: _markingAll || _opening.isNotEmpty ? null : () => _open(n),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: n.isRead
                    ? AppColors.pinkSoft
                    : AppColors.white,
                child: Icon(_icon(n), size: 20, color: AppColors.pink),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            n.title,
                            style: TextStyle(
                              fontSize: 14,
                              height: 1.35,
                              fontWeight: n.isRead
                                  ? FontWeight.w500
                                  : FontWeight.w700,
                            ),
                          ),
                        ),
                        if (!n.isRead)
                          Padding(
                            padding: const EdgeInsets.only(left: 8, top: 5),
                            child: Semantics(
                              label: 'Unread notification',
                              child: Container(
                                width: 8,
                                height: 8,
                                decoration: BoxDecoration(
                                  color: AppColors.pink,
                                  shape: BoxShape.circle,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      n.message,
                      style: TextStyle(
                        fontSize: 13,
                        height: 1.45,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Divider(height: 1),
                    const SizedBox(height: 6),
                    Text(
                      _timestamp(n.createdAt),
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: _markingAll || _opening.isNotEmpty
                            ? null
                            : () => _open(n),
                        child: Text(
                          _opening.contains(n.id)
                              ? 'Opening…'
                              : notificationActionLabel(n),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
