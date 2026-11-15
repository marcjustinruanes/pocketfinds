import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The app's single dark/light-mode switch — a real, persisted user
/// choice (Settings' toggle), not tied to the OS's system setting. Every
/// `AppColors.*` getter reads [isDark] directly, so flipping this and
/// triggering one rebuild at the app root (see `main.dart`) is enough to
/// re-skin every screen that reads those constants, without touching each
/// screen individually.
class ThemeController {
  ThemeController._();

  static const _prefsKey = 'dark_mode_enabled';

  /// Rebuilds `MaterialApp` in `main.dart` whenever this changes.
  static final ValueNotifier<bool> isDarkNotifier = ValueNotifier(false);

  static bool get isDark => isDarkNotifier.value;

  /// Reads the persisted choice — call once before `runApp`.
  static Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      isDarkNotifier.value = prefs.getBool(_prefsKey) ?? false;
    } catch (_) {
      // Defaults to light if prefs aren't available for some reason.
    }
  }

  static Future<void> setDark(bool value) async {
    isDarkNotifier.value = value;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_prefsKey, value);
    } catch (_) {
      // Non-fatal — the choice still applies for this session, just won't
      // persist across restarts if prefs genuinely aren't writable.
    }
  }
}
