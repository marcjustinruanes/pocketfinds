import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/product_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/product_grid.dart';
import 'product_detail_screen.dart';

/// Server-side product search across ALL active products (not just a
/// loaded page) — translates the site's header search box.
class SearchResultsScreen extends StatefulWidget {
  final AppUser user;
  final String query;

  const SearchResultsScreen({
    super.key,
    required this.user,
    required this.query,
  });

  @override
  State<SearchResultsScreen> createState() => _SearchResultsScreenState();
}

class _SearchResultsScreenState extends State<SearchResultsScreen> {
  final _productService = ProductService();
  final _cartService = CartService();
  List<Product>? _products;
  Object? _error;
  late String _query = widget.query;

  @override
  void initState() {
    super.initState();
    _search(_query);
  }

  Future<void> _search(String query) async {
    setState(() {
      _query = query;
      _products = null;
      _error = null;
    });
    try {
      final products = await _productService.searchActiveProducts(query);
      if (!mounted) return;
      setState(() => _products = products);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  Future<void> _addToCart(Product product) async {
    try {
      await _cartService.addToCart(
        buyerId: widget.user.id,
        productId: product.id,
        quantity: 1,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('${product.name} added to cart')));
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
      appBar: AppBar(
        title: SizedBox(
          height: 44,
          child: AppSearchBar(
            initialValue: _query,
            onChanged: (_) {},
            onSubmitted: _search,
          ),
        ),
      ),
      body: ProductGrid(
        products: _products,
        error: _error,
        onTap: _openProduct,
        onAddToCart: _addToCart,
        emptyMessage: 'No products found for "$_query".',
      ),
    );
  }
}
