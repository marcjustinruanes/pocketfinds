import '../utils/backend_config.dart';

class ProductOption {
  final String group;
  final String value;
  final String label;
  final double price;
  final int stock;
  final String? imageUrl;
  final Map<String, String> attributes;
  const ProductOption({
    required this.group,
    required this.value,
    required this.label,
    required this.price,
    required this.stock,
    this.imageUrl,
    this.attributes = const {},
  });
}

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
    return ProductVariation(
      name: map['name']?.toString() ?? '',
      options: options,
    );
  }
}

/// Display model for a product — combines a row from the `products` table
/// with its seller's public info (from `users`) and its category name (from
/// `categories`), the same way Laravel's `FetchesProducts::mapProduct()`
/// does.
class Product {
  static String? resolveImageUrl(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    return BackendConfig.mediaUrl(raw);
  }

  final String id;
  final String name;
  final double price;
  final double? discountPrice;
  final String? description;
  final int stock;
  final DateTime? createdAt;

  final int sellerId;
  final String sellerName;
  final String? sellerLocation;
  final String? sellerSlug;

  final int? categoryId;
  final String categoryName;

  final List<ProductVariation> variations;
  final List<ProductOption> purchaseOptions;

  /// A full, public, directly-loadable image URL — resolved from
  /// `products.image` by [_resolveImageUrl]. Null only if the product truly
  /// has no image set.
  final String? imageUrl;
  final List<String> images;
  final bool available, onVacation;

  const Product({
    required this.id,
    required this.name,
    required this.price,
    required this.sellerId,
    required this.sellerName,
    required this.categoryName,
    this.discountPrice,
    this.description,
    this.stock = 0,
    this.createdAt,
    this.sellerLocation,
    this.sellerSlug,
    this.categoryId,
    this.variations = const [],
    this.purchaseOptions = const [],
    this.imageUrl,
    this.images = const [],
    this.available = true,
    this.onVacation = false,
  });

  bool get isOutOfStock =>
      (purchaseOptions.isEmpty
          ? stock <= 0
          : !purchaseOptions.any((o) => o.stock > 0));
  bool get purchasable => available && !onVacation && !isOutOfStock;
  bool get hasVariations => variations.isNotEmpty || purchaseOptions.isNotEmpty;

  ProductOption? selectedOption(Map<String, String> selections) {
    if (variations.any((axis) => !selections.containsKey(axis.name))) {
      return null;
    }
    for (final option in purchaseOptions) {
      if (option.attributes.isNotEmpty &&
          variations.every(
            (axis) => option.attributes.containsKey(axis.name),
          ) &&
          option.attributes.entries.every(
            (entry) => selections[entry.key] == entry.value,
          )) {
        return option;
      }
    }
    return null;
  }

  /// Real badge derivations — no stored "is_on_sale"/"is_new" flags, just
  /// the underlying columns already fetched.
  bool get hasDiscount => discountPrice != null && discountPrice! < price;
  bool get isLowStock => stock > 0 && stock <= 5;
  bool get isNew =>
      createdAt != null && DateTime.now().difference(createdAt!).inDays <= 14;
  int? get discountPercent =>
      hasDiscount ? (100 - (discountPrice! / price * 100)).round() : null;

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

    final rawVariations = row['variations'];
    final regularPrice = double.tryParse('${row['price']}') ?? 0;
    final discountPrice = double.tryParse('${row['discount_price']}');
    final basePrice = discountPrice != null && discountPrice < regularPrice
        ? discountPrice
        : regularPrice;
    final options = <ProductOption>[];
    if ((rawVariations is! List || rawVariations.isEmpty) &&
        row['variant_axes'] is List &&
        (row['variant_axes'] as List).isNotEmpty) {
      for (final option
          in (row['variant_rows'] as List? ?? []).whereType<Map>()) {
        options.add(
          ProductOption(
            imageUrl: resolveImageUrl(option['image']?.toString()),
            group: 'variant:${option['id']}',
            value: '${option['label']}',
            label: '${option['label']}',
            price: double.tryParse('${option['price']}') ?? basePrice,
            stock: (option['stock'] as num?)?.toInt() ?? 0,
          ),
        );
      }
    } else if (rawVariations is List) {
      for (final group in rawVariations.whereType<Map>()) {
        for (final option
            in (group['options'] as List? ?? []).whereType<Map>()) {
          options.add(
            ProductOption(
              imageUrl: resolveImageUrl(option['image']?.toString()),
              attributes: group['type'] == 'combinations'
                  ? Map<String, String>.from(
                      (option['attributes'] as Map? ?? {}).map(
                        (key, value) => MapEntry('$key', '$value'),
                      ),
                    )
                  : {'${group['name']}': '${option['value']}'},
              group: '${group['name']}',
              value: '${option['value']}',
              label: '${group['name']}: ${option['value']}',
              price: double.tryParse('${option['price']}') ?? basePrice,
              stock: group['requires_stock_setup'] == true
                  ? 0
                  : (option['stock'] as num?)?.toInt() ?? 0,
            ),
          );
        }
      }
    }
    final variations = rawVariations is List
        ? rawVariations
              .whereType<Map>()
              .expand(
                (v) => v['type'] == 'combinations'
                    ? (v['axes'] as List? ?? []).whereType<Map>()
                    : [v],
              )
              .map(
                (v) => ProductVariation.fromMap(Map<String, dynamic>.from(v)),
              )
              .where((v) => v.name.isNotEmpty && v.options.isNotEmpty)
              .toList()
        : <ProductVariation>[];

    return Product(
      id: row['id'] as String,
      name: row['name'] as String,
      price: double.tryParse('${row['price']}') ?? 0,
      discountPrice: row['discount_price'] == null
          ? null
          : double.tryParse('${row['discount_price']}'),
      description: row['description'] as String?,
      stock: (row['stock'] as num?)?.toInt() ?? 0,
      createdAt: DateTime.tryParse(row['created_at']?.toString() ?? ''),
      sellerId: row['seller_id'] as int,
      sellerName: sellerName.isEmpty ? 'PocketFinds Seller' : sellerName,
      sellerLocation: location.isEmpty ? null : location,
      sellerSlug: seller?['username'] as String?,
      categoryId: row['category_id'] as int?,
      categoryName: categoryName ?? '—',
      // Older groups can be alternative, self-contained SKUs (for example
      // different formulas). Only combination axes may be selected together.
      variations:
          rawVariations is List &&
              rawVariations.length > 1 &&
              !rawVariations.whereType<Map>().any(
                (g) => g['type'] == 'combinations',
              )
          ? const []
          : variations,
      purchaseOptions: options,
      imageUrl: resolveImageUrl(rawImage),
      images: [
        ?rawImage,
        ...(row['images'] as List? ?? []).map((i) => '$i'),
      ].map(resolveImageUrl).whereType<String>().toSet().toList(),
      available: row['status'] == null || row['status'] == 'active',
      onVacation:
          seller?['is_on_vacation'] == true || seller?['is_on_vacation'] == 1,
    );
  }
}
