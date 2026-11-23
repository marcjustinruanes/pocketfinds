// Basic smoke test: with the local MySQL API configured, a fresh
// app start (no persisted session) should resolve straight to Guest Home
// without crashing. Session resolution for "no session" doesn't need a
// network round trip (it short-circuits once `currentSession` is null), so
// this stays fast and doesn't depend on network access in CI.

import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:pocketfinds/services/backend_service.dart';

import 'package:pocketfinds/main.dart';
import 'package:pocketfinds/screens/guest/guest_home_screen.dart';
import 'package:pocketfinds/widgets/storefront_hero.dart';

void main() {
  setUpAll(() async {
    // The local API client persists its session via shared_preferences, which
    // has no real platform channel in a plain widget test — mock it empty.
    SharedPreferences.setMockInitialValues({});
    await BackendService.initialize();
  });

  testWidgets('resolves to Guest Home on a fresh, session-less start', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const PocketFindsApp());
    await tester.pumpAndSettle();

    expect(find.text('PocketFinds'), findsWidgets);
    expect(find.byType(GuestHomeScreen), findsOneWidget);
    expect(find.byType(StorefrontHero), findsOneWidget);
    await tester.drag(find.byType(StorefrontHero), const Offset(0, -350));
    await tester.pumpAndSettle();
    expect(find.text("You're browsing as a guest."), findsNothing);
  });
}
