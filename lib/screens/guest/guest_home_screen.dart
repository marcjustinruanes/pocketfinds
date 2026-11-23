import 'package:flutter/material.dart';

import '../../models/category.dart';
import '../../models/product.dart';
import '../../models/shop_summary.dart';
import '../../services/category_service.dart';
import '../../services/product_service.dart';
import '../../services/shop_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../widgets/app_bottom_nav.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/brand_wordmark.dart';
import '../../widgets/storefront_hero.dart';
import '../../widgets/storefront_categories.dart';
import '../../widgets/flash_sale_banner.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/storefront_header_action.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/product_grid.dart';
import '../../widgets/product_rail.dart';
import '../../widgets/section_header.dart';
import '../../widgets/shop_discovery_card.dart';

class GuestHomeScreen extends StatefulWidget {
  /// Shown once, right after the first frame — used when the app routes
  /// here after signing someone out (e.g. a pending/rejected account, or an
  /// unexpected error resolving their session) so they know why.
  final String? flashMessage;

  const GuestHomeScreen({super.key, this.flashMessage});

  @override
  State<GuestHomeScreen> createState() => _GuestHomeScreenState();
}

class _GuestHomeScreenState extends State<GuestHomeScreen> {
  final _categoryService = CategoryService();
  final _productService = ProductService();
  final _shopService = ShopService();
  final _categoriesSectionKey = GlobalKey();
  final _productsSectionKey = GlobalKey();
  final _scrollController = ScrollController();
  int _searchReset = 0;

  late Future<List<Category>> _categoriesFuture;
  late Future<List<Product>> _productsFuture;
  late Future<List<ShopSummary>> _shopsFuture;

  String _searchQuery = '';
  String _sort = 'newest';
  bool _inStockOnly = false;
  int? _selectedCategoryId;

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _categoriesFuture = _categoryService.fetchAll();
    _shopsFuture = _shopService.discoverShops();
    if (widget.flashMessage != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        AppSnackBar.info(context, widget.flashMessage!);
      });
    }
    // Fetched once, shared by the New Arrivals rail, the Deals rail, and
    // the All Products grid below — no separate round trip per section.
    _productsFuture = _productService.fetchActiveProducts(limit: 40);
  }

  Future<void> _refresh() async {
    setState(() {
      _categoriesFuture = _categoryService.fetchAll();
      _shopsFuture = _shopService.discoverShops();
      _productsFuture = _productService.fetchActiveProducts(
        limit: 40,
        categoryId: _selectedCategoryId,
      );
    });
    await Future.wait([_categoriesFuture, _productsFuture, _shopsFuture]);
  }

  void _selectCategory(int? categoryId) {
    setState(() {
      _selectedCategoryId = _selectedCategoryId == categoryId
          ? null
          : categoryId;
      _productsFuture = _productService.fetchActiveProducts(
        limit: 40,
        categoryId: _selectedCategoryId,
      );
    });
  }

  void _selectCategoryAndScroll(int categoryId) {
    showGuestSignInSheet(context);
  }

  void _scrollToProducts() => showGuestSignInSheet(context);

  void _openProduct(Product product) {
    showGuestSignInSheet(context);
  }

  void _openShop(int sellerId) {
    showGuestSignInSheet(context);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: CustomScrollView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              _buildHeader(),
              SliverToBoxAdapter(child: _buildHero()),
              SliverPadding(
                key: _categoriesSectionKey,
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildCategoriesSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildNewArrivalsSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildDealsSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildDiscoverShopsSection()),
              ),
              SliverPadding(
                key: _productsSectionKey,
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: SectionHeader(
                    title: _selectedCategoryId == null
                        ? 'All Products'
                        : 'Products',
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverToBoxAdapter(child: _buildCatalogControls()),
              ),
              SliverToBoxAdapter(child: _buildProductsGrid()),
              const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
            ],
          ),
        ),
      ),
      bottomNavigationBar: _buildBottomNav(context),
    );
  }

  Widget _buildHeader() {
    return SliverAppBar(
      pinned: true,
      floating: true,
      backgroundColor: AppColors.white,
      titleSpacing: 16,
      title: const FittedBox(fit: BoxFit.scaleDown, child: BrandWordmark()),
      actions: [
        StorefrontHeaderAction(
          icon: const Icon(Icons.shopping_cart_outlined, size: 22),
          tooltip: 'Cart',
          onPressed: () => showGuestSignInSheet(context),
        ),
        StorefrontHeaderAction(
          icon: const MessageIcon(size: 22, color: Color(0xFF8D7A85)),
          tooltip: 'Messages',
          onPressed: () => showGuestSignInSheet(context),
        ),
        const SizedBox(width: 4),
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(64),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: AppSearchBar(
            readOnly: true,
            onTap: () => showGuestSignInSheet(context),
            key: ValueKey(_searchReset),
            onChanged: (value) => setState(() => _searchQuery = value),
            onSubmitted: (_) {
              FocusScope.of(context).unfocus();
              _scrollToProducts();
            },
            onCameraTap: () => showGuestSignInSheet(context),
          ),
        ),
      ),
    );
  }

  Widget _buildHero() => StorefrontHero(onBrowse: _scrollToProducts);

  Widget _buildCategoriesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(
          title: 'Shop by Category',
          onSeeAll: () => showGuestSignInSheet(context),
        ),
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
              return TextButton.icon(
                onPressed: () => setState(() {
                  _categoriesFuture = _categoryService.fetchAll();
                }),
                icon: const Icon(Icons.refresh),
                label: const Text('Could not load categories. Retry'),
              );
            }
            final categories = snapshot.data ?? [];
            if (categories.isEmpty) {
              return Text('No categories yet.', style: AppTypography.caption);
            }
            return StorefrontCategories(
              categories: categories,
              selectedId: _selectedCategoryId,
              onSelected: (category) => _selectCategoryAndScroll(category.id),
            );
          },
        ),
      ],
    );
  }

  Widget _buildNewArrivalsSection() {
    return FutureBuilder<List<Product>>(
      future: _productsFuture,
      builder: (context, snapshot) {
        final products = snapshot.data;
        if (products == null || products.isEmpty) {
          return const SizedBox.shrink();
        }
        // Already ordered `created_at desc` by ProductService — the first
        // handful are the real newest listings, matching `Product.isNew`.
        final newest = products.take(10).toList();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeader(title: 'New Arrivals'),
            const SizedBox(height: 12),
            ProductRail(
              products: newest,
              onTap: _openProduct,
              onAddToCart: (_) => showGuestSignInSheet(context),
              wishlistedIds: const {},
              onWishlistChanged: (_, _) async => showGuestSignInSheet(context),
            ),
          ],
        );
      },
    );
  }

  Widget _buildDealsSection() {
    return FutureBuilder<List<Product>>(
      future: _productsFuture,
      builder: (context, snapshot) {
        final products = snapshot.data;
        if (products == null) return const SizedBox.shrink();
        final deals = products.where((p) => p.hasDiscount).take(10).toList();
        // No fabricated countdown timer or stock-scarcity bar here — nothing
        // in the backend backs either (no `deal_ends_at`, no real
        // original-stock baseline). Just real products with a real
        // discount_price, in a swipeable "Flash Sale" banner. Section
        // hides entirely when there are none.
        if (deals.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeader(title: 'Flash Sale'),
            const SizedBox(height: 12),
            FlashSaleBanner(products: deals, onTap: _openProduct),
            const SizedBox(height: AppSpacing.lg),
            ProductRail(
              products: deals,
              onTap: _openProduct,
              onAddToCart: (_) => showGuestSignInSheet(context),
              wishlistedIds: const {},
              onWishlistChanged: (_, _) async => showGuestSignInSheet(context),
            ),
          ],
        );
      },
    );
  }

  Widget _buildDiscoverShopsSection() {
    return FutureBuilder<List<ShopSummary>>(
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
        final shops = snapshot.data;
        if (shops == null || shops.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeader(title: 'Discover Shops'),
            const SizedBox(height: 12),
            // ShopDiscoveryCard's natural height (avatar + name + a
            // location line + rating + product count, inside 12px padding)
            // runs to ~155px — 152 was too tight and threw a small
            // "RenderFlex overflowed on the bottom" for any shop with a
            // location set.
            SizedBox(
              height:
                  210 *
                  MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: shops.length,
                separatorBuilder: (_, _) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final shop = shops[index];
                  return ShopDiscoveryCard(
                    shop: shop,
                    onTap: () => _openShop(shop.sellerId),
                  );
                },
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildCatalogControls() => GestureDetector(
    onTap: () => showGuestSignInSheet(context),
    child: AbsorbPointer(
      child: Wrap(
        spacing: 12,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          FilterChip(
            label: const Text('In stock'),
            selected: _inStockOnly,
            onSelected: (value) => setState(() => _inStockOnly = value),
          ),
          DropdownButton<String>(
            value: _sort,
            hint: const Text('Sort products'),
            items: const [
              DropdownMenuItem(value: 'newest', child: Text('Newest first')),
              DropdownMenuItem(
                value: 'priceAsc',
                child: Text('Price: low to high'),
              ),
              DropdownMenuItem(
                value: 'priceDesc',
                child: Text('Price: high to low'),
              ),
            ],
            onChanged: (value) => setState(() => _sort = value ?? 'newest'),
          ),
          if (_selectedCategoryId != null)
            ActionChip(
              label: const Text('Clear category'),
              onPressed: () => _selectCategory(_selectedCategoryId),
            ),
          if (_searchQuery.isNotEmpty ||
              _inStockOnly ||
              _sort != 'newest' ||
              _selectedCategoryId != null)
            TextButton.icon(
              icon: const Icon(Icons.filter_alt_off_outlined),
              label: const Text('Reset filters'),
              onPressed: () {
                final reload = _selectedCategoryId != null;
                setState(() {
                  _searchQuery = '';
                  _searchReset++;
                  _inStockOnly = false;
                  _sort = 'newest';
                  _selectedCategoryId = null;
                  if (reload) {
                    _productsFuture = _productService.fetchActiveProducts(
                      limit: 40,
                    );
                  }
                });
              },
            ),
        ],
      ),
    ),
  );

  Widget _buildProductsGrid() {
    return FutureBuilder<List<Product>>(
      future: _productsFuture,
      builder: (context, snapshot) {
        final products = snapshot.data
            ?.where(
              (p) => '${p.name} ${p.categoryName}'.toLowerCase().contains(
                _searchQuery.trim().toLowerCase(),
              ),
            )
            .toList();
        final filtered = products
            ?.where((p) => !_inStockOnly || p.stock > 0)
            .toList();
        if (filtered != null && _sort != 'newest') {
          filtered.sort((a, b) {
            final result = (a.hasDiscount ? a.discountPrice! : a.price)
                .compareTo(b.hasDiscount ? b.discountPrice! : b.price);
            return _sort == 'priceAsc' ? result : -result;
          });
        }
        return ProductGrid(
          products: filtered,
          error: snapshot.error,
          emptyMessage: 'No products match your filters.',
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          onTap: _openProduct,
          onAddToCart: (_) => showGuestSignInSheet(context),
          wishlistedIds: const {},
          onWishlistChanged: (_, _) async => showGuestSignInSheet(context),
        );
      },
    );
  }

  Widget _buildBottomNav(BuildContext context) {
    return AppBottomNav(
      currentIndex: 0,
      onTap: (_) => showGuestSignInSheet(context),
      items: const [
        BottomNavigationBarItem(
          icon: Icon(Icons.home_outlined),
          activeIcon: Icon(Icons.home),
          label: 'Home',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.grid_view_outlined),
          activeIcon: Icon(Icons.grid_view),
          label: 'Categories',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.favorite_border),
          activeIcon: Icon(Icons.favorite),
          label: 'Favorites',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.person_outline),
          activeIcon: Icon(Icons.person),
          label: 'Profile',
        ),
      ],
    );
  }
}
