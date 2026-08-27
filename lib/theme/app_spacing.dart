/// A single spacing scale used everywhere instead of ad hoc numbers, so
/// padding/gaps stay consistent across every screen.
class AppSpacing {
  AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
}

/// Corner radii lifted from the website's actual CSS (`--radius: 12px` for
/// cards, ~9-10px for buttons/inputs, up to 28px for large dialog-style
/// cards like the auth modal).
class AppRadius {
  AppRadius._();

  static const double sm = 8;
  static const double md = 10;
  static const double card = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double dialog = 24;
}
