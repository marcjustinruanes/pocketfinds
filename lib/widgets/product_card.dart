import 'package:flutter/material.dart';
import '../models/product.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../utils/formatters.dart';
import 'pressable.dart';
import 'product_image.dart';

/// A single product tile, styled after the website's `.product` card —
/// same 2-line name clamp with a reserved height (so cards in a row stay
/// aligned regardless of name length), bold 18px price, and a matching
/// pink-soft "Cart" / solid pink "Buy Now" button pair.
class ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;
  final VoidCallback onAddToCart;
  final VoidCallback onBuyNow;

  const ProductCard({
    super.key,
    required this.product,
    required this.onTap,
    required this.onAddToCart,
    required this.onBuyNow,
  });

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: ProductImage(imageUrl: product.imageUrl),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 36,
                    child: Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        height: 1.35,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (product.sellerLocation != null)
                    Row(
                      children: [
                        const Icon(
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
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ),
                      ],
                    ),
                  Row(
                    children: [
                      const Icon(Icons.star, size: 12, color: AppColors.star),
                      const SizedBox(width: 3),
                      const Text(
                        'New',
                        style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    formatPeso(product.price),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: AppColors.pinkDark,
                      letterSpacing: -0.3,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _CardActionButton(
                          onTap: onAddToCart,
                          background: AppColors.pinkSoft,
                          foreground: AppColors.pinkDark,
                          borderColor: AppColors.pink.withValues(alpha: 0.2),
                          icon: Icons.shopping_cart_outlined,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: _CardActionButton(
                          onTap: onBuyNow,
                          background: AppColors.pink,
                          foreground: AppColors.white,
                          label: 'Buy Now',
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
  final VoidCallback onTap;
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
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: borderColor != null ? Border.all(color: borderColor!) : null,
          ),
          child: icon != null
              ? Icon(icon, size: 16, color: foreground)
              : Text(
                  label ?? '',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: foreground),
                ),
        ),
      ),
    );
  }
}
