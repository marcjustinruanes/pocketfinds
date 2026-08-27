import 'product.dart';

/// One row of a buyer's cart — a `cart_items` row joined with its product
/// (and that product's seller), similar in spirit to how Laravel's session
/// cart carries a denormalized snapshot alongside `product_id`.
class CartItem {
  final String id;
  final String productId;
  final String color;
  final String size;
  final int quantity;
  final Product product;

  const CartItem({
    required this.id,
    required this.productId,
    required this.color,
    required this.size,
    required this.quantity,
    required this.product,
  });

  double get lineTotal => product.price * quantity;

  String get variantLabel =>
      [color, size].where((v) => v.isNotEmpty).join(' · ');

  factory CartItem.fromRow(Map<String, dynamic> row, Product product) {
    return CartItem(
      id: row['id'] as String,
      productId: row['product_id'] as String,
      color: (row['color'] as String?) ?? '',
      size: (row['size'] as String?) ?? '',
      quantity: (row['quantity'] as num).toInt(),
      product: product,
    );
  }
}
