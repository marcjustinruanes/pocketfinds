import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/product.dart';
import 'package:pocketfinds/models/cart_item.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/buyer_workflow_service.dart';
import 'package:pocketfinds/services/order_service.dart';
import 'package:pocketfinds/models/order_status_event.dart';
import 'package:pocketfinds/models/app_notification.dart';

void main() {
  test(
    'tracking labels distinguish preparation, pickup and alternative states',
    () {
      expect(buyerStatusLabel('placed'), 'Order Placed');
      expect(buyerStatusLabel('preparing'), 'Preparing Order');
      expect(
        buyerStatusLabel('pending_payment_verification'),
        'Awaiting Payment Verification',
      );
      expect(buyerStatusLabel('ready_for_pickup'), 'Ready for Pickup');
      expect(buyerStatusLabel('picked_up'), 'Picked Up');
      expect(buyerStatusLabel('delivery_failed'), 'Delivery Failed');
      expect(buyerStatusLabel('return_in_transit'), 'Return In Transit');
    },
  );
  test('payment verification notifications belong to Payments', () {
    final notification = AppNotification(
      id: 'n',
      title: 'Verified',
      message: 'Continue payment',
      type: 'gcash_purchase',
      isRead: false,
      createdAt: DateTime(2026),
      referenceId: 'purchase',
    );
    expect(notification.category, 'Payments');
    expect(notification.referenceId, 'purchase');
  });
  test('delivery issue retries reuse the same report identifier after network loss', () async {
    final requests = <Map>[];
    final service = BuyerWorkflowService(
      client: BackendClient(
        transport: MockClient((request) async {
          requests.add(jsonDecode(request.body) as Map);
          if (requests.length == 1) throw http.ClientException('Offline');
          return http.Response('{"reported":true,"issue_id":1}', 200);
        }),
      ),
    );
    await expectLater(
      service.reportIssue('order', 'damaged', 'Parcel arrived broken.'),
      throwsA(isA<http.ClientException>()),
    );
    await service.reportIssue('order', 'damaged', 'Parcel arrived broken.');
    expect(requests[0]['request_id'], requests[1]['request_id']);
  });
  final base = <String, dynamic>{
    'id': 'product',
    'name': 'Product',
    'price': 100,
    'stock': 5,
    'seller_id': 1,
  };
  test('order tabs distinguish delivery handover from buyer completion', () {
    expect(orderStatusTabs, [
      'all',
      'pending_payment',
      'pending_payment_verification',
      'placed',
      'preparing',
      'ready_for_pickup',
      'to_ship',
      'in_transit',
      'out_for_delivery',
      'delivered',
      'completed',
      'cancelled',
      'refunded',
    ]);
  });
  test('legacy option preserves the actual group, value, price and stock', () {
    final product = Product.fromRow(
      row: {
        ...base,
        'variations': [
          {
            'name': 'Color',
            'options': [
              {'value': 'Red', 'price': 140, 'stock': 2},
            ],
          },
        ],
      },
    );
    final option = product.purchaseOptions.single;
    expect(option.group, 'Color');
    expect(option.value, 'Red');
    expect(option.price, 140);
    expect(option.stock, 2);
  });
  test(
    'matrix option uses the native variant ID instead of its display label',
    () {
      final product = Product.fromRow(
        row: {
          ...base,
          'variant_axes': [
            {'name': 'Color'},
          ],
          'variant_rows': [
            {
              'id': 'red-large',
              'label': 'Red / Large',
              'price': 175,
              'stock': 3,
            },
          ],
        },
      );
      expect(product.purchaseOptions.single.group, 'variant:red-large');
      expect(product.purchaseOptions.single.value, 'Red / Large');
    },
  );
  test('cart totals refresh stale stored prices from the current product', () {
    final product = Product.fromRow(row: base);
    final item = CartItem.fromRow({
      'id': 'cart',
      'product_id': 'product',
      'price': '175.00',
      'qty': 2,
    }, product);
    expect(item.unitPrice, product.price);
    expect(item.lineTotal, product.price * 2);
  });
  test('cart totals use the current selected variant price and stock', () {
    final product = Product.fromRow(
      row: {
        ...base,
        'variant_axes': [
          {'name': 'Color'},
        ],
        'variant_rows': [
          {'id': 'red', 'label': 'Red', 'price': 175, 'stock': 3},
        ],
      },
    );
    final row = <String, dynamic>{
      'id': 'cart',
      'product_id': 'product',
      'price': '100.00',
      'qty': 2,
      'variation_group': 'variant:red',
      'variation_value': 'Red',
    };
    final item = CartItem.fromRow(row, product);
    expect(item.lineTotal, 350);
    expect(item.selectable, isTrue);
    expect(CartItem.fromRow({...row, 'qty': 4}, product).selectable, isFalse);
    expect(
      CartItem.fromRow({
        ...row,
        'variation_group': 'variant:removed',
      }, product).selectable,
      isFalse,
    );
  });
  test('Buy Again retains its request ID after a lost response', () async {
    final requests = <Map>[];
    final service = BuyerWorkflowService(
      client: BackendClient(
        transport: MockClient((request) async {
          requests.add(jsonDecode(request.body) as Map);
          if (requests.length == 1) throw http.ClientException('Offline');
          return http.Response('{"ok":true,"message":"Added"}', 200);
        }),
      ),
    );
    await expectLater(
      service.buyAgain('order'),
      throwsA(isA<http.ClientException>()),
    );
    await service.buyAgain('order');
    expect(requests[0]['request_id'], requests[1]['request_id']);
  });
}
