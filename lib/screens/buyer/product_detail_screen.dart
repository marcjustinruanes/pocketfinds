import 'order_aftercare_screen.dart';
import '../../widgets/product_gallery.dart';
import '../../widgets/product_offers.dart';

import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../models/product.dart';
import '../../models/review.dart';
import '../../models/shop_stats.dart';
import '../../services/cart_service.dart';
import '../../services/conversation_service.dart';
import '../../services/product_service.dart';
import '../../services/review_service.dart';
import '../../services/shop_service.dart';
import '../../services/wishlist_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/category_chip.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/product_badge.dart';
import '../../widgets/product_image.dart';
import '../../widgets/product_rail.dart';
import '../../widgets/rating_stars.dart';
import '../../widgets/section_header.dart';
import '../../widgets/titled_card.dart';
import '../../widgets/wishlist_button.dart';
import 'checkout_screen.dart';
import 'conversation_detail_screen.dart';
import 'help_center_screen.dart';
import 'shop_screen.dart';
import 'report_content_screen.dart';

/// Mobile translation of `resources/views/buyer/product.blade.php` — image,
/// name, price, description, category, stock, variant + quantity
/// selection, add to cart / buy now, plus (now real) reviews and a Seller
/// section linking into the shop storefront.
///
/// [user] is null for a guest viewing product details from Guest Home —
/// the page itself is public (matches the website's `guest.product` route),
/// but Add to Cart/Buy Now/Wishlist/Review prompt sign-in instead of
/// acting, same as the guest product card buttons do.
class ProductDetailScreen extends StatefulWidget {
  final AppUser? user;
  final String productId;

  const ProductDetailScreen({
    super.key,
    required this.user,
    required this.productId,
  });

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final _productService = ProductService();
  final _cartService = CartService();
  final _wishlistService = WishlistService();
  final _reviewService = ReviewService();
  final _shopService = ShopService();

  late Future<Product?> _productFuture;
  Future<RatingSummaryData>? _ratingFuture;
  Future<List<Review>>? _reviewsFuture;
  Future<String?>? _reviewableOrderFuture;
  Future<ShopStats>? _shopStatsFuture;
  Future<List<Product>>? _relatedFuture;
  Product? _loadedProduct;
  bool _wishlisted = false;
  final Map<String, String> _selectedVariants = {};
  int _quantity = 1;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _productFuture = _productService.fetchById(widget.productId).then((
      product,
    ) {
      _loadedProduct = product;
      if (product != null) {
        _ratingFuture = _reviewService.fetchRatingSummary(product.id);
        _reviewsFuture = _reviewService.fetchForProduct(product.id);
        _reviewableOrderFuture = widget.user == null
            ? Future.value(null)
            : _reviewService.findReviewableOrderId(widget.user!.id, product.id);
        _shopStatsFuture = _shopService.fetchStats(product.sellerId);
        _relatedFuture = _productService
            .fetchActiveProducts(categoryId: product.categoryId, limit: 11)
            .then(
              (products) =>
                  products.where((p) => p.id != product.id).take(10).toList(),
            );
        _loadWishlisted(product.id);
      }
      return product;
    });
  }

  Future<void> _loadWishlisted(String productId) async {
    final user = widget.user;
    if (user == null) return;
    final wishlisted = await _wishlistService.isWishlisted(user.id, productId);
    if (mounted) setState(() => _wishlisted = wishlisted);
  }

  Future<void> _toggleWishlist(Product product, bool wishlisted) async {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    setState(() => _wishlisted = wishlisted);
    try {
      if (wishlisted) {
        await _wishlistService.add(user.id, product);
      } else {
        await _wishlistService.remove(user.id, product.id);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _wishlisted = !wishlisted);
      AppSnackBar.error(
        context,
        'Could not update wishlist: ${friendlyError(e)}',
      );
    }
  }

  String get _color =>
      _loadedProduct?.selectedOption(_selectedVariants)?.group ??
      _selectedVariants['_group'] ??
      '';
  String get _size =>
      _loadedProduct?.selectedOption(_selectedVariants)?.value ??
      _selectedVariants['_value'] ??
      '';

  void _selectVariant(Product product, String axis, String value) {
    setState(() {
      _selectedVariants[axis] = value;
      // Keep the changed axis; clear incompatible choices from other axes.
      if (!product.purchaseOptions.any(
        (o) =>
            o.stock > 0 &&
            o.attributes.entries.every(
              (e) =>
                  !_selectedVariants.containsKey(e.key) ||
                  _selectedVariants[e.key] == e.value,
            ),
      )) {
        _selectedVariants.removeWhere((key, _) => key != axis);
      }
      final option = product.selectedOption(_selectedVariants);
      if (option != null && _quantity > option.stock) {
        _quantity = option.stock > 0 ? option.stock : 1;
      }
    });
  }

  bool _validSelection(Product product) {
    if (!product.purchasable) {
      AppSnackBar.info(
        context,
        product.onVacation
            ? 'This shop is on vacation.'
            : 'This product is unavailable.',
      );
      return false;
    }
    if (product.hasVariations && _color.isEmpty) {
      AppSnackBar.info(context, 'Choose a product option first.');
      return false;
    }
    final option = _option(product);
    if (option != null && option.stock < _quantity) {
      AppSnackBar.info(context, 'Not enough stock for this option.');
      return false;
    }
    return true;
  }

  ProductOption? _option(Product product) {
    for (final option in product.purchaseOptions) {
      if (option.group == _color && option.value == _size) return option;
    }
    return null;
  }

  Future<void> _addToCart(Product product) async {
    if (!_validSelection(product)) return;
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    setState(() => _busy = true);
    try {
      await _cartService.addToCart(
        buyerId: user.id,
        product: product,
        color: _color,
        size: _size,
        quantity: _quantity,
      );
      if (!mounted) return;
      AppSnackBar.success(context, '${product.name} added to cart');
    } catch (e) {
      debugPrint('addToCart failed: $e');
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not add to cart: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _buyNow(Product product) async {
    if (!_validSelection(product)) return;
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    setState(() => _busy = true);
    try {
      final cart = CartService();
      await cart.addToCart(
        buyerId: user.id,
        product: product,
        color: _color,
        size: _size,
        quantity: _quantity,
      );
      final items = (await cart.fetchCart(user.id))
          .where(
            (item) =>
                item.productId == product.id &&
                item.color == _color &&
                item.size == _size,
          )
          .toList();
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(user: user, items: items),
        ),
      );
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openShop(Product product) async {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            ShopScreen(user: widget.user, sellerId: product.sellerId),
      ),
    );
  }

  /// "Chat Now" — real conversation with the seller, opened with a real
  /// product link auto-sent as the first message (tagged via
  /// `send_message`'s own `p_product_id`, not just plain text).
  void _chatNow(Product product) {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ConversationDetailScreen(
          conversation: ConversationSummary(
            id: product.sellerId.toString(),
            participantName: product.sellerName,
            participantAvatarInitial: product.sellerName.isNotEmpty
                ? product.sellerName[0].toUpperCase()
                : '?',
            lastMessagePreview: '',
            lastMessageAt: DateTime.now(),
            participantRole: 'seller',
          ),
          service: BackendConversationService(myUserId: user.id),
          initialMessage: "Hi! I'm interested in \"${product.name}\".",
          initialProductId: product.id,
          user: user,
        ),
      ),
    );
  }

  Future<void> _shareProduct(Product product) async {
    await SharePlus.instance.share(
      ShareParams(
        text:
            '${product.name} on PocketFinds — pocketfinds://product/${product.id}',
      ),
    );
  }

  Future<void> _reportProduct(Product product) async {
    if (widget.user == null) {
      showGuestSignInSheet(context);
      return;
    }
    try {
      final sent = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => ReportContentScreen(productId: product.id),
        ),
      );
      if (sent != true) return;
      if (!mounted) return;
      AppSnackBar.success(
        context,
        'Thanks — this product was reported to our team.',
      );
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not report: ${friendlyError(e)}');
    }
  }

  void _openHelp() {
    if (widget.user == null) {
      showGuestSignInSheet(context);
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => HelpCenterScreen(user: widget.user!)),
    );
  }

  Future<void> _writeReview(Product product) async {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    try {
      final orderId = await _reviewService.findReviewableOrderId(
        user.id,
        product.id,
      );
      if (!mounted) return;
      if (orderId == null) {
        AppSnackBar.info(
          context,
          'You can review a product after confirming receipt and completing your order.',
        );
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OrderAftercareScreen(orderId: orderId),
        ),
      );
      if (mounted) {
        setState(() {
          _ratingFuture = _reviewService.fetchRatingSummary(product.id);
          _reviewsFuture = _reviewService.fetchForProduct(product.id);
          _reviewableOrderFuture = widget.user == null
              ? Future.value(null)
              : _reviewService.findReviewableOrderId(
                  widget.user!.id,
                  product.id,
                );
        });
      }
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Product'),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            tooltip: 'Share',
            onPressed: _loadedProduct == null
                ? null
                : () => _shareProduct(_loadedProduct!),
          ),
          PopupMenuButton<String>(
            onSelected: (value) {
              if (_loadedProduct == null) return;
              switch (value) {
                case 'report':
                  _reportProduct(_loadedProduct!);
                case 'help':
                  _openHelp();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'report',
                child: Text(
                  'Report Product',
                  style: TextStyle(color: AppColors.danger),
                ),
              ),
              const PopupMenuItem(value: 'help', child: Text('Need Help')),
            ],
          ),
        ],
      ),
      body: FutureBuilder<Product?>(
        future: _productFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          final product = snapshot.data;
          if (product == null) {
            return const Center(child: Text('Product not found.'));
          }

          return SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ProductOffers(
                  productId: product.id,
                  signedIn: widget.user != null,
                ),
                Stack(
                  children: [
                    ProductGallery(
                      key: ValueKey(_option(product)?.imageUrl ?? product.id),
                      images: [
                        if (_option(product)?.imageUrl != null)
                          _option(product)!.imageUrl!,
                        ...product.images,
                      ],
                    ),
                    Positioned(
                      top: 12,
                      right: 12,
                      child: WishlistButton(
                        wishlisted: _wishlisted,
                        onChanged: (v) => _toggleWishlist(product, v),
                      ),
                    ),
                    if (product.hasDiscount ||
                        product.isNew ||
                        product.isLowStock)
                      Positioned(
                        top: 12,
                        left: 12,
                        child: Wrap(
                          spacing: 6,
                          children: [
                            if (product.hasDiscount)
                              ProductBadge(
                                type: ProductBadgeType.sale,
                                discountPercent: product.discountPercent,
                              ),
                            if (product.isNew)
                              const ProductBadge(
                                type: ProductBadgeType.newArrival,
                              ),
                            if (product.isLowStock)
                              const ProductBadge(
                                type: ProductBadgeType.lowStock,
                              ),
                          ],
                        ),
                      ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.pink,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          product.categoryName.toUpperCase(),
                          style: TextStyle(
                            fontSize: 10,
                            color: AppColors.white,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.4,
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(product.name, style: AppTypography.pageTitle),
                      const SizedBox(height: 8),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            formatPeso(
                              _option(product)?.price ??
                                  (product.hasDiscount
                                      ? product.discountPrice!
                                      : product.price),
                            ),
                            style: AppTypography.priceLarge,
                          ),
                          if (product.hasDiscount &&
                              _option(product) == null) ...[
                            const SizedBox(width: 8),
                            Padding(
                              padding: const EdgeInsets.only(bottom: 4),
                              child: Text(
                                formatPeso(product.price),
                                style: TextStyle(
                                  fontSize: 14,
                                  color: AppColors.textMuted,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 6),
                      FutureBuilder<RatingSummaryData>(
                        future: _ratingFuture,
                        builder: (context, snap) {
                          final data = snap.data;
                          return RatingStars(
                            rating: data?.average ?? 0,
                            reviewCount: data?.count,
                          );
                        },
                      ),
                      const SizedBox(height: 4),
                      Text(
                        product.stock > 0
                            ? '${product.stock} in stock'
                            : 'Stock not specified by seller',
                        style: TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const Divider(height: 32),
                      if (product.variations.isNotEmpty) ...[
                        for (final axis in product.variations) ...[
                          Text(
                            axis.name,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final value in axis.options)
                                CategoryChip(
                                  label: value,
                                  selected:
                                      _selectedVariants[axis.name] == value,
                                  onTap: () {
                                    if (!product.purchaseOptions.any(
                                      (o) =>
                                          o.stock > 0 &&
                                          o.attributes[axis.name] == value,
                                    )) {
                                      AppSnackBar.info(
                                        context,
                                        'This option is out of stock.',
                                      );
                                      return;
                                    }
                                    _selectVariant(product, axis.name, value);
                                  },
                                ),
                            ],
                          ),
                          const SizedBox(height: 16),
                        ],
                        Text(
                          _option(product) == null
                              ? 'Choose all options to see availability.'
                              : '${_option(product)!.stock} in stock',
                        ),
                        const SizedBox(height: 16),
                      ] else if (product.purchaseOptions.isNotEmpty) ...[
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final option in product.purchaseOptions)
                              CategoryChip(
                                label: option.label,
                                selected:
                                    _color == option.group &&
                                    _size == option.value,
                                onTap: () {
                                  if (option.stock > 0) {
                                    setState(() {
                                      _selectedVariants['_group'] =
                                          option.group;
                                      _selectedVariants['_value'] =
                                          option.value;
                                      if (_quantity > option.stock) {
                                        _quantity = option.stock;
                                      }
                                    });
                                  }
                                },
                              ),
                          ],
                        ),
                        const SizedBox(height: 16),
                      ],
                      const Text(
                        'Quantity',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          _qtyButton(
                            Icons.remove,
                            _quantity > 1
                                ? () => setState(() => _quantity--)
                                : null,
                          ),
                          SizedBox(
                            width: 40,
                            child: Text(
                              '$_quantity',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          _qtyButton(
                            Icons.add,
                            _quantity < 99 &&
                                    _quantity <
                                        (_option(product)?.stock ??
                                            product.stock)
                                ? () => setState(() => _quantity++)
                                : null,
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (product.description != null &&
                          product.description!.isNotEmpty) ...[
                        const Text(
                          'Description',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          product.description!,
                          style: const TextStyle(fontSize: 13, height: 1.5),
                        ),
                        const SizedBox(height: 20),
                      ],
                      _buildSellerSection(product),
                      const SizedBox(height: 20),
                      _buildReviewsSection(product),
                      const SizedBox(height: 20),
                      _buildRelatedSection(),
                      const SizedBox(height: 80),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
      bottomNavigationBar: FutureBuilder<Product?>(
        future: _productFuture,
        builder: (context, snapshot) {
          final product = snapshot.data;
          if (product == null) return const SizedBox.shrink();
          return DecoratedBox(
            decoration: BoxDecoration(
              color: AppColors.white,
              boxShadow: AppShadows.md,
            ),
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Row(
                  children: [
                    Material(
                      color: AppColors.pinkSoft,
                      borderRadius: BorderRadius.circular(10),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(10),
                        onTap: () => _chatNow(product),
                        child: Container(
                          width: 44,
                          height: 44,
                          alignment: Alignment.center,
                          child: MessageIcon(
                            size: 20,
                            color: AppColors.pinkDark,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: SecondaryButton(
                        label: 'Add to Cart',
                        icon: Icons.shopping_cart_outlined,
                        loading: _busy,
                        onPressed: _busy || !product.purchasable
                            ? null
                            : () => _addToCart(product),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: PrimaryButton(
                        label: 'Buy Now',
                        onPressed: _busy || !product.purchasable
                            ? null
                            : () => _buyNow(product),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSellerSection(Product product) {
    return TitledCard(
      title: 'Seller',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: AppColors.pink,
                child: Text(
                  product.sellerName.isNotEmpty
                      ? product.sellerName[0].toUpperCase()
                      : '?',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(product.sellerName, style: AppTypography.productTitle),
                    if (product.sellerLocation != null)
                      Text(
                        product.sellerLocation!,
                        style: AppTypography.caption,
                      ),
                    FutureBuilder<ShopStats>(
                      future: _shopStatsFuture,
                      builder: (context, snap) {
                        final stats = snap.data;
                        if (stats == null) return const SizedBox.shrink();
                        return RatingStars(
                          rating: stats.averageRating,
                          reviewCount: stats.reviewCount,
                          size: 12,
                        );
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          SizedBox(
            width: double.infinity,
            child: SecondaryButton(
              label: 'Visit Shop',
              onPressed: () => _openShop(product),
            ),
          ),
        ],
      ),
    );
  }

  /// "You may also like" — other real active products in the same
  /// category, excluding this one. Hides entirely if there are none.
  Widget _buildRelatedSection() {
    return FutureBuilder<List<Product>>(
      future: _relatedFuture,
      builder: (context, snapshot) {
        final related = snapshot.data;
        if (related == null || related.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeader(title: 'You May Also Like'),
            const SizedBox(height: 12),
            ProductRail(
              products: related,
              onTap: (product) => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ProductDetailScreen(
                    user: widget.user,
                    productId: product.id,
                  ),
                ),
              ),
              onAddToCart: _addToCart,
            ),
          ],
        );
      },
    );
  }

  Widget _buildReviewsSection(Product product) {
    return TitledCard(
      title: 'Reviews',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          FutureBuilder<RatingSummaryData>(
            future: _ratingFuture,
            builder: (context, ratingSnap) {
              return FutureBuilder<List<Review>>(
                future: _reviewsFuture,
                builder: (context, reviewsSnap) {
                  final rating = ratingSnap.data;
                  final reviews = reviewsSnap.data ?? [];
                  final distribution = <int, int>{};
                  for (final r in reviews) {
                    distribution[r.rating] = (distribution[r.rating] ?? 0) + 1;
                  }
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (rating != null && rating.count > 0) ...[
                        RatingSummary(
                          average: rating.average,
                          count: rating.count,
                          distribution: distribution,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                      ],
                      FutureBuilder<String?>(
                        future: _reviewableOrderFuture,
                        builder: (context, eligible) => eligible.data == null
                            ? const SizedBox.shrink()
                            : Column(
                                children: [
                                  SizedBox(
                                    width: double.infinity,
                                    child: SecondaryButton(
                                      label: 'Write a Review',
                                      onPressed: () => _writeReview(product),
                                    ),
                                  ),
                                  const SizedBox(height: AppSpacing.md),
                                ],
                              ),
                      ),
                      if (reviews.isEmpty)
                        Text(
                          'No reviews yet — be the first to review this product.',
                          style: TextStyle(
                            color: AppColors.textMuted,
                            fontSize: 13,
                          ),
                        )
                      else
                        ...reviews.map(
                          (r) => _ReviewTile(
                            review: r,
                            reviewService: _reviewService,
                          ),
                        ),
                    ],
                  );
                },
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _qtyButton(IconData icon, VoidCallback? onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 32,
        height: 32,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: onTap == null
              ? AppColors.border.withValues(alpha: 0.4)
              : AppColors.pinkSoft,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(
          icon,
          size: 16,
          color: onTap == null ? AppColors.textMuted : AppColors.pink,
        ),
      ),
    );
  }
}

class _ReviewTile extends StatelessWidget {
  final Review review;
  final ReviewService reviewService;

  const _ReviewTile({required this.review, required this.reviewService});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 14,
                backgroundColor: AppColors.pinkSoft,
                child: Text(
                  review.reviewerName.isNotEmpty
                      ? review.reviewerName[0].toUpperCase()
                      : '?',
                  style: TextStyle(
                    color: AppColors.pinkDark,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          review.reviewerName,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12.5,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 1,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.successSoft,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            'Verified Purchase',
                            style: TextStyle(
                              fontSize: 8.5,
                              color: AppColors.success,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    RatingStars(
                      rating: review.rating.toDouble(),
                      size: 11,
                      showCount: false,
                    ),
                  ],
                ),
              ),
              Text(_formatDate(review.createdAt), style: AppTypography.caption),
            ],
          ),
          if (review.comment != null && review.comment!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(review.comment!, style: AppTypography.body),
          ],
          if (review.hasMedia) ...[
            const SizedBox(height: 8),
            _ReviewMediaThumb(review: review, reviewService: reviewService),
          ],
          if (review.variantLabel?.isNotEmpty == true)
            Text(review.variantLabel!),
          if (review.photos.length > 1)
            Wrap(
              spacing: 8,
              children: [
                for (final path in review.photos.skip(1))
                  SizedBox(
                    width: 70,
                    height: 70,
                    child: ProductImage(imageUrl: reviewService.mediaUrl(path)),
                  ),
              ],
            ),
          if (review.sellerReply != null && review.sellerReply!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.pageBackground,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'Seller reply: ${review.sellerReply}',
                style: AppTypography.caption,
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _formatDate(DateTime date) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }
}

/// The real photo/video a reviewer attached as proof (the same attachment
/// in-app; a video opens in the device's own player via `url_launcher`,
/// since this app doesn't otherwise carry a video-playback dependency.
class _ReviewMediaThumb extends StatelessWidget {
  final Review review;
  final ReviewService reviewService;

  const _ReviewMediaThumb({required this.review, required this.reviewService});

  @override
  Widget build(BuildContext context) {
    final url = reviewService.mediaUrl(review.mediaPath!);
    return GestureDetector(
      onTap: () => review.isVideo ? _openVideo(url) : _openImage(context, url),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          width: 64,
          height: 64,
          child: review.isVideo
              ? Container(
                  color: AppColors.textPrimary,
                  alignment: Alignment.center,
                  child: const Icon(
                    Icons.play_circle_fill,
                    color: Colors.white,
                    size: 28,
                  ),
                )
              : ProductImage(imageUrl: url, fit: BoxFit.cover),
        ),
      ),
    );
  }

  Future<void> _openVideo(String url) async {
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  void _openImage(BuildContext context, String url) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.transparent,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: ProductImage(imageUrl: url, fit: BoxFit.contain),
        ),
      ),
    );
  }
}
