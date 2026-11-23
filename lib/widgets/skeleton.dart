import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// A single pulsing rounded bar — the building block for skeleton loading
/// placeholders, replacing bare `CircularProgressIndicator()` spinners on
/// list/grid screens. Hand-rolled instead of adding the `shimmer` package —
/// a single repeating opacity tween is all this needs.
class Skeleton extends StatefulWidget {
  final double? width;
  final double height;
  final BorderRadius? borderRadius;

  const Skeleton({super.key, this.width, this.height = 14, this.borderRadius});

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  late final Animation<double> _opacity = Tween<double>(
    begin: 0.4,
    end: 1.0,
  ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _opacity,
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: AppColors.border,
          borderRadius: widget.borderRadius ?? BorderRadius.circular(6),
        ),
      ),
    );
  }
}

/// Skeleton silhouette matching [ProductCard]'s layout — image block, two
/// text lines, price line.
class SkeletonProductCard extends StatelessWidget {
  const SkeletonProductCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const AspectRatio(
            aspectRatio: 1,
            child: Skeleton(borderRadius: BorderRadius.zero),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Skeleton(width: double.infinity, height: 13),
                const SizedBox(height: 6),
                const Skeleton(width: 80, height: 11),
                const SizedBox(height: 10),
                const Skeleton(width: 60, height: 16),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Skeleton silhouette for a simple list row (order/notification-style
/// rows) — leading circle, two text lines.
class SkeletonListTile extends StatelessWidget {
  const SkeletonListTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: [
          const Skeleton(
            width: 40,
            height: 40,
            borderRadius: BorderRadius.all(Radius.circular(20)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Skeleton(width: double.infinity, height: 13),
                SizedBox(height: 6),
                Skeleton(width: 120, height: 11),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Skeleton silhouette matching [ShipmentCard]'s layout.
class SkeletonShipmentCard extends StatelessWidget {
  const SkeletonShipmentCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Skeleton(
            width: 44,
            height: 44,
            borderRadius: BorderRadius.all(Radius.circular(10)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Skeleton(width: double.infinity, height: 13),
                SizedBox(height: 8),
                Skeleton(width: 140, height: 11),
                SizedBox(height: 8),
                Skeleton(width: 90, height: 11),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
