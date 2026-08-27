import 'package:flutter/material.dart';
import '../models/product.dart';
import '../utils/friendly_error.dart';
import 'empty_state.dart';
import 'product_card.dart';

/// A simple product grid with loading/empty/error states — shared by
/// Category Products and Search Results so that layout logic isn't
/// duplicated between them.
class ProductGrid extends StatelessWidget {
  final List<Product>? products;
  final Object? error;
  final void Function(Product) onTap;
  final void Function(Product) onAddToCart;
  final String emptyMessage;

  const ProductGrid({
    super.key,
    required this.products,
    required this.onTap,
    required this.onAddToCart,
    this.error,
    this.emptyMessage = 'No products found.',
  });

  @override
  Widget build(BuildContext context) {
    if (products == null && error == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (error != null) {
      return Center(
        child: ErrorState(message: 'Could not load products.', error: friendlyError(error!)),
      );
    }
    if (products!.isEmpty) {
      return Center(
        child: EmptyState(icon: Icons.search_off, title: emptyMessage),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.52,
      ),
      itemCount: products!.length,
      itemBuilder: (context, index) {
        final product = products![index];
        return ProductCard(
          product: product,
          onTap: () => onTap(product),
          onAddToCart: () => onAddToCart(product),
          onBuyNow: () => onTap(product),
        );
      },
    );
  }
}
