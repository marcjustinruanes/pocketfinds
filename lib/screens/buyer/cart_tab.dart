import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/cart_item.dart';
import '../../services/cart_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/product_image.dart';
import 'checkout_screen.dart';

/// Mobile translation of `resources/views/buyer/cart.blade.php` — view
/// cart, change quantity, remove items, pick which items to check out, and
/// proceed to checkout. Vouchers are intentionally not translated (the
/// website's voucher codes are hardcoded constants, not real Supabase data
/// — recreating them would itself be "hardcoding dynamic data").
class CartTab extends StatefulWidget {
  final AppUser user;

  const CartTab({super.key, required this.user});

  @override
  State<CartTab> createState() => _CartTabState();
}

class _CartTabState extends State<CartTab> {
  final _cartService = CartService();
  List<CartItem>? _items;
  Object? _error;
  final Set<String> _selectedIds = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _items = null;
      _error = null;
    });
    try {
      final items = await _cartService.fetchCart(widget.user.id);
      if (!mounted) return;
      setState(() {
        _items = items;
        _selectedIds
          ..clear()
          ..addAll(items.map((i) => i.id));
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  Future<void> _changeQuantity(CartItem item, int delta) async {
    final newQty = item.quantity + delta;
    if (newQty < 1 || newQty > 99) return;
    try {
      await _cartService.updateQuantity(item.id, newQty);
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not update quantity: ${friendlyError(e)}')));
    }
  }

  Future<void> _remove(CartItem item) async {
    try {
      await _cartService.removeItem(item.id);
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not remove item: ${friendlyError(e)}')));
    }
  }

  List<CartItem> get _selectedItems =>
      (_items ?? []).where((i) => _selectedIds.contains(i.id)).toList();

  double get _subtotal =>
      _selectedItems.fold(0, (sum, item) => sum + item.lineTotal);

  Future<void> _checkout() async {
    if (_selectedItems.isEmpty) return;
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            CheckoutScreen(user: widget.user, items: _selectedItems),
      ),
    );
    if (result == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Cart')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: Builder(
          builder: (context) {
            if (_items == null && _error == null) {
              return const Center(child: CircularProgressIndicator());
            }
            if (_error != null) {
              return ListView(
                children: [
                  ErrorState(message: 'Could not load your cart.', error: friendlyError(_error!)),
                ],
              );
            }
            if (_items!.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(
                    icon: Icons.shopping_cart_outlined,
                    title: 'Your cart is empty',
                    subtitle: 'Browse products and add items to your cart.',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 160),
              itemCount: _items!.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) => _cartItemTile(_items![index]),
            );
          },
        ),
      ),
      bottomNavigationBar: (_items != null && _items!.isNotEmpty)
          ? SafeArea(
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  color: AppColors.white,
                  boxShadow: AppShadows.md,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          '${_selectedItems.length} item(s) selected',
                          style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                        ),
                        Text(
                          'Subtotal: ${formatPeso(_subtotal)}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _selectedItems.isEmpty ? null : _checkout,
                        child: const Text('Proceed to Checkout'),
                      ),
                    ),
                  ],
                ),
              ),
            )
          : null,
    );
  }

  Widget _cartItemTile(CartItem item) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Checkbox(
              value: _selectedIds.contains(item.id),
              onChanged: (checked) => setState(() {
                if (checked == true) {
                  _selectedIds.add(item.id);
                } else {
                  _selectedIds.remove(item.id);
                }
              }),
            ),
            SizedBox(
              width: 64,
              height: 64,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: ProductImage(imageUrl: item.product.imageUrl, placeholderIconSize: 24),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                  if (item.variantLabel.isNotEmpty)
                    Text(
                      item.variantLabel,
                      style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    '${formatPeso(item.product.price)} each',
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      _qtyButton(Icons.remove, () => _changeQuantity(item, -1)),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        child: Text('${item.quantity}'),
                      ),
                      _qtyButton(Icons.add, () => _changeQuantity(item, 1)),
                      const Spacer(),
                      Text(
                        formatPeso(item.lineTotal),
                        style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.pinkDark),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, size: 20, color: AppColors.danger),
              onPressed: () => _remove(item),
            ),
          ],
        ),
      ),
    );
  }

  Widget _qtyButton(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 28,
        height: 28,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.pinkSoft,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 14, color: AppColors.pink),
      ),
    );
  }
}
