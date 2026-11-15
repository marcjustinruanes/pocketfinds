import 'package:flutter/material.dart';

import 'theme_controller.dart';

/// PocketFinds brand colors — originally copied from the website's actual
/// CSS (`public/css/marketplace.css`, `public/css/buyer.css` `:root`
/// variables), now a light/dark PAIR per token so the app looks like the
/// same product as the site in light mode, and an intentional (not
/// auto-inverted) dark reskin when `ThemeController.isDark` is true.
///
/// Every token is a `static Color get`, not `static const` — reading
/// [ThemeController.isDark] at build time — so every one of the ~125+
/// existing `AppColors.xxx` call sites across the app keeps working
/// unchanged; only the declarations here changed. The one real cost: a
/// `const` constructor wrapping one of these (`const TextStyle(color:
/// AppColors.pink)`) no longer compiles, since the value isn't a
/// compile-time constant anymore — the compiler finds every such site as a
/// "not a constant expression" error, which is how those were all found
/// and fixed when this file was converted.
class AppColors {
  AppColors._();

  static bool get _dark => ThemeController.isDark;

  static Color get pink =>
      _dark ? const Color(0xFFE0579C) : const Color(0xFFD9468F); // --pink
  static Color get pinkDark => _dark
      ? const Color(0xFFF48FC0)
      : const Color(
          0xFFBE3A7D,
        ); // --pink-dark (dark-mode: lightened for contrast on dark surfaces)
  static Color get pinkSoft =>
      _dark ? const Color(0xFF3A2430) : const Color(0xFFFDF2F8); // --pink-soft
  static Color get pinkBorder => _dark
      ? const Color(0xFF5C3A4C)
      : const Color(0xFFF4C4DC); // guest-notice border
  static Color get pageBackground => _dark
      ? const Color(0xFF121013)
      : const Color(0xFFFFF9FC); // website paper
  static Color get textPrimary => _dark
      ? const Color(0xFFF4F4F5)
      : const Color(0xFF27272A); // website --text
  static Color get textMuted => _dark
      ? const Color(0xFFA1A1AA)
      : const Color(0xFF71717A); // website --muted
  static Color get border => _dark
      ? const Color(0xFF3F3F46)
      : const Color(0xFFE4E4E7); // website --border
  static Color get white =>
      _dark ? const Color(0xFF1C1A1F) : const Color(0xFFFFFFFF); // card/surface color — named "white" for the light-mode meaning, but is really "surface"
  static Color get star =>
      const Color(0xFFF59E0B); // rating star — same in both modes
  static Color get success =>
      _dark ? const Color(0xFF4ADE80) : const Color(0xFF16A34A);
  static Color get successSoft =>
      _dark ? const Color(0xFF14301F) : const Color(0xFFDCFCE7);
  static Color get danger =>
      _dark ? const Color(0xFFF87171) : const Color(0xFFDC2626);
  static Color get dangerSoft =>
      _dark ? const Color(0xFF3A1A1A) : const Color(0xFFFEF2F2);

  // The rest of the site's semantic palette (`buyer.css` `--warning`/`--info`/
  // `--neutral` families) — added so status colors (see StatusStyle) come
  // from named design-system constants instead of one-off hex literals.
  static Color get warning =>
      _dark ? const Color(0xFFFBBF24) : const Color(0xFFA8670A); // --warning
  static Color get warningSoft => _dark
      ? const Color(0xFF3A2E14)
      : const Color(0xFFFDF1DE); // --warning-soft
  static Color get warningLine => _dark
      ? const Color(0xFF5C4A26)
      : const Color(0xFFF2D4A1); // --warning-line
  static Color get info =>
      _dark ? const Color(0xFF8FA8F0) : const Color(0xFF3457C2); // --info
  static Color get infoSoft =>
      _dark ? const Color(0xFF1E2438) : const Color(0xFFEAEEFB); // --info-soft
  static Color get infoLine =>
      _dark ? const Color(0xFF3A4568) : const Color(0xFFC7D1F2); // --info-line
  static Color get neutral =>
      _dark ? const Color(0xFFC4BFC9) : const Color(0xFF5C5564); // --neutral
  static Color get neutralSoft => _dark
      ? const Color(0xFF2A2730)
      : const Color(0xFFEFECF0); // --neutral-soft
  static Color get neutralLine => _dark
      ? const Color(0xFF433F4A)
      : const Color(0xFFDCD6DE); // --neutral-line

  /// The avatar/badge gradient used across the site
  /// (`linear-gradient(145deg,var(--pink),#7a2a56)`) — kept the same in
  /// both modes (self-contained, always paired with white text on top).
  static List<Color> get pinkGradient => [pink, const Color(0xFF7A2A56)];
}
