import '../models/product.dart';
import 'supabase_service.dart';

const _productColumns =
    'id, seller_id, category_id, name, description, image, price, stock, variations';
const _sellerColumns =
    'id, given_names, last_name, business_name, username, municipality, province';

/// Reads products from the existing `products` table — same rules Laravel
/// uses (`FetchesProducts::dbProducts()`): only `status = 'active'`, newest
/// first, optional limit.
///
/// `products.seller_id` and `products.category_id` aren't set up as
/// Supabase foreign keys, so we can't ask PostgREST to auto-embed
/// `users`/`categories` the way it can for a real FK relationship. Instead
/// we fetch the products, then batch-fetch just the sellers and categories
/// referenced by that page of results and join them here in Dart — two
/// extra small queries instead of one big embedded one.
class ProductService {
  Future<List<Product>> fetchActiveProducts({
    int? limit,
    int? categoryId,
  }) async {
    var query = SupabaseService.client
        .from('products')
        .select(_productColumns)
        .eq('status', 'active');

    if (categoryId != null) {
      query = query.eq('category_id', categoryId);
    }

    final rows = await query.order(
      'created_at',
      ascending: false,
    ).limit(limit ?? 1000);

    return _joinAndMap(rows);
  }

  /// Server-side search across ALL active products (not just a loaded page),
  /// matching by name — mirrors the website's product search intent.
  Future<List<Product>> searchActiveProducts(String query) async {
    final trimmed = query.trim();
    if (trimmed.isEmpty) return [];

    final rows = await SupabaseService.client
        .from('products')
        .select(_productColumns)
        .eq('status', 'active')
        .ilike('name', '%$trimmed%')
        .order('created_at', ascending: false)
        .limit(50);

    return _joinAndMap(rows);
  }

  Future<Product?> fetchById(String id) async {
    final row = await SupabaseService.client
        .from('products')
        .select(_productColumns)
        .eq('id', id)
        .eq('status', 'active')
        .maybeSingle();
    if (row == null) return null;
    final mapped = await _joinAndMap([row]);
    return mapped.isEmpty ? null : mapped.first;
  }

  /// Batch version of [fetchById] — one round trip for many products
  /// instead of one per id (used by `CartService.fetchCart`, which
  /// otherwise would fire a separate query per distinct cart item).
  Future<Map<String, Product>> fetchByIds(Iterable<String> ids) async {
    final idList = ids.toSet().toList();
    if (idList.isEmpty) return {};
    final rows = await SupabaseService.client
        .from('products')
        .select(_productColumns)
        .inFilter('id', idList)
        .eq('status', 'active');
    final products = await _joinAndMap(rows);
    return {for (final p in products) p.id: p};
  }

  Future<List<Product>> _joinAndMap(List<Map<String, dynamic>> rows) async {
    if (rows.isEmpty) return [];

    final sellerIds = rows.map((r) => r['seller_id'] as int).toSet().toList();
    final categoryIds = rows
        .map((r) => r['category_id'] as int?)
        .whereType<int>()
        .toSet()
        .toList();

    final sellersFuture = sellerIds.isEmpty
        ? Future.value(<Map<String, dynamic>>[])
        : SupabaseService.client
              .from('users')
              .select(_sellerColumns)
              .inFilter('id', sellerIds);

    final categoriesFuture = categoryIds.isEmpty
        ? Future.value(<Map<String, dynamic>>[])
        : SupabaseService.client
              .from('categories')
              .select('id, name')
              .inFilter('id', categoryIds);

    final results = await Future.wait([sellersFuture, categoriesFuture]);
    final sellersById = {for (final s in results[0]) s['id'] as int: s};
    final categoryNamesById = {
      for (final c in results[1]) c['id'] as int: c['name'] as String,
    };

    return rows
        .map(
          (row) => Product.fromRow(
            row: row,
            seller: sellersById[row['seller_id']],
            categoryName: categoryNamesById[row['category_id']],
          ),
        )
        .toList();
  }
}
