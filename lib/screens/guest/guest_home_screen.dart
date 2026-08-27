import 'package:flutter/material.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../services/category_service.dart';
import '../../services/product_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../utils/category_icons.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_bottom_nav.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/category_chip.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/product_card.dart';
import '../../widgets/section_header.dart';
import '../buyer/product_detail_screen.dart';

/// Mobile translation of `resources/views/guest/home.blade.php` — the
/// public marketplace home page a guest sees before signing in.
///
/// Real data only: products and categories are both loaded from the
/// existing Supabase `products`/`categories`/`users` tables, nothing is
/// hardcoded.
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
  final _categoriesSectionKey = GlobalKey();

  late Future<List<Category>> _categoriesFuture;
  late Future<List<Product>> _productsFuture;

  String _searchQuery = '';
  int? _selectedCategoryId;

  @override
  void initState() {
    super.initState();
    _categoriesFuture = _categoryService.fetchAll();
    if (widget.flashMessage != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(widget.flashMessage!)));
      });
    }
    _productsFuture = _productService.fetchActiveProducts(limit: 12);
  }

  Future<void> _refresh() async {
    setState(() {
      _categoriesFuture = _categoryService.fetchAll();
      _productsFuture = _productService.fetchActiveProducts(
        limit: 12,
        categoryId: _selectedCategoryId,
      );
    });
    await Future.wait([_categoriesFuture, _productsFuture]);
  }

  void _selectCategory(int? categoryId) {
    setState(() {
      _selectedCategoryId = _selectedCategoryId == categoryId
          ? null
          : categoryId;
      _productsFuture = _productService.fetchActiveProducts(
        limit: 12,
        categoryId: _selectedCategoryId,
      );
    });
  }

  void _scrollToCategories() {
    final context = _categoriesSectionKey.currentContext;
    if (context != null) {
      Scrollable.ensureVisible(
        context,
        duration: const Duration(milliseconds: 300),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: CustomScrollView(
            slivers: [
              _buildHeader(),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildGuestBanner()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildHero()),
              ),
              SliverPadding(
                key: _categoriesSectionKey,
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildCategoriesSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: SectionHeader(
                    title: _selectedCategoryId == null
                        ? 'All Products'
                        : 'Products',
                  ),
                ),
              ),
              _buildProductsGrid(),
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
      title: Row(
        children: const [
          Icon(Icons.storefront, color: AppColors.pink),
          SizedBox(width: 8),
          Text(
            'PocketFinds',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
          ),
        ],
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.favorite_border),
          tooltip: 'Wishlist',
          onPressed: () => showGuestSignInSheet(context),
        ),
        IconButton(
          icon: const Icon(Icons.shopping_cart_outlined),
          tooltip: 'Cart',
          onPressed: () => showGuestSignInSheet(context),
        ),
        const SizedBox(width: 4),
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(64),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: AppSearchBar(
            onChanged: (value) => setState(() => _searchQuery = value),
          ),
        ),
      ),
    );
  }

  Widget _buildGuestBanner() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.pinkSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  "You're browsing as a guest.",
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                ),
                Text(
                  'Sign in to save items, checkout, and track orders.',
                  style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => showGuestSignInSheet(context),
            child: const Text('Sign in →'),
          ),
        ],
      ),
    );
  }

  Widget _buildHero() {
    // Matches the website's hero exactly: a deep-to-bright pink diagonal
    // gradient (`linear-gradient(120deg,#4a1836,#a52b6c 55%,#ef7eb5)`) with
    // a soft decorative circle in the corner.
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.xl),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF4A1836), Color(0xFFA52B6C), Color(0xFFEF7EB5)],
            stops: [0, 0.55, 1],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              right: -60,
              top: -60,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.10),
                ),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'GUEST SHOPPING',
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.4,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  "Discover products\nyou'll love.",
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    height: 1.15,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Browse products, explore categories, and find something worth adding to your cart.',
                  style: TextStyle(color: Colors.white70, fontSize: 13, height: 1.4),
                ),
                const SizedBox(height: AppSpacing.lg),
                ElevatedButton(
                  onPressed: _scrollToCategories,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: const Color(0xFFA52B6C),
                  ),
                  child: const Text('Explore products →'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoriesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Browse Categories'),
        const SizedBox(height: 12),
        SizedBox(
          height: 42,
          child: FutureBuilder<List<Category>>(
            future: _categoriesFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(
                  child: SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                );
              }
              if (snapshot.hasError) {
                return const Text(
                  'Could not load categories.',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 12),
                );
              }
              final categories = snapshot.data ?? [];
              if (categories.isEmpty) {
                return const Text(
                  'No categories yet.',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 12),
                );
              }
              return ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: categories.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final category = categories[index];
                  return CategoryChip(
                    label: category.name,
                    icon: iconForCategory(category.name),
                    selected: _selectedCategoryId == category.id,
                    onTap: () => _selectCategory(category.id),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildProductsGrid() {
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: FutureBuilder<List<Product>>(
        future: _productsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 60),
                child: Center(child: CircularProgressIndicator()),
              ),
            );
          }
          if (snapshot.hasError) {
            return SliverToBoxAdapter(
              child: ErrorState(
                message: 'Could not load products.',
                error: friendlyError(snapshot.error!),
              ),
            );
          }

          final products = (snapshot.data ?? [])
              .where(
                (p) => p.name.toLowerCase().contains(
                  _searchQuery.trim().toLowerCase(),
                ),
              )
              .toList();

          if (products.isEmpty) {
            return const SliverToBoxAdapter(
              child: EmptyState(
                icon: Icons.inventory_2_outlined,
                title: 'No products available yet. Check back soon!',
              ),
            );
          }

          return SliverGrid(
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.52,
            ),
            delegate: SliverChildBuilderDelegate(
              (context, index) {
                final product = products[index];
                return ProductCard(
                  product: product,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ProductDetailScreen(
                        user: null,
                        productId: product.id,
                      ),
                    ),
                  ),
                  onAddToCart: () => showGuestSignInSheet(context),
                  onBuyNow: () => showGuestSignInSheet(context),
                );
              },
              childCount: products.length,
            ),
          );
        },
      ),
    );
  }

  Widget _buildBottomNav(BuildContext context) {
    return AppBottomNav(
      currentIndex: 0,
      onTap: (index) {
        switch (index) {
          case 1:
            _scrollToCategories();
            break;
          case 2:
          case 3:
            showGuestSignInSheet(context);
            break;
        }
      },
      items: const [
        BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
        BottomNavigationBarItem(icon: Icon(Icons.grid_view), label: 'Categories'),
        BottomNavigationBarItem(
          icon: Icon(Icons.shopping_cart_outlined),
          label: 'Cart',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.person_outline),
          label: 'Account',
        ),
      ],
    );
  }
}
