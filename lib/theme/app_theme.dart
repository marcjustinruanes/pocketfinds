import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'app_colors.dart';
import 'app_spacing.dart';
import 'theme_controller.dart';

/// Central place for the app's look & feel — mirrors the website's
/// `--font: Inter, ui-sans-serif, system-ui, ...`, its real corner-radius
/// and shadow tokens, and its pink brand colors, so every screen shares one
/// consistent, site-faithful style instead of each being styled ad hoc.
///
/// [current] rebuilds from whatever `AppColors`/`ThemeController.isDark`
/// currently are — there's no separate "dark" `ThemeData` to keep in sync
/// by hand; the same component-theme block below just resolves to dark
/// values once `AppColors` does. Any Material widget this doesn't
/// explicitly style (date pickers, `Switch`, etc.) still falls back to a
/// correct light/dark default because [_base] itself is
/// `ThemeData.light()`/`ThemeData.dark()` accordingly.
class AppTheme {
  AppTheme._();

  static ThemeData get current {
    final dark = ThemeController.isDark;
    final base = dark
        ? ThemeData.dark(useMaterial3: true)
        : ThemeData.light(useMaterial3: true);
    final textTheme = base.textTheme.apply(
      fontFamily: GoogleFonts.inter().fontFamily,
      bodyColor: AppColors.textPrimary,
      displayColor: AppColors.textPrimary,
    );

    return base.copyWith(
      brightness: dark ? Brightness.dark : Brightness.light,
      scaffoldBackgroundColor: AppColors.pageBackground,
      splashFactory: InkRipple.splashFactory,
      colorScheme: base.colorScheme.copyWith(
        brightness: dark ? Brightness.dark : Brightness.light,
        primary: AppColors.pink,
        secondary: AppColors.pinkDark,
        surface: AppColors.white,
        error: AppColors.danger,
      ),
      textTheme: textTheme.copyWith(
        titleLarge: textTheme.titleLarge?.copyWith(
          fontWeight: FontWeight.w800,
          letterSpacing: -0.3,
        ),
        titleMedium: textTheme.titleMedium?.copyWith(
          fontWeight: FontWeight.w700,
        ),
        bodyMedium: textTheme.bodyMedium?.copyWith(height: 1.4),
      ),
      // Every MaterialPageRoute push gets this same subtle fade+slide
      // instead of each screen needing its own transition wiring.
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: _FadeSlidePageTransitionsBuilder(),
          TargetPlatform.iOS: _FadeSlidePageTransitionsBuilder(),
          TargetPlatform.windows: _FadeSlidePageTransitionsBuilder(),
          TargetPlatform.macOS: _FadeSlidePageTransitionsBuilder(),
          TargetPlatform.linux: _FadeSlidePageTransitionsBuilder(),
        },
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: AppColors.white,
        centerTitle: false,
        titleTextStyle: textTheme.titleLarge?.copyWith(
          fontWeight: FontWeight.w800,
          fontSize: 18,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style:
            ElevatedButton.styleFrom(
              minimumSize: const Size(48, AppDimensions.buttonHeight),
              backgroundColor: AppColors.pinkDark,
              foregroundColor: Colors.white,
              disabledBackgroundColor: AppColors.pink.withValues(alpha: 0.4),
              elevation: 0,
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.xl,
                vertical: 12,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              textStyle: GoogleFonts.inter(
                fontWeight: FontWeight.w700,
                fontSize: 14,
              ),
            ).copyWith(
              overlayColor: WidgetStateProperty.all(
                Colors.white.withValues(alpha: 0.08),
              ),
            ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(48, AppDimensions.buttonHeight),
          foregroundColor: AppColors.textPrimary,
          side: BorderSide(color: AppColors.border),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.md,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          textStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w700,
            fontSize: 14,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, AppDimensions.buttonHeight),
          foregroundColor: AppColors.pink,
          textStyle: GoogleFonts.inter(fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        constraints: const BoxConstraints(minHeight: AppDimensions.inputHeight),
        filled: true,
        fillColor: AppColors.white,
        hintStyle: TextStyle(color: AppColors.textMuted, fontSize: 14),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.lg,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: AppColors.pink, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: AppColors.danger, width: 1.2),
        ),
      ),
      cardTheme: CardThemeData(
        color: AppColors.white,
        elevation: 0,
        shadowColor: AppColors.pink.withValues(alpha: 0.12),
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.card),
          side: BorderSide(color: AppColors.border),
        ),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: AppColors.pinkSoft,
        labelStyle: TextStyle(
          color: AppColors.pinkDark,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
        side: BorderSide.none,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: 2,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.xl),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.white,
        surfaceTintColor: AppColors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.xl),
        ),
        titleTextStyle: TextStyle(
          color: AppColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w800,
        ),
        contentTextStyle: TextStyle(color: AppColors.textMuted, fontSize: 14),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: AppColors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.xl),
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.textPrimary,
        contentTextStyle: TextStyle(color: AppColors.white, fontSize: 13),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        actionTextColor: AppColors.pink,
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: AppColors.white,
        selectedItemColor: AppColors.pink,
        unselectedItemColor: AppColors.textMuted,
        type: BottomNavigationBarType.fixed,
        showUnselectedLabels: true,
        selectedLabelStyle: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
        unselectedLabelStyle: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w500,
        ),
        elevation: 0,
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: AppColors.pink,
        unselectedLabelColor: AppColors.textMuted,
        labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        indicatorColor: AppColors.pink,
        indicatorSize: TabBarIndicatorSize.label,
      ),
      dividerTheme: DividerThemeData(color: AppColors.border, space: 1),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: AppColors.pink),
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.pink
              : Colors.transparent,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
      ),
    );
  }
}

/// A soft fade + slight upward slide — subtle enough not to feel slow, but
/// gives page navigation a sense of motion instead of Flutter's default
/// hard cut on non-iOS platforms.
class _FadeSlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const _FadeSlidePageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final curved = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
    );
    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.03),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}
