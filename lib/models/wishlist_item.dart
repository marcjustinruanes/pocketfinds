import 'product.dart';

/// One saved product — a `wishlist_items` row joined with its product, the
/// same shape `CartItem` uses for `cart_items`.
class WishlistItem {
  final String id;
  final String productId;
  final Product?
  product; // null if the product was deleted/deactivated since saving

  const WishlistItem({required this.id, required this.productId, this.product});

  factory WishlistItem.fromRow(Map<String, dynamic> row, Product? product) {
    return WishlistItem(
      id: row['id'] as String,
      productId: row['product_id'] as String,
      product: product,
    );
  }
}
