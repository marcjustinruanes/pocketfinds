import 'dart:convert';
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_notification.dart';
import 'package:pocketfinds/screens/buyer/notifications_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/notification_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/utils/notification_presentation.dart';

void main() {
  testWidgets('failed read updates restore the count and retain the feed', (
    tester,
  ) async {
    var opened = false;
    final service = NotificationService(
      client: BackendClient(
        transport: MockClient((request) async {
          if (request.url.path.endsWith('unread_notification_count')) {
            return http.Response('1', 200);
          }
          if (request.url.path.endsWith('fetch_my_notifications')) {
            return http.Response(
              jsonEncode([
                {
                  'id': 'one',
                  'title': 'Saved update',
                  'message': 'An actual saved notification.',
                  'is_read': false,
                  'notification_type': 'order_status',
                  'created_at': '2026-11-18T09:00:00',
                },
              ]),
              200,
            );
          }
          return http.Response(
            jsonEncode({'message': 'Read update unavailable. Please retry.'}),
            503,
          );
        }),
      ),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: NotificationsScreen(
          service: service,
          now: () => DateTime(2026, 11, 18),
          onOpen: (_) async {
            opened = true;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('View Details'));
    await tester.pumpAndSettle();
    expect(opened, isFalse);
    expect(find.text('1 unread'), findsOneWidget);
    expect(find.text('Saved update'), findsOneWidget);
    await tester.tap(find.text('Mark All as Read'));
    await tester.pumpAndSettle();
    expect(find.text('1 unread'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });
  testWidgets('empty feed retains its dropdown and explains the empty state', (
    tester,
  ) async {
    final service = NotificationService(
      client: BackendClient(
        transport: MockClient(
          (request) async => http.Response(
            request.url.path.endsWith('unread_notification_count') ? '0' : '[]',
            200,
          ),
        ),
      ),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: NotificationsScreen(service: service),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('No notifications yet.'), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);
    expect(find.text('Today'), findsNothing);
    expect(
      tester
          .widget<TextButton>(
            find.widgetWithText(TextButton, 'Mark All as Read'),
          )
          .onPressed,
      isNull,
    );
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });
  final now = DateTime(2026, 11, 18, 12);
  AppNotification notification(String id, String type, DateTime date) =>
      AppNotification(
        id: id,
        title: id,
        message: 'Saved notification details',
        type: type,
        isRead: false,
        createdAt: date,
        referenceId: 'record',
      );
  test('calendar date groups, category types, sorting and deduplication', () {
    final items = [
      notification('old', 'order_status', DateTime(2026, 11, 10)),
      notification('today', 'order_status', now),
      notification('yesterday', 'order_status', DateTime(2026, 11, 17)),
      notification('monday', 'delivery_otp', DateTime(2026, 11, 16)),
      notification('today', 'order_status', now),
    ];
    final groups = groupNotifications(items, 'All Notifications', now);
    expect(groups.keys.toList(), notificationDateGroups);
    expect(groups['Today']!.length, 1);
    expect(groupNotifications(items, 'Delivery', now).keys, [
      'Earlier This Week',
    ]);
    expect(
      notificationDateGroup(DateTime(2026, 11, 15), DateTime(2026, 11, 16)),
      'Yesterday',
    );
    expect(
      notificationDateGroup(DateTime(2026, 11, 14), DateTime(2026, 11, 16)),
      'Previous Notifications',
    );
    expect(
      notificationDateGroup(DateTime(2025, 12, 31), DateTime(2026, 1, 1)),
      'Yesterday',
    );
    expect(notification('pay', 'gcash_purchase', now).category, 'Payments');
    expect(notification('sale', 'flash_sale', now).category, 'Promotions');
    expect(notification('unknown', 'security_update', now).category, 'Account');
    expect(
      AppNotification.fromRow({'id': 1, 'created_at': 'not a date'})
          .createdAt
          .millisecondsSinceEpoch,
      0,
    );
  });
  for (final width in [320.0, 393.0, 430.0]) {
    testWidgets('dropdown preserves date groups and read actions at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var fetches = 0, marked = 0;
      final allRead = Completer<void>();
      String? opened;
      final rows = [
        for (final day in [18, 17, 16, 10])
          {
            'id': 'order-$day',
            'title': 'Saved order update $day',
            'message': 'Your order has a saved status update with a long reference PF-1234567890-REFERENCE. Review the order details.',
            'notification_type': 'order_status',
            'reference_id': 'order-$day',
            'is_read': false,
            'created_at': DateTime(2026, 11, day, 9).toIso8601String(),
          },
        {
          'id': 'payment',
          'title': 'Payment awaiting verification',
          'message': 'Your uploaded receipt is under review.',
          'notification_type': 'gcash_purchase',
          'reference_id': 'purchase',
          'is_read': false,
          'created_at': DateTime(2026, 11, 18, 10).toIso8601String(),
        },
      ];
      final service = NotificationService(
        client: BackendClient(
          transport: MockClient((request) async {
            if (request.url.path.endsWith('fetch_my_notifications')) {
              fetches++;
              return http.Response(jsonEncode(rows), 200);
            }
            if (request.url.path.endsWith('unread_notification_count')) {
              return http.Response('5', 200);
            }
            if (request.url.path.endsWith('mark_notification_read')) {
              marked++;
              return http.Response('null', 200);
            }
            if (request.url.path.endsWith('mark_all_notifications_read')) {
              await allRead.future;
              return http.Response('null', 200);
            }
            return http.Response('null', 200);
          }),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(1.5)),
            child: child!,
          ),
          home: NotificationsScreen(
            service: service,
            now: () => now,
            onOpen: (n) async {
              opened = n.referenceId;
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(TabBar), findsNothing);
      expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);
      expect(find.text('5 unread'), findsOneWidget);
      final beforeFilter = fetches;
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Orders').last);
      await tester.pumpAndSettle();
      expect(fetches, beforeFilter);
      expect(find.text('Today'), findsOneWidget);
      expect(find.text('Saved order update 18'), findsOneWidget);
      expect(find.text('Payment awaiting verification'), findsNothing);
      await tester.tap(find.text('View Order').first);
      await tester.pumpAndSettle();
      expect(marked, 1);
      expect(opened, 'order-18');
      expect(find.text('4 unread'), findsOneWidget);
      for (final heading in [
        'Yesterday',
        'Earlier This Week',
        'Previous Notifications',
      ]) {
        await tester.scrollUntilVisible(
          find.text(heading),
          300,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        expect(find.text(heading), findsOneWidget);
      }
      await tester.drag(find.byType(CustomScrollView), const Offset(0, 2500));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Delivery').last);
      await tester.pumpAndSettle();
      expect(find.text('No notifications in this category.'), findsOneWidget);
      expect(find.text('Today'), findsNothing);
      expect(find.text('Yesterday'), findsNothing);
      await tester.tap(find.text('Mark All as Read'));
      await tester.pump();
      expect(find.text('You’re all caught up'), findsOneWidget);
      allRead.complete();
      await tester.pumpAndSettle();
      expect(fetches, beforeFilter);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}
