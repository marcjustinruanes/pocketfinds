import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';

import '../screens/auth/login_screen.dart';
import '../screens/auth/register/rider_activation_screen.dart';
import '../screens/rider/pickup_tasks_screen.dart';
import '../screens/rider/tasks_tab.dart';
import '../screens/rider/assigned_deliveries_screen.dart';
import '../screens/rider/task_detail_screen.dart';
import '../screens/rider/earnings_tab.dart';
import '../screens/rider/rider_vehicle_screen.dart';
import '../screens/rider/hub_transfers_screen.dart';
import '../screens/rider/rider_payouts_screen.dart';
import 'shipment_service.dart';
import 'backend_service.dart';
import '../screens/buyer/product_detail_screen.dart';
import '../screens/buyer/shop_screen.dart';
import '../screens/buyer/order_detail_screen.dart';
import '../screens/buyer/conversation_detail_screen.dart';
import '../screens/buyer/gcash_purchases_screen.dart';
import '../screens/buyer/website_content_screen.dart';
import '../widgets/app_snackbar.dart';
import 'auth_service.dart';
import 'conversation_service.dart';
import 'order_service.dart';

/// Only routes to authorized app screens. Incoming IDs are never trusted.
class IncomingNavigation {
  final GlobalKey<NavigatorState> navigator;
  static IncomingNavigation? instance;
  IncomingNavigation(this.navigator) {
    instance = this;
  }
  StreamSubscription<Uri>? _links;
  Map<String, dynamic>? _pending;
  Uri? _lastUri;
  DateTime? _lastUriAt;
  void start() {
    final appLinks = AppLinks();
    _links = appLinks.uriLinkStream.listen(_receiveUri);
    appLinks
        .getInitialLink()
        .then((uri) {
          if (uri != null) _receiveUri(uri);
        })
        .catchError((_) {});
  }

  void _receiveUri(Uri uri) {
    if (uri == _lastUri &&
        _lastUriAt != null &&
        DateTime.now().difference(_lastUriAt!) < const Duration(seconds: 5)) {
      return;
    }
    _lastUri = uri;
    _lastUriAt = DateTime.now();
    final custom = uri.scheme == 'pocketfinds';
    if (!custom &&
        ![
          'pocketfindsph.shop',
          'www.pocketfindsph.shop',
          '127.0.0.1',
          'localhost',
          '10.0.2.2',
        ].contains(uri.host)) {
      return;
    }
    final parts = [if (custom) uri.host, ...uri.pathSegments];
    if (parts.isNotEmpty && parts.first == 'buyer') parts.removeAt(0);
    if (parts.length == 3 && parts[0] == 'rider' && parts[1] == 'activate') {
      open({
        'type': 'rider_activation',
        'invitation': custom
            ? 'https://pocketfindsph.shop/rider/activate/${parts[2]}'
            : uri.toString(),
      });
      return;
    }
    if (parts.length >= 2 &&
        [
          'product',
          'products',
          'p',
          'shop',
          'store',
          'order',
          'orders',
        ].contains(parts[0])) {
      open({
        'type': ['product', 'products', 'p'].contains(parts[0])
            ? 'product'
            : ['shop', 'store'].contains(parts[0])
            ? 'shop'
            : 'order_status',
        'reference_id': parts[1],
      });
    }
  }

  Future<void> flush() async {
    final p = _pending;
    _pending = null;
    if (p != null) await open(p);
  }

  Future<void> open(Map<String, dynamic> data) async {
    final context = navigator.currentContext;
    if (context == null) {
      _pending = data;
      return;
    }
    try {
      final type = '${data['type']}';
      final id = '${data['reference_id'] ?? ''}';
      if (type == 'rider_activation') {
        await navigator.currentState?.push(
          MaterialPageRoute(
            builder: (_) =>
                RiderActivationScreen(invitation: '${data['invitation']}'),
          ),
        );
        return;
      }
      final auth = AuthService();
      final user = auth.currentSession == null
          ? null
          : await auth.fetchMyProfile();
      Widget? page;
      if (type == 'product' || type == 'flash_sale') {
        page = ProductDetailScreen(user: user, productId: id);
      } else if (type == 'shop') {
        final seller = int.tryParse(id);
        if (seller != null) page = ShopScreen(user: user, sellerId: seller);
      } else {
        if (user == null) {
          _pending = data;
          if (context.mounted) {
            await navigator.currentState?.push(
              MaterialPageRoute(builder: (_) => const LoginScreen()),
            );
          }
          return;
        }
        if ((!user.isBuyer && !user.isRider) || !user.isApproved) {
          throw const FormatException(
            'This link is unavailable for your account.',
          );
        }
        if (user.isRider && type != 'chat') {
          if (type == 'delivery_assigned' || type == 'rider_offer') {
            // Offers reference orders, while task lookup is keyed by shipment.
            // Open the rider-scoped assignment inbox directly, including pending
            // offers that do not yet have shipments.courier_id populated.
            page = '${data['title'] ?? ''}'.toLowerCase().contains('pickup')
                ? const PickupTasksScreen()
                : const AssignedDeliveriesScreen();
          } else if (type == 'rider_earnings') {
            page = const EarningsTab();
          } else if (type.startsWith('hub_transfer')) {
            page = const HubTransfersScreen();
          } else if (type.startsWith('rider_payout')) {
            page = const RiderPayoutsScreen();
          } else if (type == 'rider_profile') {
            page = const RiderVehicleScreen();
          } else {
            final picks =
                await BackendService.client.request('pickup/list', {}) as Map;
            final rows = [
              ...(picks['mine'] as List),
              ...(picks['available'] as List),
            ];
            if (rows.any((r) => r['id'] == id || r['order_id'] == id)) {
              page = const PickupTasksScreen();
            } else {
              final service = ShipmentService();
              final jobs = [
                ...await service.fetchMyActive(),
                ...await service.fetchMyHistory(),
                ...await service.fetchAvailable(),
              ];
              for (final job in jobs) {
                if (job.id == id || job.orderId == id) {
                  page = TaskDetailScreen(shipment: job, riderId: user.id);
                  break;
                }
              }
            }
          }
          if (page == null && ['delivery_assigned', 'rider_offer'].contains(type)) {
            page = Scaffold(appBar: AppBar(title: const Text('Assignments')), body: TasksTab(user: user));
          }
        } else if (type == 'gcash_purchase' && user.isBuyer) {
          page = GcashPurchasesScreen(purchaseId: id);
        } else if (type == 'announcement') {
          page = WebsiteContentScreen(
            kind: 'announcements',
            itemId: id.isEmpty ? null : id,
          );
        } else if (type == 'chat') {
          final service = BackendConversationService(myUserId: user.id);
          final chats = await service.fetchConversations('${user.id}');
          for (final chat in chats) {
            if (chat.id == id) {
              page = ConversationDetailScreen(
                conversation: chat,
                service: service,
                user: user,
              );
              break;
            }
          }
        } else if (user.isBuyer) {
          final order = await OrderService().findOrder(user.id, id);
          if (order != null) {
            page = OrderDetailScreen(
              order: order,
              myUserId: user.id,
              showTracking: data['show_tracking'] == true,
            );
          }
        }
      }
      if (!context.mounted) return;
      if (page == null) {
        AppSnackBar.error(
          context,
          'This item was not found or is unavailable to your account.',
        );
        return;
      }
      await navigator.currentState?.push(
        MaterialPageRoute(builder: (_) => page!),
      );
    } catch (_) {
      if (context.mounted) {
        AppSnackBar.error(
          context,
          'This link is unavailable. Sign in with the correct account or open it from your own order list.',
        );
      }
    }
  }

  void dispose() {
    _links?.cancel();
  }
}
