/// One named variation group parsed from `products.variations` jsonb, e.g.
/// `{"name": "Color", "options": [{"value": "Red", "stock": 5}, ...]}`.
class ProductVariation {
  final String name;
  final List<String> options;

  const ProductVariation({required this.name, required this.options});

  factory ProductVariation.fromMap(Map<String, dynamic> map) {
    final options = (map['options'] as List? ?? [])
        .map((o) => (o as Map)['value']?.toString() ?? '')
        .where((v) => v.isNotEmpty)
        .toList();
    return ProductVariation(name: map['name']?.toString() ?? '', options: options);
  }
}

/// Display model for a product — combines a row from the `products` table
/// with its seller's public info (from `users`) and its category name (from
/// `categories`), the same way Laravel's `FetchesProducts::mapProduct()`
/// does.
class Product {
  final String id;
  final String name;
  final double price;
  final String? description;
  final int stock;

  final int sellerId;
  final String sellerName;
  final String? sellerLocation;
  final String? sellerSlug;

  final int? categoryId;
  final String categoryName;

  final List<ProductVariation> variations;

  /// Only populated when the DB value is already a usable http(s) URL.
  /// Today, `products.image` holds a path meant for Laravel's local disk
  /// (e.g. "product_images/xyz.webp"), not a Supabase Storage URL, so this
  /// will usually be null and the UI shows a placeholder instead — see the
  /// note in ProductService.
  final String? imageUrl;

  const Product({
    required this.id,
    required this.name,
    required this.price,
    required this.sellerId,
    required this.sellerName,
    required this.categoryName,
    this.description,
    this.stock = 0,
    this.sellerLocation,
    this.sellerSlug,
    this.categoryId,
    this.variations = const [],
    this.imageUrl,
  });

  bool get hasVariations => variations.isNotEmpty;

  /// Builds a [Product] from a raw `products` row, plus the seller row
  /// (from `users`) and category name looked up separately, since
  /// `products.category_id` has no foreign key for Supabase to auto-embed.
  factory Product.fromRow({
    required Map<String, dynamic> row,
    Map<String, dynamic>? seller,
    String? categoryName,
  }) {
    final businessName = seller?['business_name'] as String?;
    final givenNames = seller?['given_names'] as String? ?? '';
    final lastName = seller?['last_name'] as String? ?? '';
    final sellerName = (businessName != null && businessName.isNotEmpty)
        ? businessName
        : '$givenNames $lastName'.trim();

    final municipality = (seller?['municipality'] as String?)?.trim() ?? '';
    final province = (seller?['province'] as String?)?.trim() ?? '';
    final location = [
      municipality,
      province,
    ].where((part) => part.isNotEmpty).join(', ');

    final rawImage = row['image'] as String?;
    final isUsableUrl =
        rawImage != null &&
        (rawImage.startsWith('http://') || rawImage.startsWith('https://'));

    final rawVariations = row['variations'];
    final variations = rawVariations is List
        ? rawVariations
              .whereType<Map>()
              .map((v) => ProductVariation.fromMap(Map<String, dynamic>.from(v)))
              .where((v) => v.name.isNotEmpty && v.options.isNotEmpty)
              .toList()
        : <ProductVariation>[];

    return Product(
      id: row['id'] as String,
      name: row['name'] as String,
      price: double.tryParse('${row['price']}') ?? 0,
      description: row['description'] as String?,
      stock: (row['stock'] as num?)?.toInt() ?? 0,
      sellerId: row['seller_id'] as int,
      sellerName: sellerName.isEmpty ? 'PocketFinds Seller' : sellerName,
      sellerLocation: location.isEmpty ? null : location,
      sellerSlug: seller?['username'] as String?,
      categoryId: row['category_id'] as int?,
      categoryName: categoryName ?? '—',
      variations: variations,
      imageUrl: isUsableUrl ? rawImage : null,
    );
  }
}
