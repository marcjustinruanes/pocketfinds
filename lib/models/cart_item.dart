import 'product.dart';

/// One row of a buyer's cart — a `cart_items` row joined with its product
/// (and that product's seller), similar in spirit to how Laravel's session
/// cart carries a denormalized snapshot alongside `product_id`.
///
/// The live `cart_items` table names these `qty`/`variation_group`/
/// `variation_value` (a named variant group + the chosen option, matching
/// `products.variations`' own shape) rather than `quantity`/`color`/`size` —
/// this model keeps the shorter `color`/`size`/`quantity` names on the Dart
/// side (few call sites, all pre-existing) and maps them onto the real
/// column names in [CartService].
class CartItem {
  final String id;
  final String productId;
  final String color;
  final String size;
  final int quantity;
  final Product product;
  final double? selectedUnitPrice;
  final double? savedUnitPrice;
  const CartItem({
    required this.id,
    required this.productId,
    required this.color,
    required this.size,
    required this.quantity,
    required this.product,
    this.selectedUnitPrice,
    this.savedUnitPrice,
  });
  ProductOption? get selectedOption {
    for (final option in product.purchaseOptions) {
      if (option.group == color && option.value == size) return option;
    }
    return null;
  }

  int get availableStock => product.purchaseOptions.isEmpty
      ? (product.hasVariations ? 0 : product.stock)
      : selectedOption?.stock ?? 0;
  String? get unavailableReason {
    if (!product.available) return 'Product is no longer available';
    if (product.onVacation) return 'Shop is temporarily unavailable';
    if ((product.hasVariations && selectedOption == null) ||
        (!product.hasVariations && (color.isNotEmpty || size.isNotEmpty))) {
      return 'This variation is no longer available';
    }
    if (availableStock <= 0) return 'Out of stock';
    if (quantity > availableStock) {
      return 'Only $availableStock available. Reduce quantity.';
    }
    return null;
  }

  bool get selectable => quantity > 0 && unavailableReason == null;
  CartItem withQuantity(int value) => CartItem(
    id: id,
    productId: productId,
    color: color,
    size: size,
    quantity: value,
    product: product,
    selectedUnitPrice: selectedUnitPrice,
    savedUnitPrice: savedUnitPrice,
  );
  double get unitPrice =>
      selectedUnitPrice ??
      (product.hasDiscount ? product.discountPrice! : product.price);
  double get lineTotal => unitPrice * quantity;
  String get variantLabel =>
      [color, size].where((v) => v.isNotEmpty).join(' · ');
  factory CartItem.fromRow(Map<String, dynamic> row, Product product) {
    final group = (row['variation_group'] as String?) ?? '';
    final value = (row['variation_value'] as String?) ?? '';
    final options = product.purchaseOptions.where(
      (option) => option.group == group && option.value == value,
    );
    return CartItem(
      id: row['id'] as String,
      productId: row['product_id'] as String,
      color: (row['variation_group'] as String?) ?? '',
      size: (row['variation_value'] as String?) ?? '',
      quantity: (row['qty'] as num).toInt(),
      product: product,
      savedUnitPrice: double.tryParse('${row['price']}'),
      selectedUnitPrice: options.isNotEmpty
          ? options.first.price
          : (product.hasDiscount ? product.discountPrice! : product.price),
    );
  }
}
