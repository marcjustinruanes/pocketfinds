import 'dart:convert';

import 'delivery_service.dart';
import '../models/cart_item.dart';
import '../models/product.dart';
import 'product_service.dart';
import 'backend_service.dart';

class CartService {
  final BackendClient? client;
  CartService({this.client});
  BackendClient get _client => client ?? BackendService.client;
  ProductService get _productService => ProductService(client: _client);
  final _requests = <String, String>{};
  Future<List<CartItem>> fetchCart(int buyerId) async {
    final rows = await _client
        .from('cart_items')
        .select(
          'id, product_id, qty, price, variation_group, variation_value, name, seller, img',
        )
        .eq('buyer_id', buyerId)
        .order('created_at');
    if (rows.isEmpty) return [];
    // One batched query for every product in the cart, instead of one
    // query per distinct product.
    final productIds = rows.map((r) => r['product_id'] as String);
    final products = await _productService.fetchByIds(productIds);
    // Retain unavailable rows so buyers can review and remove them.
    return rows
        .map(
          (r) => CartItem.fromRow(
            r,
            products[r['product_id']] ??
                Product(
                  id: '${r['product_id']}',
                  name: '${r['name'] ?? 'Unavailable product'}',
                  price: double.tryParse('${r['price']}') ?? 0,
                  sellerId: 0,
                  sellerName: '${r['seller'] ?? 'Shop'}',
                  categoryName: 'Unavailable',
                  available: false,
                  imageUrl: Product.resolveImageUrl(r['img'] as String?),
                ),
          ),
        )
        .toList();
  }

  /// Mirrors `BuyerController::cartAdd()`: if this exact product+variant is
  /// already in the cart, add to its quantity (capped at 99); otherwise
  /// insert a new row.
  Future<void> addToCart({
    required int buyerId,
    required Product product,
    String color = '',
    String size = '',
    required int quantity,
  }) async {
    final key = jsonEncode([product.id, color, size, quantity]);
    final id = _requests.putIfAbsent(key, DeliveryService.requestId);
    await _client.request('cart/add', {
      'request_id': id,
      'product_id': product.id,
      'quantity': quantity,
      'group': color,
      'value': size,
    });
    _requests.remove(key);
  }

  Future<void> updateQuantity(String cartItemId, int quantity) async {
    await _client
        .from('cart_items')
        .update({
          'qty': quantity,
          'updated_at': DateTime.now().toIso8601String(),
        })
        .eq('id', cartItemId);
  }

  Future<void> editVariation(CartItem item, ProductOption option) async {
    final key = jsonEncode([
      'edit',
      item.id,
      option.group,
      option.value,
      item.quantity,
    ]);
    final request = _requests.putIfAbsent(key, DeliveryService.requestId);
    await _client.request('cart/edit', {
      'request_id': request,
      'cart_id': item.id,
      'quantity': item.quantity,
      'group': option.group,
      'value': option.value,
    });
    _requests.remove(key);
  }

  Future<void> removeItem(String cartItemId) async {
    await _client.from('cart_items').delete().eq('id', cartItemId);
  }

  Future<void> removeItems(List<String> cartItemIds) async {
    if (cartItemIds.isEmpty) return;
    await _client.from('cart_items').delete().inFilter('id', cartItemIds);
  }
}
