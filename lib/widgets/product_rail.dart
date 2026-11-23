import 'package:flutter/material.dart';

import '../models/product.dart';
import 'product_card.dart';
import 'staggered_fade_in.dart';

/// A horizontally scrolling row of [ProductCard]s — used for Guest/Buyer
/// Home's "New Arrivals" and "Deals" rails, where `ProductGrid`'s fixed
/// 2-column grid doesn't fit. Reuses `ProductCard` as-is (same badges,
/// price, wishlist button) rather than a separate tile widget.
class ProductRail extends StatelessWidget {
  final List<Product> products;
  final void Function(Product) onTap;
  final void Function(Product) onAddToCart;
  final Set<String>? wishlistedIds;
  final Future<void> Function(Product product, bool wishlisted)?
  onWishlistChanged;

  const ProductRail({
    super.key,
    required this.products,
    required this.onTap,
    required this.onAddToCart,
    this.wishlistedIds,
    this.onWishlistChanged,
  });

  // `ProductCard` isn't a fixed-height widget — its natural height (image
  // + name/location/rating/price/action rows) runs to ~300px+ at a 160px
  // width (matches `ProductGrid`'s own `childAspectRatio: 0.52`, i.e.
  // height = width / 0.52 ≈ 308). This rail previously fixed the height at
  // 268, which was tighter than that and threw "RenderFlex overflowed on
  // the bottom" for any card with a location line + rating/low-stock row.
  static const _itemWidth = 160.0;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height:
          _itemWidth +
          190 * MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: products.length,
        separatorBuilder: (_, _) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final product = products[index];
          return StaggeredFadeIn(
            index: index,
            child: SizedBox(
              width: _itemWidth,
              child: ProductCard(
                product: product,
                onTap: () => onTap(product),
                onAddToCart: () => onAddToCart(product),
                onBuyNow: () => onTap(product),
                wishlisted: wishlistedIds?.contains(product.id),
                onWishlistChanged: onWishlistChanged == null
                    ? null
                    : (v) => onWishlistChanged!(product, v),
              ),
            ),
          );
        },
      ),
    );
  }
}
