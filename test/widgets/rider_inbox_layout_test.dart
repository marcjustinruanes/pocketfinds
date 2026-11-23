import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/models/chat_ui.dart';
import 'package:pocketfinds/screens/buyer/notifications_screen.dart';
import 'package:pocketfinds/screens/rider/messages_tab.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/conversation_service.dart';
import 'package:pocketfinds/services/notification_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/widgets/chat/conversation_list_tile.dart';

class _Chats extends BackendConversationService {
  int calls = 0;
  bool fail = false;
  _Chats() : super(myUserId: 1);
  @override
  Future<List<ConversationSummary>> fetchConversations(String userId) async {
    calls++;
    if (fail) throw const DatabaseException('Connection unavailable');
    return [];
  }
}

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);
  const rider = AppUser(
    id: 1,
    accountType: 'rider',
    status: 'approved',
    email: 'rider@example.invalid',
    givenNames: 'Rider',
    lastName: 'Test',
  );
  for (final width in [320.0, 393.0, 430.0]) {
    testWidgets('inbox alignment, read action and retry at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 780);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      Future<void> show(Widget screen) async {
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.current,
            builder: (c, child) => MediaQuery(
              data: MediaQuery.of(c)
                  .copyWith(textScaler: const TextScaler.linear(1.5)),
              child: child!,
            ),
            home: screen,
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }

      var read = false;
      final service = NotificationService(
        client: BackendClient(
          transport: MockClient((request) async {
            if (request.url.path.endsWith('mark_all_notifications_read')) {
              read = true;
              return http.Response('null', 200);
            }
            return http.Response(
              jsonEncode([
                {
                  'id': 12,
                  'title': 'Pickup confirmed',
                  'message': 'The seller confirmed your parcel handover. Bring it to the sorting center.',
                  'reference_id': 123,
                  'notification_type': 'order_status',
                  'is_read': read ? 1 : 0,
                  'created_at': '2026-11-14 10:00:00',
                },
              ]),
              200,
            );
          }),
        ),
      );
      await show(NotificationsScreen(service: service));
      expect(find.byType(DropdownButtonFormField<String>), findsOneWidget);
      await tester.tap(find.text('Mark All as Read'));
      await tester.pumpAndSettle();
      expect(read, isTrue);
      expect(
        find.text('Something went wrong. Please try again.'),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
      final chats = _Chats();
      await show(RiderMessagesTab(user: rider, service: chats));
      expect(find.text('No messages yet'), findsOneWidget);
      expect(tester.getTopLeft(find.text('All')).dx, lessThan(55));
      await tester.drag(find.byType(CustomScrollView), const Offset(0, 350));
      await tester.pumpAndSettle();
      expect(chats.calls, greaterThan(1));
      await tester.pumpWidget(const SizedBox.shrink());
      final failed = _Chats()..fail = true;
      await show(RiderMessagesTab(user: rider, service: failed));
      failed.fail = false;
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('No messages yet'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await show(
        Scaffold(
          body: ListView(
            children: [
              ConversationListTile(
                conversation: ConversationSummary(
                  id: '2',
                  participantName: 'A buyer with a very long full name',
                  participantAvatarInitial: 'A',
                  lastMessagePreview: 'Please deliver to the building beside the entrance of the subdivision.',
                  lastMessageAt: DateTime.now(),
                  participantRole: 'rider',
                  unreadCount: 3,
                ),
                onTap: () {},
              ),
            ],
          ),
        ),
      );
      expect(tester.takeException(), isNull);
    });
  }
}
