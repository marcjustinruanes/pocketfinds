/// A product review — from `fetch_product_reviews()`. `reviewerName` is
/// already privacy-trimmed server-side (given name + last initial), or,
/// for an anonymous review, already masked down to a first letter +
/// asterisks — nothing about the real name is ever sent to the client
/// when [isAnonymous] is true.
class Review {
  final String id;
  final int rating;
  final String? comment;
  final String? sellerReply;
  final DateTime createdAt;
  final String reviewerName;

  /// Only populated by `fetch_my_reviews()` — `fetch_product_reviews()`
  /// omits it since the product is already known from context there.
  final String? productId;

  /// Storage path (in the `review-media` bucket) of the one real photo/
  /// video attached as proof, if any — only a review with this attached
  final String? mediaPath;
  final bool isAnonymous;
  final List<String> photos;
  final String? orderId, variantLabel;

  const Review({
    required this.id,
    required this.rating,
    required this.createdAt,
    required this.reviewerName,
    this.comment,
    this.sellerReply,
    this.productId,
    this.mediaPath,
    this.isAnonymous = false,
    this.photos = const [],
    this.orderId,
    this.variantLabel,
  });

  factory Review.fromRow(Map<String, dynamic> row) {
    return Review(
      id: row['id'] as String,
      rating: (row['rating'] as num).toInt(),
      comment: row['comment'] as String?,
      sellerReply: row['seller_reply'] as String?,
      createdAt:
          DateTime.tryParse(row['created_at']?.toString() ?? '') ??
          DateTime.now(),
      reviewerName: row['reviewer_name']?.toString() ?? 'PocketFinds Buyer',
      productId: row['product_id'] as String?,
      mediaPath: row['media_url'] as String?,
      photos: (row['photos'] as List? ?? []).map((p) => '$p').toList(),
      orderId: row['order_id'] as String?,
      variantLabel: row['variant_label'] as String?,
      isAnonymous: row['is_anonymous'] == true,
    );
  }

  bool get hasMedia => mediaPath != null && mediaPath!.isNotEmpty;

  bool get isVideo {
    final path = mediaPath?.toLowerCase() ?? '';
    return path.endsWith('.mp4') ||
        path.endsWith('.mov') ||
        path.endsWith('.m4v');
  }
}

/// A product's aggregate rating — from `get_product_rating_summary()`.
class RatingSummaryData {
  final double average;
  final int count;

  const RatingSummaryData({required this.average, required this.count});

  factory RatingSummaryData.fromMap(Map<String, dynamic> map) {
    return RatingSummaryData(
      average: double.tryParse('${map['average']}') ?? 0,
      count: (map['review_count'] as num?)?.toInt() ?? 0,
    );
  }
}
