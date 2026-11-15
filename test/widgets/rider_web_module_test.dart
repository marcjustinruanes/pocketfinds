import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/screens/rider/hub_transfers_screen.dart';
import 'package:pocketfinds/screens/rider/rider_payouts_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/rider_web_module_service.dart';

RiderWebModuleService serviceFor(
  Future<Object> Function(Map<String, dynamic>) action,
) => RiderWebModuleService(
  client: BackendClient(
    transport: MockClient(
      (request) async => http.Response(
        jsonEncode(
          await action(
            Map<String, dynamic>.from(jsonDecode(request.body) as Map),
          ),
        ),
        200,
      ),
    ),
  ),
);

void main() {
  testWidgets(
    'transfer pickup requires physical confirmation and renders confirmed state',
    (tester) async {
      final calls = <Map<String, dynamic>>[];
      var pickedUp = false;
      final service = serviceFor((data) async {
        calls.add(data);
        if (data['action'] == 'confirm-transfer') {
          pickedUp = true;
          return {'confirmed': true};
        }
        return {
          'transfers': [
            {
              'id': 'leg-id',
              'tracking_number': 'PF-TRANSFER',
              'from_hub': 'Origin',
              'to_hub': 'Destination',
              'leg_type': 'relay',
              'picked_up_at': pickedUp ? '2026-11-13' : null,
              'batch': {
                'id': 'batch-id',
                'parcel_count': 2,
                'received_count': 1,
                'parcels': [
                  {'tracking_number': 'PARCEL-A', 'received_at': '2026-11-13'},
                  {'tracking_number': 'PARCEL-B', 'received_at': null},
                ],
              },
            },
          ],
        };
      });
      await tester.pumpWidget(
        MaterialApp(home: HubTransfersScreen(service: service)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Transfer batch: 2 parcels'), findsOneWidget);
      expect(find.text('1 received at destination'), findsOneWidget);
      await tester.tap(find.text('View batch manifest'));
      await tester.pumpAndSettle();
      expect(find.text('PARCEL-A'), findsOneWidget);
      expect(find.text('PARCEL-B'), findsOneWidget);
      await tester.tap(find.text('View batch manifest'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Confirm pickup'));
      await tester.tap(find.text('Confirm pickup'));
      await tester.pumpAndSettle();
      expect(
        calls.where((data) => data['action'] == 'confirm-transfer'),
        isEmpty,
      );
      await tester.tap(find.text('I have the parcel'));
      await tester.pumpAndSettle();
      final sent = calls.singleWhere(
        (data) => data['action'] == 'confirm-transfer',
      );
      expect(sent['id'], 'leg-id');
      expect(sent['request_id'], isNotEmpty);
      expect(
        find.text('Picked up · En route to destination hub'),
        findsOneWidget,
      );
      expect(find.text('Confirm pickup'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'pending payouts cannot be acknowledged and account form validates on small screens',
    (tester) async {
      tester.view.physicalSize = const Size(320, 760);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final calls = <Map<String, dynamic>>[];
      final service = serviceFor((data) async {
        calls.add(data);
        return {
          'destinations': [],
          'payouts': [
            {
              'id': 'pending-id',
              'source': 'web',
              'amount': 25,
              'status': 'prepared',
              'can_acknowledge': false,
            },
          ],
        };
      });
      await tester.pumpWidget(
        MaterialApp(home: RiderPayoutsScreen(service: service)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Confirm payout received'), findsNothing);
      await tester.tap(find.text('Add receiving account'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save receiving account'));
      await tester.pumpAndSettle();
      expect(find.text('Enter the registered account name.'), findsOneWidget);
      expect(calls.where((data) => data['action'] == 'destination'), isEmpty);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'released payout receipt asks for confirmation and sends no financial changes',
    (tester) async {
      final calls = <Map<String, dynamic>>[];
      var received = false;
      final service = serviceFor((data) async {
        calls.add(data);
        if (data['action'] == 'acknowledge') {
          received = true;
          return {'acknowledged': true};
        }
        return {
          'destinations': [],
          'payouts': [
            {
              'id': 'paid-id',
              'source': 'app',
              'amount': 25,
              'status': 'released',
              'reference': 'REF-12345',
              'can_acknowledge': !received,
              'acknowledged_at': received ? '2026-11-13' : null,
            },
          ],
        };
      });
      await tester.pumpWidget(
        MaterialApp(home: RiderPayoutsScreen(service: service)),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Confirm payout received'));
      await tester.tap(find.text('Confirm payout received'));
      await tester.pumpAndSettle();
      expect(calls.where((data) => data['action'] == 'acknowledge'), isEmpty);
      await tester.tap(find.text('I received it'));
      await tester.pumpAndSettle();
      expect(calls.singleWhere((data) => data['action'] == 'acknowledge'), {
        'action': 'acknowledge',
        'id': 'paid-id',
        'source': 'app',
        'received': true,
      });
      expect(find.text('Receipt confirmed: 2026-11-13'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
