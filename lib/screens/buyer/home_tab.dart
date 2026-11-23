import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/app_user.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../models/shop_summary.dart';
import '../../services/shop_service.dart';
import '../../widgets/shop_discovery_card.dart';
import 'shop_screen.dart';
import '../../services/cart_service.dart';
import '../../services/category_service.dart';
import '../../services/conversation_service.dart';
import '../../services/product_service.dart';
import '../../services/wishlist_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/brand_wordmark.dart';
import '../../widgets/storefront_hero.dart';
import '../../widgets/storefront_categories.dart';
import '../../widgets/flash_sale_banner.dart';
import 'deals_screen.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/storefront_header_action.dart';
import '../../widgets/product_grid.dart';
import '../../widgets/section_header.dart';
import 'website_content_screen.dart';
import 'all_categories_screen.dart';
import 'cart_tab.dart';
import 'category_products_screen.dart';
import 'messages_tab.dart';
import 'product_detail_screen.dart';
import 'search_results_screen.dart';
import 'visual_search_results_screen.dart';

class BuyerHomeTab extends StatefulWidget {
  final AppUser user;

  const BuyerHomeTab({super.key, required this.user});

  @override
  State<BuyerHomeTab> createState() => _BuyerHomeTabState();
}

class _BuyerHomeTabState extends State<BuyerHomeTab> {
  late final AppUser _user = widget.user;

  final _categoryService = CategoryService();
  final _productService = ProductService();
  final _cartService = CartService();
  final _wishlistService = WishlistService();
  final _shopService = ShopService();
  late Future<List<ShopSummary>> _shopsFuture;
  late final ConversationService _conversationService =
      BackendConversationService(myUserId: _user.id);

  late Future<List<Category>> _categoriesFuture;
  late Future<List<Product>> _productsFuture;
  late Future<int> _cartCountFuture;
  late Future<int> _unreadMessagesFuture;
  final _productsKey = GlobalKey();
  Set<String> _wishlistedIds = {};

  @override
  void initState() {
    super.initState();
    _categoriesFuture = _categoryService.fetchAll();
    _shopsFuture = _shopService.discoverShops();
    _productsFuture = _productService.fetchActiveProducts(limit: 30);
    _cartCountFuture = _fetchCartCount();
    _unreadMessagesFuture = _fetchUnreadMessages();
    _loadWishlistIds();
  }

  Future<int> _fetchCartCount() async {
    final items = await _cartService.fetchCart(_user.id);
    return items.length;
  }

  Future<int> _fetchUnreadMessages() async {
    final conversations = await _conversationService.fetchConversations(
      _user.id.toString(),
    );
    return conversations.fold<int>(0, (sum, c) => sum + c.unreadCount);
  }

  Future<void> _loadWishlistIds() async {
    final items = await _wishlistService.fetchWishlist(_user.id);
    if (!mounted) return;
    setState(() => _wishlistedIds = items.map((i) => i.productId).toSet());
  }

  Future<void> _refresh() async {
    setState(() {
      _categoriesFuture = _categoryService.fetchAll();
      _shopsFuture = _shopService.discoverShops();
      _productsFuture = _productService.fetchActiveProducts(limit: 30);
      _cartCountFuture = _fetchCartCount();
      _unreadMessagesFuture = _fetchUnreadMessages();
    });
    await Future.wait([
      _categoriesFuture,
      _shopsFuture,
      _productsFuture,
      _cartCountFuture,
      _unreadMessagesFuture,
    ]);
    _loadWishlistIds();
  }

  Future<void> _addToCart(Product product) async {
    if (product.hasVariations) {
      _openProduct(product);
      return;
    }
    try {
      await _cartService.addToCart(
        buyerId: _user.id,
        product: product,
        quantity: 1,
      );
      if (!mounted) return;
      setState(() => _cartCountFuture = _fetchCartCount());
      AppSnackBar.success(context, '${product.name} added to cart');
    } catch (e) {
      debugPrint('addToCart failed: $e');
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not add to cart: ${friendlyError(e)}');
    }
  }

  Future<void> _toggleWishlist(Product product, bool wishlisted) async {
    setState(() {
      if (wishlisted) {
        _wishlistedIds.add(product.id);
      } else {
        _wishlistedIds.remove(product.id);
      }
    });
    try {
      if (wishlisted) {
        await _wishlistService.add(_user.id, product);
      } else {
        await _wishlistService.remove(_user.id, product.id);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        if (wishlisted) {
          _wishlistedIds.remove(product.id);
        } else {
          _wishlistedIds.add(product.id);
        }
      });
      AppSnackBar.error(
        context,
        'Could not update wishlist: ${friendlyError(e)}',
      );
    }
  }

  void _openProduct(Product product) {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) =>
                ProductDetailScreen(user: _user, productId: product.id),
          ),
        )
        .then((_) => _loadWishlistIds());
  }

  void _openAllCategories() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => AllCategoriesScreen(user: _user)));
  }

  Future<void> _visualSearch() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(sheetContext, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(sheetContext, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final picked = await ImagePicker().pickImage(
      source: source,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            VisualSearchResultsScreen(user: _user, photo: File(picked.path)),
      ),
    );
  }

  void _openCart() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => CartTab(user: _user)))
        .then((_) => setState(() => _cartCountFuture = _fetchCartCount()));
  }

  void _openMessages() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => MessagesTab(user: _user)))
        .then(
          (_) => setState(() => _unreadMessagesFuture = _fetchUnreadMessages()),
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: CustomScrollView(
            slivers: [
              _buildHeader(),
              SliverToBoxAdapter(
                child: StorefrontHero(
                  onBrowse: () {
                    final target = _productsKey.currentContext;
                    if (target != null) {
                      Scrollable.ensureVisible(
                        target,
                        duration: const Duration(milliseconds: 300),
                      );
                    }
                  },
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildCategoriesSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildPromoBanner()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildShopsSection()),
              ),
              SliverPadding(
                key: _productsKey,
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: SectionHeader(
                    title: 'All Products',
                    onSeeAll: _browseProducts,
                  ),
                ),
              ),
              SliverToBoxAdapter(child: _buildProductsSection()),
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverToBoxAdapter(
                  child: OutlinedButton.icon(
                    onPressed: _browseProducts,
                    icon: const Icon(Icons.tune),
                    label: const Text('Browse all products'),
                  ),
                ),
              ),
              const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
            ],
          ),
        ),
      ),
    );
  }

  void _browseProducts() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SearchResultsScreen(user: _user, query: ''),
      ),
    );
  }

  Widget _buildShopsSection() => FutureBuilder<List<ShopSummary>>(
    future: _shopsFuture,
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return TextButton.icon(
          onPressed: () => setState(() {
            _shopsFuture = _shopService.discoverShops();
          }),
          icon: const Icon(Icons.refresh),
          label: const Text('Could not load shops. Retry'),
        );
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final shops = snapshot.data!;
      if (shops.isEmpty) return const SizedBox.shrink();
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SectionHeader(title: 'Discover Shops'),
          const SizedBox(height: 12),
          SizedBox(
            height:
                210 * MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0),
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: shops.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final shop = shops[index];
                return ShopDiscoveryCard(
                  shop: shop,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) =>
                          ShopScreen(user: _user, sellerId: shop.sellerId),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      );
    },
  );

  Widget _buildHeader() {
    return SliverAppBar(
      pinned: true,
      floating: true,
      backgroundColor: AppColors.white,
      titleSpacing: 16,
      title: const FittedBox(fit: BoxFit.scaleDown, child: BrandWordmark()),
      actions: [
        IconButton(
          tooltip: 'Deals',
          icon: const Icon(Icons.local_offer_outlined),
          onPressed: () => Navigator.of(
            context,
          ).push(MaterialPageRoute(builder: (_) => DealsScreen(user: _user))),
        ),
        IconButton(
          style: StorefrontHeaderAction.buttonStyle,
          tooltip: 'Announcements',
          icon: const Icon(Icons.campaign_outlined),
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const WebsiteContentScreen(kind: 'announcements'),
            ),
          ),
        ),
        _HeaderIconButton(
          icon: Icons.shopping_cart_outlined,
          onTap: _openCart,
          countFuture: _cartCountFuture,
        ),
        const SizedBox(width: 4),
        _HeaderIconButton(
          iconWidget: MessageIcon(size: 20, color: AppColors.textPrimary),
          onTap: _openMessages,
          countFuture: _unreadMessagesFuture,
        ),
        const SizedBox(width: 8),
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(64),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: AppSearchBar(
            onChanged: (_) {},
            onSubmitted: (query) {
              if (query.trim().isEmpty) return;
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      SearchResultsScreen(user: _user, query: query),
                ),
              );
            },
            onCameraTap: _visualSearch,
          ),
        ),
      ),
    );
  }

  Widget _buildCategoriesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: 'Shop by Category', onSeeAll: _openAllCategories),
        const SizedBox(height: 12),
        FutureBuilder<List<Category>>(
          future: _categoriesFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const SizedBox(
                height: 130,
                child: Center(child: CircularProgressIndicator()),
              );
            }
            if (snapshot.hasError) {
              return Text(
                'Could not load categories.',
                style: AppTypography.caption,
              );
            }
            final categories = snapshot.data ?? [];
            if (categories.isEmpty) {
              return Text('No categories yet.', style: AppTypography.caption);
            }
            return StorefrontCategories(
              categories: categories,
              onSelected: (category) => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      CategoryProductsScreen(user: _user, category: category),
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  /// Real "Flash Sale" — a swipeable banner, one real discounted product
  /// per page (real `discount_price`). No fabricated countdown or
  /// stock-scarcity bar (nothing backs either). Falls back to the plain
  /// "find something you'll love" banner when nothing's on sale right now.
  Widget _buildPromoBanner() {
    return FutureBuilder<List<Product>>(
      future: _productsFuture,
      builder: (context, snapshot) {
        final deals = (snapshot.data ?? const <Product>[])
            .where((p) => p.hasDiscount)
            .take(10)
            .toList();
        if (deals.isNotEmpty) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SectionHeader(
                title: 'Deals',
                onSeeAll: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => DealsScreen(user: _user)),
                ),
              ),
              const SizedBox(height: 10),
              FlashSaleBanner(products: deals, onTap: _openProduct),
            ],
          );
        }
        return Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: AppColors.pinkSoft,
            borderRadius: BorderRadius.circular(AppRadius.card),
            border: Border.all(color: AppColors.pinkBorder),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: AppColors.pinkGradient),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.auto_awesome,
                  color: AppColors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Find something you\'ll love',
                      style: AppTypography.sectionTitle.copyWith(fontSize: 15),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Discover products from local sellers — new arrivals added all the time.',
                      style: AppTypography.caption,
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildProductsSection() {
    return FutureBuilder<List<Product>>(
      future: _productsFuture,
      builder: (context, snapshot) {
        return ProductGrid(
          products: snapshot.data,
          error: snapshot.error,
          onTap: _openProduct,
          onAddToCart: _addToCart,
          wishlistedIds: _wishlistedIds,
          onWishlistChanged: _toggleWishlist,
          emptyMessage: 'No products available yet. Check back soon!',
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
        );
      },
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  final IconData? icon;
  final Widget? iconWidget;
  final VoidCallback onTap;
  final Future<int>? countFuture;

  const _HeaderIconButton({
    this.icon,
    this.iconWidget,
    required this.onTap,
    this.countFuture,
  }) : assert(icon != null || iconWidget != null);

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFE4E4E4), width: 1.3),
              ),
              child:
                  iconWidget ??
                  Icon(icon, size: 22, color: const Color(0xFF8D7A85)),
            ),
            if (countFuture != null)
              Positioned(
                top: -2,
                right: -2,
                child: FutureBuilder<int>(
                  future: countFuture,
                  builder: (context, snapshot) {
                    final count = snapshot.data ?? 0;
                    if (count == 0) return const SizedBox.shrink();
                    return Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 1,
                      ),
                      constraints: const BoxConstraints(minWidth: 15),
                      decoration: BoxDecoration(
                        color: AppColors.danger,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        count > 9 ? '9+' : '$count',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
