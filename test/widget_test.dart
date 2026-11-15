// Basic smoke test: with the real Supabase project configured, a fresh
// app start (no persisted session) should resolve straight to Guest Home
// without crashing. Session resolution for "no session" doesn't need a
// network round trip (it short-circuits once `currentSession` is null), so
// this stays fast and doesn't depend on network access in CI.

import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'package:pocketfinds/main.dart';
import 'package:pocketfinds/utils/supabase_config.dart';

void main() {
  setUpAll(() async {
    // supabase_flutter persists session state via shared_preferences, which
    // has no real platform channel in a plain widget test — mock it empty.
    SharedPreferences.setMockInitialValues({});
    await Supabase.initialize(
      url: SupabaseConfig.url,
      publishableKey: SupabaseConfig.publishableKey,
    );
  });

  testWidgets('resolves to Guest Home on a fresh, session-less start', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const PocketFindsApp());
    await tester.pumpAndSettle();

    expect(find.text('PocketFinds'), findsWidgets);
    expect(find.text("You're browsing as a guest."), findsOneWidget);
  });
}
