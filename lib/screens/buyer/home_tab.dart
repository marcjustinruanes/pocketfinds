import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/category_service.dart';
import '../../services/product_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../utils/category_icons.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/category_chip.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/product_card.dart';
import '../../widgets/section_header.dart';
import 'category_products_screen.dart';
import 'product_detail_screen.dart';
import 'search_results_screen.dart';

/// Mobile translation of `resources/views/buyer/dashboard.blade.php` — the
/// signed-in buyer's home tab. Same product/category data source as Guest
/// Home, but tapping a product/category navigates into real buyer screens
/// and Cart/Buy Now actually add to the buyer's Supabase-backed cart.
class BuyerHomeTab extends StatefulWidget {
  final AppUser user;

  const BuyerHomeTab({super.key, required this.user});

  @override
  State<BuyerHomeTab> createState() => _BuyerHomeTabState();
}

class _BuyerHomeTabState extends State<BuyerHomeTab> {
  final _categoryService = CategoryService();
  final _productService = ProductService();
  final _cartService = CartService();

  late Future<List<Category>> _categoriesFuture;
  late Future<List<Product>> _productsFuture;

  @override
  void initState() {
    super.initState();
    _categoriesFuture = _categoryService.fetchAll();
    _productsFuture = _productService.fetchActiveProducts(limit: 12);
  }

  Future<void> _refresh() async {
    setState(() {
      _categoriesFuture = _categoryService.fetchAll();
      _productsFuture = _productService.fetchActiveProducts(limit: 12);
    });
    await Future.wait([_categoriesFuture, _productsFuture]);
  }

  Future<void> _addToCart(Product product) async {
    try {
      await _cartService.addToCart(
        buyerId: widget.user.id,
        productId: product.id,
        quantity: 1,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${product.name} added to cart')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not add to cart: ${friendlyError(e)}')));
    }
  }

  void _openProduct(Product product) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            ProductDetailScreen(user: widget.user, productId: product.id),
      ),
    );
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
                sliver: SliverToBoxAdapter(child: _buildWelcomeBanner()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                sliver: SliverToBoxAdapter(child: _buildCategoriesSection()),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: SectionHeader(title: 'Featured Products'),
                ),
              ),
              _buildProductsGrid(),
              const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return SliverAppBar(
      pinned: true,
      floating: true,
      backgroundColor: AppColors.white,
      titleSpacing: 16,
      title: Row(
        children: [
          const Icon(Icons.storefront, color: AppColors.pink),
          const SizedBox(width: 8),
          const Text(
            'PocketFinds',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
          ),
        ],
      ),
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
                      SearchResultsScreen(user: widget.user, query: query),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildWelcomeBanner() {
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : hour < 18
            ? 'Good afternoon'
            : 'Good evening';
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.xl),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.xl),
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
              right: -50,
              top: -50,
              child: Container(
                width: 140,
                height: 140,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.10),
                ),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$greeting, ${widget.user.givenNames} 👋',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'What are you looking for today?',
                  style: TextStyle(color: Colors.white70, fontSize: 13),
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
                    selected: false,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => CategoryProductsScreen(
                          user: widget.user,
                          category: category,
                        ),
                      ),
                    ),
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
          final products = snapshot.data ?? [];
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
                  onTap: () => _openProduct(product),
                  onAddToCart: () => _addToCart(product),
                  onBuyNow: () => _openProduct(product),
                );
              },
              childCount: products.length,
            ),
          );
        },
      ),
    );
  }
}
