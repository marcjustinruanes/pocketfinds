import '../models/cart_item.dart';
import 'product_service.dart';
import 'supabase_service.dart';

/// Reads/writes the signed-in buyer's cart, stored server-side in
/// `cart_items` (RLS-scoped so a buyer only ever sees their own rows) —
/// unlike Laravel's PHP-session cart, this persists across app restarts and
/// devices. See project notes for why this table was added.
class CartService {
  final _productService = ProductService();

  Future<List<CartItem>> fetchCart(int buyerId) async {
    final rows = await SupabaseService.client
        .from('cart_items')
        .select('id, product_id, color, size, quantity')
        .eq('buyer_id', buyerId)
        .order('created_at');

    if (rows.isEmpty) return [];

    // One batched query for every product in the cart, instead of one
    // query per distinct product.
    final productIds = rows.map((r) => r['product_id'] as String);
    final products = await _productService.fetchByIds(productIds);

    // Skip rows whose product has since been deactivated/deleted, matching
    // Laravel's cart(), which only resolves items against currently active
    // products.
    return rows
        .where((r) => products.containsKey(r['product_id']))
        .map((r) => CartItem.fromRow(r, products[r['product_id']]!))
        .toList();
  }

  /// Mirrors `BuyerController::cartAdd()`: if this exact product+variant is
  /// already in the cart, add to its quantity (capped at 99); otherwise
  /// insert a new row.
  Future<void> addToCart({
    required int buyerId,
    required String productId,
    String color = '',
    String size = '',
    required int quantity,
  }) async {
    final existing = await SupabaseService.client
        .from('cart_items')
        .select('id, quantity')
        .eq('buyer_id', buyerId)
        .eq('product_id', productId)
        .eq('color', color)
        .eq('size', size)
        .maybeSingle();

    if (existing != null) {
      final newQty = ((existing['quantity'] as num).toInt() + quantity).clamp(
        1,
        99,
      );
      await SupabaseService.client
          .from('cart_items')
          .update({'quantity': newQty, 'updated_at': DateTime.now().toIso8601String()})
          .eq('id', existing['id']);
    } else {
      await SupabaseService.client.from('cart_items').insert({
        'buyer_id': buyerId,
        'product_id': productId,
        'color': color,
        'size': size,
        'quantity': quantity.clamp(1, 99),
      });
    }
  }

  Future<void> updateQuantity(String cartItemId, int quantity) async {
    await SupabaseService.client
        .from('cart_items')
        .update({
          'quantity': quantity.clamp(1, 99),
          'updated_at': DateTime.now().toIso8601String(),
        })
        .eq('id', cartItemId);
  }

  Future<void> removeItem(String cartItemId) async {
    await SupabaseService.client.from('cart_items').delete().eq(
      'id',
      cartItemId,
    );
  }

  Future<void> removeItems(List<String> cartItemIds) async {
    if (cartItemIds.isEmpty) return;
    await SupabaseService.client
        .from('cart_items')
        .delete()
        .inFilter('id', cartItemIds);
  }
}
