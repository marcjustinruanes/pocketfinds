import 'package:flutter/material.dart';

import '../models/product.dart';
import '../theme/app_colors.dart';

import '../theme/app_typography.dart';
import '../utils/formatters.dart';
import 'pressable.dart';
import 'product_badge.dart';
import 'product_image.dart';
import 'rating_stars.dart';
import 'wishlist_button.dart';

/// A single product tile, styled after the website's `.product` card —
/// same 2-line name clamp with a reserved height (so cards in a row stay
/// aligned regardless of name length), bold 18px price, and a matching
/// pink-soft "Cart" / solid pink "Buy Now" button pair. Badges are real
/// (derived from `discount_price`/`stock`/`created_at` — see `Product`);
/// rating/wishlist are optional so guest/list contexts that haven't
/// fetched them yet still render correctly.
class ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback? onTap;
  final VoidCallback onAddToCart;
  final VoidCallback onBuyNow;
  final double? rating;
  final int? reviewCount;
  final bool? wishlisted;
  final Future<void> Function(bool wishlisted)? onWishlistChanged;

  const ProductCard({
    super.key,
    required this.product,
    required this.onTap,
    required this.onAddToCart,
    required this.onBuyNow,
    this.rating,
    this.reviewCount,
    this.wishlisted,
    this.onWishlistChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      child: Card(
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: BorderSide(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: ProductImage(
                      imageUrl: product.imageUrl,
                      fit: BoxFit.contain,
                    ),
                  ),
                ),
                if (!product.isOutOfStock &&
                    (product.hasDiscount || product.isNew || product.isLowStock))
                  Positioned(
                    top: 8,
                    left: 8,
                    child: Wrap(
                      spacing: 4,
                      runSpacing: 4,
                      children: [
                        if (product.hasDiscount)
                          ProductBadge(
                            type: ProductBadgeType.sale,
                            discountPercent: product.discountPercent,
                          ),
                        if (product.isNew)
                          const ProductBadge(type: ProductBadgeType.newArrival),
                        if (product.isLowStock)
                          const ProductBadge(type: ProductBadgeType.lowStock),
                      ],
                    ),
                  ),
                if (product.isOutOfStock)
                  const Positioned(
                    left: 8,
                    bottom: 8,
                    child: ProductBadge(type: ProductBadgeType.outOfStock),
                  ),
                if (wishlisted != null && onWishlistChanged != null)
                  Positioned(
                    top: 6,
                    right: 6,
                    child: WishlistButton(
                      wishlisted: wishlisted!,
                      onChanged: onWishlistChanged!,
                      size: 15,
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 38,
                    child: Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.productTitle.copyWith(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  // Fixed-height reservations (rather than only rendering
                  // when the optional data exists) — otherwise a card with
                  // e.g. a location + low-stock line ends up taller than
                  // one with neither, and since every card in a row shares
                  // the same grid-enforced cell height, the shorter one
                  // just gets rendered off-center/uneven and the taller
                  // one can overflow it.
                  SizedBox(
                    height: 14,
                    child: product.sellerLocation == null
                        ? null
                        : Row(
                            children: [
                              Icon(
                                Icons.location_on_outlined,
                                size: 12,
                                color: AppColors.textMuted,
                              ),
                              const SizedBox(width: 2),
                              Expanded(
                                child: Text(
                                  product.sellerLocation!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: AppColors.textMuted,
                                  ),
                                ),
                              ),
                            ],
                          ),
                  ),
                  SizedBox(
                    height: 14,
                    child: rating != null
                        ? RatingStars(
                            rating: rating!,
                            reviewCount: reviewCount,
                            size: 11,
                          )
                        : product.isLowStock
                        ? Text(
                            'Only ${product.stock} left',
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.warning,
                              fontWeight: FontWeight.w600,
                            ),
                          )
                        : null,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            formatPeso(
                              product.hasDiscount
                                  ? product.discountPrice!
                                  : product.price,
                            ),
                            style: AppTypography.price,
                          ),
                        ),
                      ),
                      if (product.hasDiscount) ...[
                        const SizedBox(width: 6),
                        Flexible(
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 2),
                            child: Text(
                              formatPeso(product.price),
                              style: TextStyle(
                                fontSize: 11,
                                color: AppColors.textMuted,
                                decoration: TextDecoration.lineThrough,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _CardActionButton(
                          onTap: product.purchasable ? onAddToCart : null,
                          background: AppColors.white,
                          foreground: AppColors.pinkDark,
                          borderColor: AppColors.border,
                          icon: Icons.shopping_cart_outlined,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: _CardActionButton(
                          onTap: product.purchasable ? onBuyNow : null,
                          background: AppColors.pinkDark,
                          foreground: Colors.white,
                          label: product.onVacation
                              ? 'On vacation'
                              : product.purchasable
                              ? 'Buy Now'
                              : product.isOutOfStock
                              ? 'Out of stock'
                              : 'Unavailable',
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CardActionButton extends StatelessWidget {
  final VoidCallback? onTap;
  final Color background;
  final Color foreground;
  final Color? borderColor;
  final IconData? icon;
  final String? label;

  const _CardActionButton({
    required this.onTap,
    required this.background,
    required this.foreground,
    this.borderColor,
    this.icon,
    this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: borderColor != null
                ? Border.all(color: borderColor!)
                : null,
          ),
          child: icon != null
              ? Icon(icon, size: 16, color: foreground)
              : Text(
                  label ?? '',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: foreground,
                  ),
                ),
        ),
      ),
    );
  }
}
