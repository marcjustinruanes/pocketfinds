import '../models/product.dart';
import '../models/shop_profile.dart';
import '../models/shop_stats.dart';
import '../models/shop_summary.dart';
import 'product_service.dart';
import 'backend_service.dart';

const _sellerColumns =
    'id, given_names, last_name, business_name, username, municipality, province, profile_picture, store_description, store_banner, created_at';

class ShopService {
  final _productService = ProductService();

  Future<ShopProfile?> fetchProfile(int sellerId) async {
    final row = await BackendService.client
        .from('users')
        .select(_sellerColumns)
        .eq('id', sellerId)
        .maybeSingle();
    return row == null ? null : ShopProfile.fromRow(row);
  }

  Future<ShopStats> fetchStats(int sellerId) async {
    final row = await BackendService.client.rpc(
      'get_shop_stats',
      params: {'p_seller_id': sellerId},
    );
    final list = List<Map<String, dynamic>>.from(row as List);
    if (list.isEmpty) {
      return const ShopStats(
        followerCount: 0,
        productCount: 0,
        averageRating: 0,
        reviewCount: 0,
      );
    }
    return ShopStats.fromMap(list.first);
  }

  Future<List<Product>> fetchShopProducts(int sellerId) {
    return _productService.fetchActiveProducts(sellerId: sellerId);
  }

  /// Real, ordered by real average rating then real product count —
  /// `list_shops()` — for Guest/Buyer Home's "Discover Shops" rail. No
  /// "trending"/"featured" fabrication.
  Future<List<ShopSummary>> discoverShops({int limit = 10}) async {
    final rows = await BackendService.client.rpc(
      'list_shops',
      params: {'p_limit': limit},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    return list.map(ShopSummary.fromMap).toList();
  }

  Future<bool> isFollowing(int buyerId, int sellerId) async {
    final row = await BackendService.client
        .from('shop_follows')
        .select('id')
        .eq('buyer_id', buyerId)
        .eq('seller_id', sellerId)
        .maybeSingle();
    return row != null;
  }

  Future<void> follow(int buyerId, int sellerId) async {
    await BackendService.client.from('shop_follows').insert({
      'buyer_id': buyerId,
      'seller_id': sellerId,
    });
  }

  Future<void> unfollow(int buyerId, int sellerId) async {
    await BackendService.client
        .from('shop_follows')
        .delete()
        .eq('buyer_id', buyerId)
        .eq('seller_id', sellerId);
  }

  Future<List<ShopProfile>> fetchFollowedShops(int buyerId) async {
    final follows = await BackendService.client
        .from('shop_follows')
        .select('seller_id, created_at')
        .eq('buyer_id', buyerId)
        .order('created_at', ascending: false);
    if (follows.isEmpty) return [];

    final sellerIds = follows.map((f) => f['seller_id'] as int).toList();
    final sellers = await BackendService.client
        .from('users')
        .select(_sellerColumns)
        .inFilter('id', sellerIds);
    final byId = {for (final s in sellers) s['id'] as int: s};

    return sellerIds
        .where(byId.containsKey)
        .map((id) => ShopProfile.fromRow(byId[id]!))
        .toList();
  }
}
