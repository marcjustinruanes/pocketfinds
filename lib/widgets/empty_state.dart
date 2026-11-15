import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// A centered icon + title + optional subtitle for "nothing here yet"
/// screens — used by Cart, Orders, Deliveries, and History tabs (each had
/// its own hand-rolled copy of this same layout before being pulled out
/// here). Wrap in a `ListView` at the call site so pull-to-refresh keeps
/// working even when the list is empty.
class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;

  const EmptyState({super.key, required this.icon, required this.title, this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 80, horizontal: 24),
      child: Column(
        children: [
          Icon(icon, size: 48, color: AppColors.textMuted),
          const SizedBox(height: 12),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700), textAlign: TextAlign.center),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

/// A centered error message for "could not load" states — used the same
/// way `EmptyState` is, by the same set of screens.
class ErrorState extends StatelessWidget {
  final String message;
  final Object? error;

  const ErrorState({super.key, required this.message, this.error});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Text(
        error == null ? message : '$message\n$error',
        textAlign: TextAlign.center,
        style: const TextStyle(color: AppColors.textMuted),
      ),
    );
  }
}
