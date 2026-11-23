import 'package:flutter/animation.dart';

/// Shared motion tokens — durations/curves every animated widget in the app
/// should reach for instead of hardcoding its own literal. Consolidates
/// what was previously ~20 independently-chosen `Duration(milliseconds: …)`
/// values clustering informally around the same few numbers.
class AppMotion {
  /// Micro-interactions: toggles, chips, focus rings, press feedback.
  static const Duration fast = Duration(milliseconds: 150);

  /// Component-level: cards, sheets, tab switches, list-item entrances.
  static const Duration medium = Duration(milliseconds: 220);

  /// Bigger reveals.
  static const Duration slow = Duration(milliseconds: 320);

  /// The one easing curve used everywhere by default.
  static const Curve curve = Curves.easeOutCubic;
}
