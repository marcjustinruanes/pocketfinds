import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/wishlist_item.dart';
import '../../services/cart_service.dart';
import '../../services/wishlist_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/product_image.dart';
import '../../widgets/skeleton.dart';
import 'product_detail_screen.dart';

/// Wishlist — real `wishlist_items`. "Currently unavailable" is real
/// (the product's `products.status` is no longer active — `WishlistService`
/// already reflects that as `product: null`). "Price dropped" is
/// deliberately not shown — there's no price-at-save snapshot to compare
/// against, so that claim would be fabricated.
class WishlistScreen extends StatefulWidget {
  final AppUser user;

  const WishlistScreen({super.key, required this.user});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  final _wishlistService = WishlistService();
  final _cartService = CartService();
  late Future<List<WishlistItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = _wishlistService.fetchWishlist(widget.user.id);
  }

  Future<void> _refresh() async {
    setState(() => _future = _wishlistService.fetchWishlist(widget.user.id));
    await _future;
  }

  Future<void> _remove(WishlistItem item) async {
    try {
      await _wishlistService.remove(widget.user.id, item.productId);
      _refresh();
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not remove: ${friendlyError(e)}');
    }
  }

  Future<void> _addToCart(WishlistItem item) async {
    if (item.product == null) return;
    if (item.product!.hasVariations) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) =>
              ProductDetailScreen(user: widget.user, productId: item.productId),
        ),
      );
      if (mounted) await _refresh();
      return;
    }
    try {
      await _cartService.addToCart(
        buyerId: widget.user.id,
        product: item.product!,
        quantity: 1,
      );
      if (!mounted) return;
      AppSnackBar.success(context, '${item.product!.name} added to cart');
    } catch (e) {
      debugPrint('addToCart failed: $e');
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not add to cart: ${friendlyError(e)}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('Wishlist', style: AppTypography.pageTitle)),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<WishlistItem>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return GridView.builder(
                padding: const EdgeInsets.all(16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 0.75,
                ),
                itemCount: 4,
                itemBuilder: (_, _) => const SkeletonProductCard(),
              );
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(
                    message: 'Could not load wishlist.',
                    error: friendlyError(snapshot.error!),
                  ),
                ],
              );
            }
            final items = snapshot.data ?? [];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(
                    icon: Icons.favorite_border,
                    title: 'Your wishlist is empty',
                    subtitle: 'Tap the heart on any product to save it here.',
                  ),
                ],
              );
            }
            return GridView.builder(
              padding: const EdgeInsets.all(16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 0.68,
              ),
              itemCount: items.length,
              itemBuilder: (context, index) => _WishlistCard(
                item: items[index],
                onTap: items[index].product == null
                    ? null
                    : () => Navigator.of(context)
                          .push(
                            MaterialPageRoute(
                              builder: (_) => ProductDetailScreen(
                                user: widget.user,
                                productId: items[index].productId,
                              ),
                            ),
                          )
                          .then((_) => _refresh()),
                onRemove: () => _remove(items[index]),
                onAddToCart: () => _addToCart(items[index]),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _WishlistCard extends StatelessWidget {
  final WishlistItem item;
  final VoidCallback? onTap;
  final VoidCallback onRemove;
  final VoidCallback onAddToCart;

  const _WishlistCard({
    required this.item,
    this.onTap,
    required this.onRemove,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    final product = item.product;
    final available = product != null;

    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.sm,
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: Opacity(
                    opacity: available ? 1 : 0.4,
                    child: ProductImage(imageUrl: product?.imageUrl),
                  ),
                ),
                Positioned(
                  top: 6,
                  right: 6,
                  child: GestureDetector(
                    onTap: onRemove,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.favorite,
                        color: AppColors.pink,
                        size: 16,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.sm),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 32,
                    child: Text(
                      product?.name ?? 'Product no longer available',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.productTitle.copyWith(
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  if (available) ...[
                    Text(
                      product.sellerName,
                      style: AppTypography.caption,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      formatPeso(product.price),
                      style: AppTypography.price.copyWith(fontSize: 14),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      height: 30,
                      child: ElevatedButton(
                        onPressed: onAddToCart,
                        style: ElevatedButton.styleFrom(
                          padding: EdgeInsets.zero,
                        ),
                        child: const Text(
                          'Add to Cart',
                          style: TextStyle(fontSize: 11),
                        ),
                      ),
                    ),
                  ] else
                    Text(
                      'Currently unavailable',
                      style: TextStyle(
                        color: AppColors.danger,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
