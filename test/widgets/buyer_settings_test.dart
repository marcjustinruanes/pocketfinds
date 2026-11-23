import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/screens/buyer/settings_screen.dart';
import 'package:pocketfinds/theme/theme_controller.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('app-specific settings remain available and dark mode persists', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    ThemeController.isDarkNotifier.value = false;
    addTearDown(() => ThemeController.isDarkNotifier.value = false);
    const user = AppUser(
      id: 42,
      accountType: 'buyer',
      status: 'approved',
      email: 'buyer@example.com',
      givenNames: 'Buyer',
      lastName: 'Fixture',
    );
    await tester.pumpWidget(
      const MaterialApp(home: SettingsScreen(user: user)),
    );
    for (final label in [
      'Personal Info & Socials',
      'Change Username',
      'Change Email',
      'Change Mobile Number',
      'Change Password',
      'Address',
      'Push Notifications',
      'Payment Methods',
      'Vouchers',
      'Wishlist',
      'Dark Mode',
    ]) {
      await tester.scrollUntilVisible(find.text(label), 120);
      expect(find.text(label), findsOneWidget);
    }
    expect(find.text('Coins'), findsNothing);
    await tester.ensureVisible(find.text('Dark Mode'));
    final darkSwitch = find.descendant(
      of: find.ancestor(
        of: find.text('Dark Mode'),
        matching: find.byType(SwitchListTile),
      ),
      matching: find.byType(Switch),
    );
    await tester.tap(darkSwitch);
    await tester.pumpAndSettle();
    expect(ThemeController.isDark, true);
    expect(
      (await SharedPreferences.getInstance()).getBool('dark_mode_enabled'),
      true,
    );
    for (final label in [
      'Help Center',
      'App Policies & Rules',
      'About',
      'Deactivate Account',
    ]) {
      await tester.scrollUntilVisible(find.text(label), 120);
      expect(find.text(label), findsOneWidget);
    }
  });
}
