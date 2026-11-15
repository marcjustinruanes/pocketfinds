import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/screens/buyer/website_content_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';

void main() {
  testWidgets('announcement link opens only its referenced published content', (
    tester,
  ) async {
    final client = BackendClient(
      transport: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'items': [
              {
                'id': 'one',
                'title': 'First announcement',
                'body': 'First details',
              },
              {
                'id': 'two',
                'title': 'Selected announcement',
                'body': 'Selected details',
              },
            ],
          }),
          200,
        ),
      ),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: WebsiteContentScreen(
          kind: 'announcements',
          itemId: 'two',
          client: client,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('First announcement'), findsNothing);
    expect(find.text('Selected announcement'), findsOneWidget);
    expect(find.text('Selected details'), findsOneWidget);
  });
}
