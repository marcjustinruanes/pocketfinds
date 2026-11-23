import 'dart:io';

import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/image_signature_service.dart';
import '../../services/visual_search_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/product_grid.dart';
import 'product_detail_screen.dart';

class VisualSearchResultsScreen extends StatefulWidget {
  final AppUser? user;
  final File photo;

  const VisualSearchResultsScreen({
    super.key,
    required this.user,
    required this.photo,
  });

  @override
  State<VisualSearchResultsScreen> createState() =>
      _VisualSearchResultsScreenState();
}

class _VisualSearchResultsScreenState extends State<VisualSearchResultsScreen> {
  final _signatureService = ImageSignatureService();
  final _visualSearchService = VisualSearchService();
  final _cartService = CartService();
  List<Product>? _products;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _search();
  }

  Future<void> _search() async {
    setState(() {
      _products = null;
      _error = null;
    });
    try {
      final signature = _signatureService.computeSignature(widget.photo);
      if (signature == null) {
        throw Exception("Couldn't read that photo — try another one.");
      }
      final products = await _visualSearchService.findSimilar(signature);
      if (!mounted) return;
      setState(() => _products = products);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  Future<void> _addToCart(Product product) async {
    if (product.hasVariations) {
      _openProduct(product);
      return;
    }
    final user = widget.user;
    if (user == null) return;
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
        title: Text('Visual Search', style: AppTypography.pageTitle),
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppSpacing.md),
            color: AppColors.white,
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.file(
                    widget.photo,
                    width: 52,
                    height: 52,
                    fit: BoxFit.cover,
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Text(
                    'Products visually similar to your photo',
                    style: AppTypography.body,
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ProductGrid(
              products: _products,
              error: _error,
              onTap: _openProduct,
              onAddToCart: _addToCart,
              emptyMessage: 'No visually similar products found.',
            ),
          ),
        ],
      ),
    );
  }
}
