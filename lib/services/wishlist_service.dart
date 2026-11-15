import '../models/product.dart';
import '../models/wishlist_item.dart';
import 'product_service.dart';
import 'backend_service.dart';

class WishlistService {
  final _productService = ProductService();

  Future<List<WishlistItem>> fetchWishlist(int buyerId) async {
    final rows = await BackendService.client
        .from('wishlist_items')
        .select('id, product_id')
        .eq('buyer_id', buyerId)
        .order('created_at', ascending: false);

    if (rows.isEmpty) return [];

    // Batch-fetch products (only active ones come back — see ProductService)
    // so a saved-but-now-inactive product still shows up with product=null,
    // which the UI renders as "Currently unavailable" rather than dropping it.
    final productIds = rows.map((r) => r['product_id'] as String);
    final products = await _productService.fetchByIds(productIds);

    return rows
        .map((r) => WishlistItem.fromRow(r, products[r['product_id']]))
        .toList();
  }

  Future<bool> isWishlisted(int buyerId, String productId) async {
    final row = await BackendService.client
        .from('wishlist_items')
        .select('id')
        .eq('buyer_id', buyerId)
        .eq('product_id', productId)
        .maybeSingle();
    return row != null;
  }

  Future<void> add(int buyerId, Product product) async {
    await BackendService.client.from('wishlist_items').insert({
      'buyer_id': buyerId,
      'product_id': product.id,
    });
  }

  Future<void> remove(int buyerId, String productId) async {
    await BackendService.client
        .from('wishlist_items')
        .delete()
        .eq('buyer_id', buyerId)
        .eq('product_id', productId);
  }
}
