import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/cart_item.dart';
import '../../models/product.dart';
import '../../services/cart_service.dart';
import '../../services/product_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/category_chip.dart';
import '../../widgets/guest_sign_in_sheet.dart';
import '../../widgets/product_image.dart';
import 'checkout_screen.dart';

/// Mobile translation of `resources/views/buyer/product.blade.php` — image,
/// name, price, description, category, stock, variant + quantity
/// selection, add to cart / buy now. Chat-with-seller and reviews are out
/// of scope (the website itself always shows 0 rating/no reviews — that
/// data was never wired up server-side, so there's nothing real to show).
///
/// [user] is null for a guest viewing product details from Guest Home —
/// the page itself is public (matches the website's `guest.product` route),
/// but Add to Cart/Buy Now prompt sign-in instead of acting, same as the
/// guest product card buttons do.
class ProductDetailScreen extends StatefulWidget {
  final AppUser? user;
  final String productId;

  const ProductDetailScreen({
    super.key,
    required this.user,
    required this.productId,
  });

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final _productService = ProductService();
  final _cartService = CartService();

  late Future<Product?> _productFuture;
  final Map<String, String> _selectedVariants = {};
  int _quantity = 1;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _productFuture = _productService.fetchById(widget.productId);
  }

  void _ensureDefaultVariants(Product product) {
    for (final variation in product.variations) {
      _selectedVariants.putIfAbsent(variation.name, () => variation.options.first);
    }
  }

  String get _color => _selectedVariants['Color'] ?? _selectedVariants['color'] ?? '';
  String get _size => _selectedVariants['Size'] ?? _selectedVariants['size'] ?? '';

  Future<void> _addToCart(Product product) async {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    setState(() => _busy = true);
    try {
      await _cartService.addToCart(
        buyerId: user.id,
        productId: product.id,
        color: _color,
        size: _size,
        quantity: _quantity,
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
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _buyNow(Product product) async {
    final user = widget.user;
    if (user == null) {
      showGuestSignInSheet(context);
      return;
    }
    // "Buy Now" goes straight to checkout with just this item — it isn't
    // added to the persisted cart, so there's nothing to clean up there
    // afterward (CartItem.id is left blank to mark it as not cart-backed).
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CheckoutScreen(
          user: user,
          items: [
            CartItem(
              id: '',
              productId: product.id,
              color: _color,
              size: _size,
              quantity: _quantity,
              product: product,
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Product')),
      body: FutureBuilder<Product?>(
        future: _productFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          final product = snapshot.data;
          if (product == null) {
            return const Center(child: Text('Product not found.'));
          }
          _ensureDefaultVariants(product);

          return SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: ProductImage(
                    imageUrl: product.imageUrl,
                    fit: BoxFit.contain,
                    placeholderIconSize: 64,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: AppColors.pink,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          product.categoryName.toUpperCase(),
                          style: const TextStyle(
                            fontSize: 10,
                            color: AppColors.white,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.4,
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        product.name,
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        formatPeso(product.price),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppColors.pinkDark,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          const Icon(Icons.storefront, size: 14, color: AppColors.textMuted),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              product.sellerName,
                              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                      if (product.sellerLocation != null) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.location_on_outlined, size: 14, color: AppColors.textMuted),
                            const SizedBox(width: 4),
                            Text(
                              product.sellerLocation!,
                              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        product.stock > 0
                            ? '${product.stock} in stock'
                            : 'Stock not specified by seller',
                        style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                      ),
                      const Divider(height: 32),
                      for (final variation in product.variations) ...[
                        Text(
                          variation.name,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: variation.options.map((option) {
                            return CategoryChip(
                              label: option,
                              selected: _selectedVariants[variation.name] == option,
                              onTap: () => setState(
                                () => _selectedVariants[variation.name] = option,
                              ),
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: 16),
                      ],
                      const Text(
                        'Quantity',
                        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          _qtyButton(
                            Icons.remove,
                            _quantity > 1 ? () => setState(() => _quantity--) : null,
                          ),
                          SizedBox(
                            width: 40,
                            child: Text(
                              '$_quantity',
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                            ),
                          ),
                          _qtyButton(
                            Icons.add,
                            _quantity < 99 ? () => setState(() => _quantity++) : null,
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (product.description != null && product.description!.isNotEmpty) ...[
                        const Text(
                          'Description',
                          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                        const SizedBox(height: 8),
                        Text(product.description!, style: const TextStyle(fontSize: 13, height: 1.5)),
                      ],
                      const SizedBox(height: 80),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
      bottomNavigationBar: FutureBuilder<Product?>(
        future: _productFuture,
        builder: (context, snapshot) {
          final product = snapshot.data;
          if (product == null) return const SizedBox.shrink();
          return DecoratedBox(
            decoration: const BoxDecoration(color: AppColors.white, boxShadow: AppShadows.md),
            child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _busy ? null : () => _addToCart(product),
                      icon: const Icon(Icons.shopping_cart_outlined, size: 18),
                      label: const Text('Add to Cart'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _busy ? null : () => _buyNow(product),
                      child: const Text('Buy Now'),
                    ),
                  ),
                ],
              ),
            ),
            ),
          );
        },
      ),
    );
  }

  Widget _qtyButton(IconData icon, VoidCallback? onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 32,
        height: 32,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: onTap == null ? AppColors.border.withValues(alpha: 0.4) : AppColors.pinkSoft,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(
          icon,
          size: 16,
          color: onTap == null ? AppColors.textMuted : AppColors.pink,
        ),
      ),
    );
  }
}
