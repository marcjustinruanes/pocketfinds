import '../../services/backend_service.dart';

import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../models/product.dart';
import '../../models/shop_profile.dart';
import '../../models/shop_stats.dart';
import '../../services/cart_service.dart';
import '../../services/conversation_service.dart';
import '../../services/shop_service.dart';
import '../../services/wishlist_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/product_grid.dart';
import 'conversation_detail_screen.dart';
import 'product_detail_screen.dart';

import 'package:intl/intl.dart';

enum _SortOrder { popular, newest, priceLowHigh, priceHighLow }

const _sortLabels = {
  _SortOrder.popular: 'Popular',
  _SortOrder.newest: 'Newest',
  _SortOrder.priceLowHigh: 'Price: Low to High',
  _SortOrder.priceHighLow: 'Price: High to Low',
};

/// A seller's storefront — real profile (`ShopService.fetchProfile`), real
/// stats (`get_shop_stats()`), real active products (filterable/sortable
/// client-side), and real FOLLOW (`shop_follows`) + MESSAGE (the same
/// conversation flow used elsewhere, seller as counterpart).
class ShopScreen extends StatefulWidget {
  final AppUser? user;
  final int sellerId;

  const ShopScreen({super.key, required this.user, required this.sellerId});

  @override
  State<ShopScreen> createState() => _ShopScreenState();
}

class _ShopScreenState extends State<ShopScreen> {
  final _shopService = ShopService();
  final _cartService = CartService();
  final _wishlistService = WishlistService();

  late Future<ShopProfile?> _profileFuture;
  late Future<ShopStats> _statsFuture;
  late Future<List<Product>> _productsFuture;
  bool _following = false;
  bool _followBusy = false;
  Set<String> _wishlistedIds = {};
  _SortOrder _sort = _SortOrder.popular;
  int? _storeCategory;
  late final _storeCategories = BackendService.client.request(
    'catalog/store-categories',
    {'seller_id': widget.sellerId},
  );
  int _page = 1, _generation = 0;
  bool _more = false, _loadingMore = false;
  List<Product> _loadedProducts = [];
  Future<List<Product>> _fetchProducts({bool next = false}) async {
    final generation = ++_generation;
    final data = await BackendService.client.request('catalog/search', {
      'seller_id': widget.sellerId,
      'store_category_id': _storeCategory,
      'sort': switch (_sort) {
        _SortOrder.popular => 'best_selling',
        _SortOrder.newest => 'latest',
        _SortOrder.priceLowHigh => 'price_low',
        _SortOrder.priceHighLow => 'price_high',
      },
      'page': next ? _page + 1 : 1,
      'limit': 24,
    });
    final products = (data['items'] as List)
        .map(
          (row) => Product.fromRow(
            row: Map<String, dynamic>.from(row as Map),
            seller: Map<String, dynamic>.from(row),
          ),
        )
        .toList();
    if (!mounted || generation != _generation) return _loadedProducts;
    _page = data['page'] as int;
    _more = data['has_more'] == true;
    _loadedProducts = next ? [..._loadedProducts, ...products] : products;
    return _loadedProducts;
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      final products = await _fetchProducts(next: true);
      if (mounted) setState(() => _productsFuture = Future.value(products));
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _profileFuture = _shopService.fetchProfile(widget.sellerId);
    _statsFuture = _shopService.fetchStats(widget.sellerId);
    _productsFuture = _fetchProducts();
    _loadFollowState();
    _loadWishlistIds();
  }

  void _retryProfile() {
    setState(() {
      _profileFuture = _shopService.fetchProfile(widget.sellerId);
      _statsFuture = _shopService.fetchStats(widget.sellerId);
      _productsFuture = _fetchProducts();
    });
  }

  Future<void> _loadFollowState() async {
    final user = widget.user;
    if (user == null) return;
    final following = await _shopService.isFollowing(user.id, widget.sellerId);
    if (mounted) setState(() => _following = following);
  }

  Future<void> _loadWishlistIds() async {
    final user = widget.user;
    if (user == null) return;
    final items = await _wishlistService.fetchWishlist(user.id);
    if (mounted) {
      setState(() => _wishlistedIds = items.map((i) => i.productId).toSet());
    }
  }

  Future<void> _toggleFollow() async {
    if (_followBusy) return;
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    final next = !_following;
    setState(() {
      _following = next;
      _followBusy = true;
    });
    try {
      if (next) {
        await _shopService.follow(user.id, widget.sellerId);
      } else {
        await _shopService.unfollow(user.id, widget.sellerId);
      }
      if (mounted) {
        setState(() => _statsFuture = _shopService.fetchStats(widget.sellerId));
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _following = !next);
      AppSnackBar.error(context, 'Could not update: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _followBusy = false);
    }
  }

  void _message() {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    final profile = _profileFuture;
    profile.then((p) {
      if (p == null || !mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ConversationDetailScreen(
            conversation: ConversationSummary(
              id: widget.sellerId.toString(),
              participantName: p.name,
              participantAvatarInitial: p.name.isNotEmpty
                  ? p.name[0].toUpperCase()
                  : '?',
              lastMessagePreview: '',
              lastMessageAt: DateTime.now(),
            ),
            service: BackendConversationService(myUserId: user.id),
            user: user,
          ),
        ),
      );
    });
  }

  Future<void> _addToCart(Product product) async {
    if (product.hasVariations) {
      _openProduct(product);
      return;
    }
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    try {
      await _cartService.addToCart(
        buyerId: user.id,
        product: product,
        quantity: 1,
      );
      if (!mounted) return;
      AppSnackBar.success(context, '${product.name} added to cart');
    } catch (e) {
      debugPrint('addToCart failed: $e');
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not add to cart: ${friendlyError(e)}');
    }
  }

  Future<void> _toggleWishlist(Product product, bool wishlisted) async {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    setState(() {
      if (wishlisted) {
        _wishlistedIds.add(product.id);
      } else {
        _wishlistedIds.remove(product.id);
      }
    });
    try {
      if (wishlisted) {
        await _wishlistService.add(user.id, product);
      } else {
        await _wishlistService.remove(user.id, product.id);
      }
    } catch (_) {
      // Non-fatal — heart just won't stay toggled; already reflected optimistically.
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
      backgroundColor: AppColors.pageBackground,
      body: FutureBuilder<ShopProfile?>(
        future: _profileFuture,
        builder: (context, profileSnap) {
          if (profileSnap.connectionState == ConnectionState.waiting) {
            return Center(
              child: CircularProgressIndicator(color: AppColors.pink),
            );
          }
          if (profileSnap.hasError) {
            return Center(
              child: ErrorState(
                message: 'Could not load this shop.',
                error: friendlyError(profileSnap.error!),
                onRetry: _retryProfile,
              ),
            );
          }
          final profile = profileSnap.data;
          return CustomScrollView(
            slivers: [
              SliverAppBar(
                pinned: true,
                title: Text(
                  profile?.name ?? 'Shop',
                  style: AppTypography.pageTitle,
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverToBoxAdapter(
                  child: profile == null
                      ? const SizedBox.shrink()
                      : _buildHeader(profile),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverToBoxAdapter(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Shop Products',
                        style: AppTypography.sectionTitle.copyWith(
                          fontSize: 15,
                        ),
                      ),
                      PopupMenuButton<_SortOrder>(
                        initialValue: _sort,
                        onSelected: (v) => setState(() {
                          _sort = v;
                          _productsFuture = _fetchProducts();
                        }),
                        itemBuilder: (context) => _sortLabels.entries
                            .map(
                              (e) => PopupMenuItem(
                                value: e.key,
                                child: Text(e.value),
                              ),
                            )
                            .toList(),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              _sortLabels[_sort]!,
                              style: AppTypography.caption.copyWith(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Icon(
                              Icons.arrow_drop_down,
                              size: 18,
                              color: AppColors.textMuted,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: FutureBuilder<dynamic>(
                  future: _storeCategories,
                  builder: (context, snap) {
                    final categories = snap.data is Map
                        ? snap.data['items'] as List
                        : [];
                    return Padding(
                      padding: const EdgeInsets.all(16),
                      child: Wrap(
                        spacing: 8,
                        children: [
                          ChoiceChip(
                            label: const Text('All products'),
                            selected: _storeCategory == null,
                            onSelected: (_) => setState(() {
                              _storeCategory = null;
                              _productsFuture = _fetchProducts();
                            }),
                          ),
                          for (final c in categories)
                            ChoiceChip(
                              label: Text('${c['name']}'),
                              selected: _storeCategory == c['id'],
                              onSelected: (_) => setState(() {
                                _storeCategory = c['id'] as int;
                                _productsFuture = _fetchProducts();
                              }),
                            ),
                        ],
                      ),
                    );
                  },
                ),
              ),
              SliverToBoxAdapter(
                child: FutureBuilder<List<Product>>(
                  future: _productsFuture,
                  builder: (context, snapshot) {
                    return Column(
                      children: [
                        ProductGrid(
                          products: snapshot.data,
                          error: snapshot.error,
                          onTap: _openProduct,
                          onAddToCart: _addToCart,
                          wishlistedIds: _wishlistedIds,
                          onWishlistChanged: _toggleWishlist,
                          emptyMessage: 'This shop has no active products yet.',
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                        ),
                        if (snapshot.hasData && _more)
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: OutlinedButton(
                              onPressed: _loadingMore ? null : _loadMore,
                              child: Text(
                                _loadingMore
                                    ? 'Loading...'
                                    : 'Load more products',
                              ),
                            ),
                          ),
                      ],
                    );
                  },
                ),
              ),
              const SliverPadding(padding: EdgeInsets.only(bottom: 24)),
            ],
          );
        },
      ),
    );
  }

  Widget _buildHeader(ShopProfile profile) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          if (profile.bannerUrl != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.network(
                  profile.bannerUrl!,
                  width: double.infinity,
                  height: 130,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const SizedBox.shrink(),
                ),
              ),
            ),
          Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(colors: AppColors.pinkGradient),
                ),
                alignment: Alignment.center,
                child: profile.photoUrl != null
                    ? ClipOval(
                        child: Image.network(
                          profile.photoUrl!,
                          width: 56,
                          height: 56,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => const Icon(
                            Icons.storefront_outlined,
                            color: Colors.white,
                          ),
                        ),
                      )
                    : Text(
                        profile.name.isNotEmpty
                            ? profile.name[0].toUpperCase()
                            : '?',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 22,
                        ),
                      ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      profile.name,
                      style: AppTypography.productTitle.copyWith(fontSize: 16),
                    ),
                    if (profile.location != null)
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 12,
                            color: AppColors.textMuted,
                          ),
                          const SizedBox(width: 2),
                          Expanded(
                            child: Text(
                              profile.location!,
                              style: AppTypography.caption,
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          if (profile.description?.isNotEmpty == true)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(profile.description!, style: AppTypography.body),
            ),
          if (profile.joined != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                'Joined ${DateFormat('MMM y').format(profile.joined!)}',
                style: AppTypography.caption,
              ),
            ),
          FutureBuilder<ShopStats>(
            future: _statsFuture,
            builder: (context, snap) {
              final stats = snap.data;
              return Row(
                children: [
                  Expanded(
                    child: _statItem(
                      '${stats?.followerCount ?? '—'}',
                      'Followers',
                    ),
                  ),
                  Expanded(
                    child: _statItem(
                      '${stats?.productCount ?? '—'}',
                      'Products',
                    ),
                  ),
                  Expanded(
                    child: _statItem(
                      stats == null
                          ? '—'
                          : stats.averageRating.toStringAsFixed(1),
                      'Rating',
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _followBusy ? null : _toggleFollow,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _following
                        ? AppColors.white
                        : AppColors.pink,
                    backgroundColor: _following ? AppColors.pink : null,
                    side: BorderSide(color: AppColors.pink),
                  ),
                  icon: Icon(_following ? Icons.check : Icons.add, size: 16),
                  label: Text(_following ? 'Following' : 'Follow'),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _message,
                  icon: MessageIcon(size: 16, color: AppColors.pink),
                  label: const Text('Message'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statItem(String value, String label) {
    return Column(
      children: [
        Text(value, style: AppTypography.productTitle.copyWith(fontSize: 15)),
        Text(label, style: AppTypography.caption),
      ],
    );
  }
}
