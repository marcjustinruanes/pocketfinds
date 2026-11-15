import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// A row of stars for a real rating value (fractional fill), optionally
/// with the "4.8 (126)" text alongside — used on product cards, Product
/// Detail, and the Reviews list.
class RatingStars extends StatelessWidget {
  final double rating;
  final int? reviewCount;
  final double size;
  final bool showCount;

  const RatingStars({
    super.key,
    required this.rating,
    this.reviewCount,
    this.size = 14,
    this.showCount = true,
  });

  @override
  Widget build(BuildContext context) {
    if (reviewCount == 0) {
      return Text('No reviews yet', style: AppTypography.caption);
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        ...List.generate(5, (i) {
          final fill = (rating - i).clamp(0.0, 1.0);
          return Icon(
            fill >= 1
                ? Icons.star
                : (fill > 0 ? Icons.star_half : Icons.star_border),
            size: size,
            color: AppColors.star,
          );
        }),
        if (showCount) ...[
          const SizedBox(width: 4),
          Text(
            reviewCount == null
                ? rating.toStringAsFixed(1)
                : '${rating.toStringAsFixed(1)} ($reviewCount)',
            style: AppTypography.caption.copyWith(fontWeight: FontWeight.w600),
          ),
        ],
      ],
    );
  }
}

/// The big "4.8 / 5" summary block for Product Detail / Reviews, with a
/// per-star distribution bar computed from the real fetched review list
/// (not a server-computed histogram — the review list is already small
/// enough per product to bucket client-side).
class RatingSummary extends StatelessWidget {
  final double average;
  final int count;
  final Map<int, int> distribution; // star (1-5) -> count

  const RatingSummary({
    super.key,
    required this.average,
    required this.count,
    required this.distribution,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Column(
          children: [
            Text(
              average.toStringAsFixed(1),
              style: AppTypography.display.copyWith(fontSize: 34),
            ),
            RatingStars(rating: average, size: 16, showCount: false),
            const SizedBox(height: 4),
            Text(
              '$count review${count == 1 ? '' : 's'}',
              style: AppTypography.caption,
            ),
          ],
        ),
        const SizedBox(width: 20),
        Expanded(
          child: Column(
            children: List.generate(5, (i) {
              final star = 5 - i;
              final starCount = distribution[star] ?? 0;
              final fraction = count == 0 ? 0.0 : starCount / count;
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  children: [
                    Text('$star', style: AppTypography.caption),
                    const SizedBox(width: 4),
                    Icon(Icons.star, size: 11, color: AppColors.star),
                    const SizedBox(width: 6),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(999),
                        child: LinearProgressIndicator(
                          value: fraction,
                          minHeight: 6,
                          backgroundColor: AppColors.border,
                          valueColor: AlwaysStoppedAnimation(AppColors.star),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          ),
        ),
      ],
    );
  }
}
