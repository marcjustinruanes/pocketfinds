import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// A consistent success/error/info SnackBar — a small color-coded leading
/// icon on top of the app's existing `snackBarTheme` (dark background,
/// rounded, floating — see `app_theme.dart`), instead of every call site
/// building its own bare `SnackBar(content: Text(...))` with no visual
/// distinction between "it worked" and "it didn't."
class AppSnackBar {
  const AppSnackBar._();

  static void success(BuildContext context, String message) => _show(
    context,
    message,
    icon: Icons.check_circle_outline,
    color: AppColors.success,
  );

  static void error(BuildContext context, String message) => _show(
    context,
    message,
    icon: Icons.error_outline,
    color: AppColors.danger,
  );

  static void info(BuildContext context, String message) =>
      _show(context, message, icon: Icons.info_outline, color: AppColors.pink);

  static void _show(
    BuildContext context,
    String message, {
    required IconData icon,
    required Color color,
  }) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(child: Text(message)),
          ],
        ),
      ),
    );
  }
}
