/// One row of `list_shops()` — a lightweight shop identity for discovery
/// rails (Guest Home's "Discover Shops"), distinct from [ShopStats]/
/// [ShopProfile] which are fetched per-seller once a shop is opened.
class ShopSummary {
  final int sellerId;
  final String name;
  final String? location;
  final int productCount;
  final double averageRating;

  const ShopSummary({
    required this.sellerId,
    required this.name,
    this.location,
    required this.productCount,
    required this.averageRating,
  });

  factory ShopSummary.fromMap(Map<String, dynamic> map) {
    return ShopSummary(
      sellerId: (map['seller_id'] as num).toInt(),
      name: (map['name'] as String?)?.trim().isNotEmpty == true
          ? map['name'] as String
          : 'PocketFinds Seller',
      location: (map['location'] as String?)?.trim().isEmpty ?? true
          ? null
          : map['location'] as String,
      productCount: (map['product_count'] as num?)?.toInt() ?? 0,
      averageRating: double.tryParse('${map['average_rating']}') ?? 0,
    );
  }
}
