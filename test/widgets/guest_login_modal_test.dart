import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:pocketfinds/screens/auth/login_screen.dart';
import 'package:pocketfinds/screens/auth/account_type_screen.dart';
import 'package:pocketfinds/screens/guest/guest_home_screen.dart';

void main() {
  setUp(() => GoogleFonts.config.allowRuntimeFetching = false);
  testWidgets(
    'guest cart opens login over dashboard; close preserves dashboard; Create one opens registration',
    (tester) async {
      await tester.pumpWidget(const MaterialApp(home: GuestHomeScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsOneWidget);
      expect(find.byType(GuestHomeScreen), findsOneWidget);
      expect(
        tester.widget<LoginScreen>(find.byType(LoginScreen)).embedded,
        isTrue,
      );
      expect(find.text('Email or username'), findsOneWidget);
      expect(find.text('Continue with Google'), findsOneWidget);
      await tester.tap(find.byTooltip('Close login'));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsNothing);
      expect(find.byType(GuestHomeScreen), findsOneWidget);
      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Create one'));
      await tester.tap(find.text('Create one'));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsNothing);
      expect(find.byType(AccountTypeScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('guest search opens modal instead of keyboard or results page', (
    tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: GuestHomeScreen()));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(TextField).first);
    await tester.pumpAndSettle();
    expect(find.byType(Dialog), findsOneWidget);
    expect(find.byType(GuestHomeScreen), findsOneWidget);
  });
}
