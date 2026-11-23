import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:pocketfinds/models/shipment.dart';
import 'package:pocketfinds/widgets/rider_dashboard_header.dart';
import 'package:pocketfinds/screens/rider/rider_finance_summary.dart';
import 'package:pocketfinds/screens/auth/login_screen.dart';
import 'package:pocketfinds/screens/auth/register/buyer_registration_screen.dart';
import 'package:pocketfinds/screens/rider/task_detail_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/psgc_service.dart';
import 'package:pocketfinds/theme/app_colors.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/widgets/adaptive_card_grid.dart';
import 'package:pocketfinds/widgets/app_bottom_nav.dart';
import 'package:pocketfinds/widgets/active_delivery_card.dart';
import 'package:pocketfinds/widgets/delivery_request_card.dart';
import 'package:pocketfinds/widgets/rider_stat_tile.dart';
import 'package:pocketfinds/widgets/task_card.dart';

class _Directory extends PsgcService {
  @override
  Future<List<PsgcEntry>> fetchRegions() async => [
    const PsgcEntry(code: '04', name: 'CALABARZON'),
  ];
}

void main() {
  setUpAll(() async {
    GoogleFonts.config.allowRuntimeFetching = false;
    SharedPreferences.setMockInitialValues({});
    await BackendService.initialize();
  });
  final shipment = Shipment(
    id: 'parcel',
    orderId: 'order',
    shippingStatus: 'assigned_to_rider',
    orderNumber: 'PF-2026-12345678901234567890',
    trackingNumber: 'TRACK-12345678901234567890',
    items: const [],
    total: 123456.78,
    deliveryFee: 1234.56,
    createdAt: DateTime(2026),
    pickupLocation: 'A very long sorting center address, Calamba City, Laguna',
    shippingAddress: const {
      'barangay': 'Barangay with a long name',
      'municipality': 'City of Calamba',
      'province': 'Laguna',
    },
  );
  for (final width in [320.0, 393.0, 430.0]) {
    for (final scale in [1.0, 1.5]) {
      testWidgets('mobile screens fit width $width text $scale', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 800);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        Future<void> show(Widget screen, {double keyboard = 0}) async {
          await tester.pumpWidget(
            MaterialApp(
              theme: AppTheme.current,
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context).copyWith(
                  textScaler: TextScaler.linear(scale),
                  viewInsets: EdgeInsets.only(bottom: keyboard),
                ),
                child: child!,
              ),
              home: screen,
            ),
          );
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }

        await show(const LoginScreen());
        await show(
          Scaffold(
            body: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: RiderFinanceSummary(
                data: const {
                  'earnings': {'earned': 123456.78, 'pending': 20, 'paid': 50},
                  'daily': [
                    {
                      'date': '2026-11-15',
                      'successful_deliveries': 15,
                      'earned': 150,
                      'paid': 100,
                      'unpaid': 50,
                    },
                    {
                      'date': '2026-11-14',
                      'successful_deliveries': 10,
                      'earned': 100,
                      'paid': 100,
                      'unpaid': 0,
                    },
                  ],
                  'cash': {
                    'collected': 100,
                    'submitted': 40,
                    'outstanding': 100,
                    'available': 60,
                  },
                  'collections': [
                    {
                      'id': 'collection',
                      'tracking_number':
                          'PF-LONG-TRACKING-12345678901234567890',
                      'available': 60,
                      'outstanding': 100,
                      'due_at': '2026-11-15T23:59:00+08:00',
                    },
                  ],
                },
                onRefresh: () async {},
                onOpenPayouts: () {},
              ),
            ),
          ),
        );
        await tester.ensureVisible(find.text('Available to submit'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.ensureVisible(find.text('Record cash handover'));
        await tester.pumpAndSettle();
        expect(find.text('Record cash handover').hitTestable(), findsOneWidget);
        expect(tester.takeException(), isNull);
        await show(const LoginScreen());
        await tester.ensureVisible(find.text('Sign in'));
        await tester.pumpAndSettle();
        expect(find.text('Sign in').hitTestable(), findsOneWidget);
        await show(const LoginScreen(), keyboard: 280);
        await tester.ensureVisible(find.text('Password'));
        await tester.pumpAndSettle();
        expect(find.text('Password').hitTestable(), findsOneWidget);
        expect(tester.takeException(), isNull);
        for (final role in ['buyer', 'rider']) {
          final client = BackendClient(
            transport: MockClient(
              (request) async => http.Response(
                request.url.path.endsWith('registration-options')
                    ? '{"vehicle_types":[],"hubs":[]}'
                    : jsonEncode([
                        {'id': 1, 'name': 'National ID'},
                      ]),
                200,
              ),
            ),
          );
          await show(
            BuyerRegistrationScreen(
              key: ValueKey(role),
              accountType: role,
              addressService: _Directory(),
              client: client,
            ),
          );
          await tester.ensureVisible(find.text('Continue'));
          await tester.pumpAndSettle();
          expect(find.text('Continue').hitTestable(), findsOneWidget);
          expect(tester.takeException(), isNull);
        }
        await show(
          Scaffold(
            body: CustomScrollView(
              slivers: [
                RiderDashboardHeader(
                  unreadCount: 12,
                  onNotifications: () {},
                  onMessages: () {},
                  onAssigned: () {},
                  onPickup: () {},
                ),
              ],
            ),
          ),
        );
        expect(find.byTooltip('Notifications'), findsOneWidget);
        expect(find.byTooltip('Delivery tools'), findsOneWidget);
        expect(tester.takeException(), isNull);
        await show(
          Scaffold(
            body: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  AdaptiveCardGrid(
                    children: [
                      for (final label in ['DELIVERED', 'COMPLETED', 'PENDING'])
                        RiderStatTile(
                          label: label,
                          value: '123456',
                          icon: Icons.local_shipping,
                          color: AppColors.pink,
                          background: AppColors.pinkSoft,
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  TaskCard(shipment: shipment, onTap: () {}),
                  const SizedBox(height: 16),
                  ActiveDeliveryCard(shipment: shipment, onTap: () {}),
                  const SizedBox(height: 16),
                  DeliveryRequestCard(
                    shipment: shipment,
                    onTap: () {},
                    onAccept: () {},
                    onDecline: () {},
                  ),
                ],
              ),
            ),
            bottomNavigationBar: AppBottomNav(
              currentIndex: 0,
              onTap: (_) {},
              items: const [
                BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
                BottomNavigationBarItem(
                  icon: Icon(Icons.assignment),
                  label: 'Tasks',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.payments),
                  label: 'Earnings',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.history),
                  label: 'History',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.person),
                  label: 'Profile',
                ),
              ],
            ),
          ),
        );
        await tester.ensureVisible(find.text('Accept'));
        await tester.pumpAndSettle();
        expect(find.text('Accept').hitTestable(), findsOneWidget);
        expect(tester.takeException(), isNull);
        await show(
          TaskDetailScreen(
            shipment: shipment.copyWith(shippingStatus: 'sorted'),
            riderId: 42,
          ),
        );
        await tester.ensureVisible(find.text('Accept delivery'));
        await tester.pumpAndSettle();
        expect(find.text('Accept delivery').hitTestable(), findsOneWidget);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
      });
    }
  }
}
