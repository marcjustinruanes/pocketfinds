import 'order_aftercare_screen.dart';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../models/app_user.dart';
import '../../models/review.dart';
import '../../services/product_service.dart';
import '../../services/review_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/product_image.dart';
import '../../widgets/rating_stars.dart';
import '../../widgets/staggered_fade_in.dart';
import 'product_detail_screen.dart';

/// My Reviews — every real review this buyer has left (`fetch_my_reviews`),
/// with the real product name/image batch-resolved the same way
/// `CartService`/`OrderService` already do, since the RPC itself only
/// returns `product_id`.
class MyReviewsScreen extends StatefulWidget {
  final AppUser user;
  const MyReviewsScreen({super.key, required this.user});

  @override
  State<MyReviewsScreen> createState() => _MyReviewsScreenState();
}

class _MyReviewsScreenState extends State<MyReviewsScreen> {
  final _reviewService = ReviewService();
  final _productService = ProductService();
  late Future<List<(Review, String, String?)>> _future = _load();

  Future<List<(Review, String, String?)>> _load() async {
    final reviews = await _reviewService.fetchMyReviews();
    final productIds = reviews.map((r) => r.productId).whereType<String>();
    final products = await _productService.fetchByIds(productIds);
    return [
      for (final review in reviews)
        (
          review,
          products[review.productId]?.name ?? 'Product',
          products[review.productId]?.imageUrl,
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('My Reviews', style: AppTypography.pageTitle)),
      body: RefreshIndicator(
        color: AppColors.pink,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<List<(Review, String, String?)>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(
                    message: 'Could not load your reviews.',
                    error: friendlyError(snapshot.error!),
                  ),
                ],
              );
            }
            final entries = snapshot.data ?? [];
            if (entries.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(
                    icon: Icons.rate_review_outlined,
                    title: "You haven't written any reviews yet",
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: entries.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final (review, productName, imageUrl) = entries[index];
                return StaggeredFadeIn(
                  index: index,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: review.productId == null
                        ? null
                        : () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ProductDetailScreen(
                                user: widget.user,
                                productId: review.productId!,
                              ),
                            ),
                          ),
                    child: Container(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.border),
                        boxShadow: AppShadows.sm,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: SizedBox(
                                  width: 44,
                                  height: 44,
                                  child: ProductImage(
                                    imageUrl: imageUrl,
                                    fit: BoxFit.cover,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      productName,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: AppTypography.body.copyWith(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    RatingStars(
                                      rating: review.rating.toDouble(),
                                      showCount: false,
                                      size: 13,
                                    ),
                                  ],
                                ),
                              ),
                              Text(
                                DateFormat('MMM d, yyyy')
                                    .format(review.createdAt),
                                style: AppTypography.caption,
                              ),
                            ],
                          ),
                          if (review.isAnonymous || review.hasMedia) ...[
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 6,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                if (review.isAnonymous)
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: AppColors.pageBackground,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      'Posted anonymously',
                                      style: TextStyle(
                                        fontSize: 10.5,
                                        color: AppColors.textMuted,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                if (review.orderId != null)
                                  TextButton(
                                    onPressed: () => Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) => OrderAftercareScreen(
                                          orderId: review.orderId!,
                                        ),
                                      ),
                                    ),
                                    child: const Text('View / edit review'),
                                  ),
                                if (review.hasMedia)
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(8),
                                    child: SizedBox(
                                      width: 40,
                                      height: 40,
                                      child: review.isVideo
                                          ? Container(
                                              color: AppColors.textPrimary,
                                              alignment: Alignment.center,
                                              child: const Icon(
                                                Icons.play_circle_fill,
                                                color: Colors.white,
                                                size: 18,
                                              ),
                                            )
                                          : ProductImage(
                                              imageUrl: _reviewService.mediaUrl(
                                                review.mediaPath!,
                                              ),
                                              fit: BoxFit.cover,
                                            ),
                                    ),
                                  ),
                              ],
                            ),
                          ],
                          if (review.comment != null &&
                              review.comment!.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Text(review.comment!, style: AppTypography.body),
                          ],
                          if (review.sellerReply != null &&
                              review.sellerReply!.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.all(AppSpacing.sm),
                              decoration: BoxDecoration(
                                color: AppColors.pinkSoft,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Seller reply',
                                    style: TextStyle(
                                      color: AppColors.pinkDark,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    review.sellerReply!,
                                    style: AppTypography.caption,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
