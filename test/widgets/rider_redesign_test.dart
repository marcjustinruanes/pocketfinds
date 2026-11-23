import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/models/rider_profile.dart';
import 'package:pocketfinds/models/shipment.dart';
import 'package:pocketfinds/screens/rider/dashboard_tab.dart';
import 'package:pocketfinds/screens/rider/earnings_tab.dart';
import 'package:pocketfinds/screens/rider/history_tab.dart';
import 'package:pocketfinds/screens/rider/rider_profile_tab.dart';
import 'package:pocketfinds/screens/rider/rider_shell.dart';
import 'package:pocketfinds/screens/rider/tasks_tab.dart';
import 'package:pocketfinds/screens/rider/task_detail_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/conversation_service.dart';
import 'package:pocketfinds/services/delivery_service.dart';
import 'package:pocketfinds/services/rider_web_module_service.dart';
import 'package:pocketfinds/services/shipment_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

const rider = AppUser(
  id: 42,
  accountType: 'rider',
  status: 'approved',
  email: 'rider@example.invalid',
  givenNames: 'Alex',
  lastName: 'Rider',
);
final parcel = Shipment(
  id: 'parcel',
  orderId: 'order',
  shippingStatus: 'delivered',
  courierId: 42,
  buyerId: 51,
  orderNumber: 'PF-ORDER-12345678901234567890',
  trackingNumber: 'PF-SHIP-12345678901234567890',
  items: [],
  total: 146,
  shippingAddress: {
    'street': 'A long delivery street',
    'municipality': 'Santa Cruz',
    'province': 'Laguna',
  },
  deliveredAt: DateTime.now(),
  createdAt: DateTime.now(),
);

class Shipments extends ShipmentService {
  bool fail = false;
  @override
  Future<List<Shipment>> fetchAvailable() async => [];
  @override
  Future<List<Shipment>> fetchMyActive() async {
    if (fail) throw Exception('Connection interrupted');
    return [];
  }

  @override
  Future<List<Shipment>> fetchMyHistory() async {
    if (fail) throw Exception('Connection interrupted');
    return [parcel];
  }

  @override
  Future<RiderProfile?> fetchMyRiderProfile() async => const RiderProfile(
    vehicleType: 'two_wheels',
    status: 'approved',
    hubMunicipality: 'Santa Cruz',
    hubProvince: 'Laguna',
    hubCompany: 'Delivery company',
  );
  @override
  Future<({String name, String contact})?> fetchBuyerContact(String id) async =>
      (name: 'Buyer', contact: '');
}

class Modules extends RiderWebModuleService {
  bool fail = false;
  Completer<Map<String, dynamic>>? pending;
  @override
  Future<Map<String, dynamic>> call(
    String action, [
    Map<String, dynamic> data = const {},
  ]) async {
    if (fail) throw Exception('Connection interrupted');
    if (pending != null) return pending!.future;
    if (action == 'transfers') return {'transfers': []};
    return {
      'jobs': [],
      'daily': [],
      'earnings': {},
      'cash': {},
      'collections': [],
      'delivery_rate': 10,
    };
  }
}

class Delivery extends DeliveryService {
  @override
  Future<Map<String, dynamic>> details(String id) async => {
    'status': 'delivered',
    'order_status': 'delivered',
    'payment_method': 'COD',
    'cod_amount': 146,
    'attempts': [],
    'events': [],
    'tracking_number': parcel.trackingNumber,
  };
}

BackendClient fixtureClient() => BackendClient(
  transport: MockClient(
    (request) async => http.Response(
      jsonEncode(
        request.url.path.endsWith('pickup/list')
            ? {'mine': [], 'available': []}
            : [
                {'leg': 'delivery', 'result': 'delivered'},
                {'leg': 'pickup', 'result': 'picked_up'},
                {'leg': 'delivery', 'result': 'delivery_failed'},
              ],
      ),
      200,
    ),
  ),
);

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);
  testWidgets(
    'home displays a failed request and recovers without losing navigation',
    (tester) async {
      final shipments = Shipments()..fail = true;
      await tester.pumpWidget(
        MaterialApp(
          home: RiderDashboardTab(
            user: rider,
            shipmentService: shipments,
            conversationService: const EmptyConversationService(),
            moduleService: Modules(),
            client: fixtureClient(),
            onOpenTasks: () {},
            onOpenEarnings: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Could not load your work.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      shipments.fail = false;
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Active deliveries'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
  testWidgets(
    'bottom navigation preserves visited screen state and loads tabs on demand',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: RiderShell(
            user: rider,
            shipmentService: Shipments(),
            conversationService: const EmptyConversationService(),
            moduleService: Modules(),
            client: fixtureClient(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final home = tester.state(find.byType(RiderDashboardTab));
      expect(find.byType(EarningsTab, skipOffstage: false), findsNothing);
      await tester.tap(find.text('Tasks').last);
      await tester.pumpAndSettle();
      expect(find.byType(TasksTab), findsOneWidget);
      await tester.tap(find.text('Home').last);
      await tester.pumpAndSettle();
      expect(
        identical(home, tester.state(find.byType(RiderDashboardTab))),
        isTrue,
      );
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
  for (final (width, scale) in [(320.0, 1.5), (400.0, 1.0), (768.0, 1.5)]) {
    testWidgets('all rider sections fit $width at text scale $scale', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      Future<void> show(Widget page) async {
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.current,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: page,
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }

      final shipments = Shipments();
      await show(
        RiderDashboardTab(
          user: rider,
          shipmentService: shipments,
          conversationService: const EmptyConversationService(),
          moduleService: Modules(),
          client: fixtureClient(),
          onOpenTasks: () {},
          onOpenEarnings: () {},
        ),
      );
      expect(find.text('Active deliveries'), findsOneWidget);
      await show(TasksTab(user: rider, service: shipments));
      expect(find.text('No delivery requests'), findsOneWidget);
      await show(
        TaskDetailScreen(
          shipment: parcel,
          riderId: 42,
          deliveryService: Delivery(),
          shipmentService: shipments,
        ),
      );
      await tester.ensureVisible(find.text('COD collected'));
      expect(find.text('COD to collect'), findsNothing);
      expect(find.text('Complete delivery'), findsNothing);
      expect(tester.takeException(), isNull);
      await show(EarningsTab(service: Modules()));
      await tester.ensureVisible(find.text('Payout balance'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await show(RiderHistoryTab(user: rider, service: shipments));
      expect(find.text(parcel.orderNumber), findsOneWidget);
      await show(
        RiderProfileTab(
          user: rider,
          shipmentService: shipments,
          client: fixtureClient(),
        ),
      );
      expect(find.text('Santa Cruz, Laguna'), findsOneWidget);
      expect(
        find.text('2'),
        findsOneWidget,
      ); // Failed attempts are not completed jobs.
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
  testWidgets('earnings loading, failure, and retry use the same data source', (
    tester,
  ) async {
    final modules = Modules()..pending = Completer();
    await tester.pumpWidget(MaterialApp(home: EarningsTab(service: modules)));
    await tester.pump();
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    modules.pending!.completeError(Exception('Connection interrupted'));
    await tester.pumpAndSettle();
    expect(find.text('Could not load earnings.'), findsOneWidget);
    modules.pending = null;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Recorded earnings'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
  });
}
