import 'package:flutter/material.dart';

import '../models/shop_summary.dart';
import '../theme/app_colors.dart';

import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import 'pressable.dart';
import 'rating_stars.dart';

/// A compact shop card for a horizontal "Discover Shops" rail — real name,
/// location, rating, and product count from [ShopSummary] (`list_shops()`).
/// Same avatar-initial-circle recipe as `ConversationListTile`/profile
/// avatars, for visual consistency across the app.
class ShopDiscoveryCard extends StatelessWidget {
  final ShopSummary shop;
  final VoidCallback onTap;

  const ShopDiscoveryCard({super.key, required this.shop, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      child: Container(
        width: 168,
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: AppColors.pinkGradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                shape: BoxShape.circle,
              ),
              child: Text(
                shop.name.isNotEmpty ? shop.name[0].toUpperCase() : '?',
                style: TextStyle(
                  color: AppColors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              shop.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.productTitle,
            ),
            if (shop.location != null) ...[
              const SizedBox(height: 2),
              Row(
                children: [
                  Icon(
                    Icons.location_on_outlined,
                    size: 12,
                    color: AppColors.textMuted,
                  ),
                  const SizedBox(width: 2),
                  Expanded(
                    child: Text(
                      shop.location!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.caption,
                    ),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 6),
            // `list_shops()` only returns an average, not a count — pass 0
            // as the review count when there's no rating yet so
            // `RatingStars` shows "No reviews yet" instead of empty stars.
            RatingStars(
              rating: shop.averageRating,
              reviewCount: shop.averageRating > 0 ? null : 0,
              size: 12,
              showCount: false,
            ),
            const SizedBox(height: 2),
            Text(
              '${shop.productCount} product${shop.productCount == 1 ? '' : 's'}',
              style: AppTypography.caption,
            ),
          ],
        ),
      ),
    );
  }
}
