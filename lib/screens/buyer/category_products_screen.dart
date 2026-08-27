import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/product_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/product_grid.dart';
import 'product_detail_screen.dart';

/// "Category → Products belonging to that category" — a buyer taps a
/// category chip on Home and lands here.
class CategoryProductsScreen extends StatefulWidget {
  final AppUser user;
  final Category category;

  const CategoryProductsScreen({
    super.key,
    required this.user,
    required this.category,
  });

  @override
  State<CategoryProductsScreen> createState() =>
      _CategoryProductsScreenState();
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
