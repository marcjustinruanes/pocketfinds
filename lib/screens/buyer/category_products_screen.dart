import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/product_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/product_grid.dart';
import 'product_detail_screen.dart';
import 'search_results_screen.dart';

/// "Category → Products belonging to that category" — a buyer taps a
/// category chip on Home and lands here. `user` is nullable — also reached
/// from Guest Home's Categories "See All", where Add to Cart prompts
/// sign-in instead (same gate `ProductDetailScreen`/`ShopScreen` use).
class CategoryProductsScreen extends StatefulWidget {
  final AppUser? user;
  final Category category;

  const CategoryProductsScreen({
    super.key,
    required this.user,
    required this.category,
  });

  @override
  State<CategoryProductsScreen> createState() => _CategoryProductsScreenState();
}

class _CategoryProductsScreenState extends State<CategoryProductsScreen> {
  final _productService = ProductService();
  final _cartService = CartService();
  List<Product>? _products;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final products = await _productService.fetchActiveProducts(
        categoryId: widget.category.id,
      );
      if (!mounted) return;
      setState(() => _products = products);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  Future<void> _addToCart(Product product) async {
    if (widget.user == null) {
      showGuestSignInSheet(context);
      return;
    }
    try {
      await _cartService.addToCart(
        buyerId: widget.user!.id,
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
    if (widget.user != null) {
      return SearchResultsScreen(
        user: widget.user!,
        query: '',
        initialCategory: widget.category.id,
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(widget.category.name)),
      body: ProductGrid(
        products: _products,
        error: _error,
        onTap: _openProduct,
        onAddToCart: _addToCart,
        emptyMessage: 'No products in "${widget.category.name}" yet.',
      ),
    );
  }
}
