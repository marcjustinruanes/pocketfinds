/// A seller's real, aggregated shop stats — from `get_shop_stats()`. No
/// "response rate" (no real signal for it) and no "units sold" (can't be
/// computed reliably from `orders.items`, a JSONB array, within this pass).
class ShopStats {
  final int followerCount;
  final int productCount;
  final double averageRating;
  final int reviewCount;

  const ShopStats({
    required this.followerCount,
    required this.productCount,
    required this.averageRating,
    required this.reviewCount,
  });

  factory ShopStats.fromMap(Map<String, dynamic> map) {
    return ShopStats(
      followerCount: (map['follower_count'] as num?)?.toInt() ?? 0,
      productCount: (map['product_count'] as num?)?.toInt() ?? 0,
      averageRating: double.tryParse('${map['average_rating']}') ?? 0,
      reviewCount: (map['review_count'] as num?)?.toInt() ?? 0,
    );
  }
}
