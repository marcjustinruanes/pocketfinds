import '../models/product.dart';
import 'product_service.dart';
import 'backend_service.dart';

class VisualSearchService {
  final _productService = ProductService();

  Future<List<Product>> findSimilar(
    List<double> signature, {
    int limit = 20,
  }) async {
    final rows = await BackendService.client.rpc(
      'find_visually_similar_products',
      params: {'p_signature': signature, 'p_limit': limit},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return [];

    final orderedIds = list.map((r) => r['id'] as String).toList();
    final products = await _productService.fetchByIds(orderedIds);
    // Preserve the real similarity order the RPC returned (closest first).
    return orderedIds
        .where(products.containsKey)
        .map((id) => products[id]!)
        .toList();
  }
}
