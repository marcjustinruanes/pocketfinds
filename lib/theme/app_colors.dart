import 'package:flutter/material.dart';

/// PocketFinds brand colors, copied from the website's actual CSS
/// (`public/css/marketplace.css`, `public/css/buyer.css` `:root` variables)
/// so the app looks like the same product as the site, not a generic
/// Flutter template with the brand pink swapped in.
class AppColors {
  AppColors._();

  static const Color pink = Color(0xFFD9468F); // --pink
  static const Color pinkDark = Color(0xFFBE3A7D); // --pink-dark
  static const Color pinkSoft = Color(0xFFFDF2F8); // --pink-soft
  static const Color pinkBorder = Color(0xFFF4C4DC); // guest-notice border
  static const Color pageBackground = Color(0xFFFFF9FC); // --page
  static const Color textPrimary = Color(0xFF27272A); // --text
  static const Color textMuted = Color(0xFF71717A); // --muted
  static const Color border = Color(0xFFE4E4E7); // --border
  static const Color white = Color(0xFFFFFFFF);
  static const Color star = Color(0xFFF59E0B); // rating star color
  static const Color success = Color(0xFF16A34A);
  static const Color danger = Color(0xFFDC2626);
  static const Color dangerSoft = Color(0xFFFEF2F2);

  /// The avatar/badge gradient used across the site
  /// (`linear-gradient(145deg,var(--pink),#7a2a56)`).
  static const List<Color> pinkGradient = [pink, Color(0xFF7A2A56)];
}
