import 'package:flutter/material.dart';

import '../models/product.dart';
import '../utils/friendly_error.dart';
import 'empty_state.dart';
import 'product_card.dart';
import 'skeleton.dart';
import 'staggered_fade_in.dart';

/// A simple product grid with loading/empty/error states — shared by
/// Category Products, Search Results, and Home's Featured Products section
/// so that layout logic isn't duplicated between them.
class ProductGrid extends StatelessWidget {
  final List<Product>? products;
  final Object? error;
  final void Function(Product) onTap;
  final void Function(Product) onAddToCart;
  final String emptyMessage;

  /// Real wishlist state (product id -> saved?) — omit entirely (leave
  /// both null) for contexts that haven't fetched it (e.g. guest browsing),
  /// in which case no heart button renders at all.
  final Set<String>? wishlistedIds;
  final Future<void> Function(Product product, bool wishlisted)?
  onWishlistChanged;

  /// Set true to embed this inside another scrollable (e.g. Home's
  /// `CustomScrollView`) instead of letting it scroll independently.
  final bool shrinkWrap;
  final ScrollPhysics? physics;

  const ProductGrid({
    super.key,
    required this.products,
    required this.onTap,
    required this.onAddToCart,
    this.error,
    this.emptyMessage = 'No products found.',
    this.wishlistedIds,
    this.onWishlistChanged,
    this.shrinkWrap = false,
    this.physics,
  });

  SliverGridDelegate _gridDelegate(
    BuildContext context,
    double availableWidth,
  ) {
    final width = availableWidth - 32;
    final columns = (width / 180).floor().clamp(2, 6);
    final cardWidth = (width - (columns - 1) * 12) / columns;
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return SliverGridDelegateWithFixedCrossAxisCount(
      crossAxisCount: columns,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      mainAxisExtent: cardWidth + 190 * scale.clamp(1.0, 2.0),
    );
  }

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) =>
        _buildGrid(context, constraints.maxWidth),
  );

  Widget _buildGrid(BuildContext context, double availableWidth) {
    if (products == null && error == null) {
      return GridView.builder(
        padding: const EdgeInsets.all(16),
        shrinkWrap: shrinkWrap,
        physics: physics,
        gridDelegate: _gridDelegate(context, availableWidth),
        itemCount: 4,
        itemBuilder: (context, index) => const SkeletonProductCard(),
      );
    }
    if (error != null) {
      return Center(
        child: ErrorState(
          message: 'Could not load products.',
          error: friendlyError(error!),
        ),
      );
    }
    if (products!.isEmpty) {
      return Center(
        child: EmptyState(icon: Icons.search_off, title: emptyMessage),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      shrinkWrap: shrinkWrap,
      physics: physics,
      gridDelegate: _gridDelegate(context, availableWidth),
      itemCount: products!.length,
      itemBuilder: (context, index) {
        final product = products![index];
        return StaggeredFadeIn(
          index: index,
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
        );
      },
    );
  }
}
