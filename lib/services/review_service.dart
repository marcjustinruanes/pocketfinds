import 'dart:io';

import 'backend_service.dart';

import '../models/review.dart';

/// Reads/writes product reviews via the real `reviews` table's RPCs
/// (`fetch_product_reviews`, `get_product_rating_summary`, `submit_review`
/// — table itself is RPC-only, same lockdown pattern as `messages`), plus
/// the real, public `review-media` Storage bucket a review's optional
/// photo/video proof is uploaded to (owner-folder-scoped write, same
/// pattern `PaymentVerificationService` already uses for payment proofs).
class ReviewService {
  final BackendClient client;
  ReviewService({BackendClient? client})
    : client = client ?? BackendService.client;
  static const bucket = 'review-media';

  Future<List<Review>> fetchForProduct(String productId) async {
    final rows = await client.rpc(
      'fetch_product_reviews',
      params: {'p_product_id': productId},
    );
    return List<Map<String, dynamic>>.from(rows as List)
        .map(Review.fromRow)
        .toList();
  }

  Future<List<Review>> fetchMyReviews() async {
    final rows = await client.rpc('fetch_my_reviews');
    return List<Map<String, dynamic>>.from(rows as List)
        .map((r) => Review.fromRow({...r, 'reviewer_name': ''}))
        .toList();
  }

  Future<RatingSummaryData> fetchRatingSummary(String productId) async {
    final rows = await client.rpc(
      'get_product_rating_summary',
      params: {'p_product_id': productId},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return const RatingSummaryData(average: 0, count: 0);
    return RatingSummaryData.fromMap(list.first);
  }

  Future<String?> findReviewableOrderId(int buyerId, String productId) async {
    final result = await client.rpc(
      'reviewable_product_order',
      params: {'p_product_id': productId},
    );
    return (result as Map)['order_id'] as String?;
  }

  /// Uploads the review's real photo/video proof to the owner's own folder
  /// in `review-media`, returning the storage path `submit_review()` (and
  /// later `mediaUrl`) expects — matches
  /// `PaymentVerificationService.uploadProof`'s pattern exactly.
  Future<String> uploadMedia(File file) async {
    final authUid = client.auth.currentUser?.id;
    if (authUid == null) throw StateError('No active session.');
    final dot = file.path.lastIndexOf('.');
    final ext = dot == -1 ? 'jpg' : file.path.substring(dot + 1).toLowerCase();
    final path =
        '$authUid/${DateTime.now().millisecondsSinceEpoch}_review.${ext.isEmpty ? 'jpg' : ext}';
    await client.storage
        .from(bucket)
        .upload(path, file, fileOptions: const FileOptions(upsert: true));
    return path;
  }

  /// Public URL for a review's attached media, given the raw storage path
  /// (`Review.mediaPath`) — `review-media` is a public bucket so this is a
  /// plain signed-free URL, same as `AuthService.uploadAvatar`'s bucket.
  String mediaUrl(String path) =>
      client.storage.from(bucket).getPublicUrl(path);

  /// Throws (via `DatabaseException`, surfaced through `friendlyError`) if
  /// the caller hasn't actually received this product in a delivered order,
  /// or already reviewed it — enforced server-side in `submit_review()`.
  Future<void> submit({
    required String orderId,
    required String productId,
    required int rating,
    String comment = '',
    String? mediaPath,
    bool isAnonymous = false,
  }) async {
    await client.rpc(
      'submit_review',
      params: {
        'p_order_id': orderId,
        'p_product_id': productId,
        'p_rating': rating,
        'p_comment': comment,
        'p_media_url': mediaPath,
        'p_is_anonymous': isAnonymous,
      },
    );
  }
}
