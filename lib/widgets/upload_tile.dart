import 'dart:io';

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'pressable.dart';

/// A dashed-border upload target for the ID photo / selfie capture step —
/// shows a placeholder prompt, a loading spinner while OCR runs, or the
/// picked image with a "Retake"/"Change" action once one is chosen.
class UploadTile extends StatelessWidget {
  final IconData icon;
  final String placeholder;
  final String hint;
  final String actionLabel;
  final File? image;
  final bool loading;
  final VoidCallback onTap;

  const UploadTile({
    super.key,
    required this.icon,
    required this.placeholder,
    required this.actionLabel,
    required this.onTap,
    this.hint = '',
    this.image,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: loading ? null : onTap,
      child: DottedBorderBox(
        child: Container(
          height: 160,
          alignment: Alignment.center,
          padding: const EdgeInsets.all(AppSpacing.md),
          child: image != null
              ? _PickedImage(image: image!, actionLabel: actionLabel)
              : loading
              ? const CircularProgressIndicator(strokeWidth: 2.5)
              : Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      icon,
                      size: 32,
                      color: AppColors.textMuted.withValues(alpha: 0.6),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      placeholder,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted.withValues(alpha: 0.9),
                      ),
                    ),
                    const SizedBox(height: 10),
                    _ActionPill(label: actionLabel),
                    if (hint.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        hint,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 10,
                          color: AppColors.textMuted.withValues(alpha: 0.7),
                        ),
                      ),
                    ],
                  ],
                ),
        ),
      ),
    );
  }
}

class _PickedImage extends StatelessWidget {
  final File image;
  final String actionLabel;

  const _PickedImage({required this.image, required this.actionLabel});

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Image.file(image, fit: BoxFit.cover),
        ),
        Positioned(
          bottom: 6,
          right: 6,
          child: _ActionPill(label: 'Retake', icon: Icons.refresh),
        ),
      ],
    );
  }
}

class _ActionPill extends StatelessWidget {
  final String label;
  final IconData? icon;

  const _ActionPill({required this.label, this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.pink),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon ?? Icons.upload, size: 14, color: AppColors.pink),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: AppColors.pink,
            ),
          ),
        ],
      ),
    );
  }
}

/// A dashed-outline container — Flutter has no built-in dashed border, so
/// this paints one with a `CustomPainter`.
class DottedBorderBox extends StatelessWidget {
  final Widget child;

  const DottedBorderBox({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _DashedBorderPainter(),
      child: ClipRRect(borderRadius: BorderRadius.circular(10), child: child),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.border
      ..strokeWidth = 1.2
      ..style = PaintingStyle.stroke;
    final rrect = RRect.fromRectAndRadius(
      Offset.zero & size,
      const Radius.circular(10),
    );
    final path = Path()..addRRect(rrect);
    const dashWidth = 6.0;
    const dashGap = 4.0;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = distance + dashWidth;
        canvas.drawPath(
          metric.extractPath(distance, next.clamp(0, metric.length)),
          paint,
        );
        distance = next + dashGap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
