import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// A centered icon + title + optional subtitle for "nothing here yet"
/// screens — used by Cart, Orders, Deliveries, and History tabs (each had
/// its own hand-rolled copy of this same layout before being pulled out
/// here). Wrap in a `ListView` at the call site so pull-to-refresh keeps
/// working even when the list is empty.
class EmptyState extends StatelessWidget {
  final IconData? icon;
  final Widget? iconWidget;
  final String title;
  final String? subtitle;

  const EmptyState({
    super.key,
    this.icon,
    this.iconWidget,
    required this.title,
    this.subtitle,
  }) : assert(icon != null || iconWidget != null);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 80, horizontal: 24),
      child: Column(
        children: [
          Container(
            width: 84,
            height: 84,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.pinkSoft,
              shape: BoxShape.circle,
            ),
            child: iconWidget ?? Icon(icon, size: 36, color: AppColors.pink),
          ),
          const SizedBox(height: 16),
          Text(
            title,
            style: AppTypography.productTitle,
            textAlign: TextAlign.center,
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: AppTypography.caption,
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

/// A centered "couldn't load" state — same icon-in-soft-circle layout as
/// [EmptyState] (a danger-tinted circle instead of pink, so a failed fetch
/// never reads as "there's just nothing here"), with an optional [onRetry]
/// action. Used everywhere a `FutureBuilder`'s `snapshot.hasError` branch
/// needs a real recovery path instead of an indefinite spinner or a blank
/// screen.
class ErrorState extends StatelessWidget {
  final String message;
  final Object? error;
  final VoidCallback? onRetry;

  const ErrorState({
    super.key,
    required this.message,
    this.error,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 24),
      child: Column(
        children: [
          Container(
            width: 84,
            height: 84,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.dangerSoft,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.cloud_off_outlined,
              size: 36,
              color: AppColors.danger,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            message,
            style: AppTypography.productTitle,
            textAlign: TextAlign.center,
          ),
          if (error != null) ...[
            const SizedBox(height: 4),
            Text(
              '$error',
              style: AppTypography.caption,
              textAlign: TextAlign.center,
            ),
          ],
          if (onRetry != null) ...[
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Try again'),
            ),
          ],
        ],
      ),
    );
  }
}
