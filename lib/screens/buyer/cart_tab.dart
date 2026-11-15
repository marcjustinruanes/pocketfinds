import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_user.dart';
import '../../models/cart_item.dart';
import '../../models/product.dart';
import '../../services/backend_service.dart';
import '../../services/cart_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/product_image.dart';
import 'checkout_screen.dart';
import 'shop_screen.dart';

class CartTab extends StatefulWidget {
  final AppUser user;
  final BackendClient? client;
  final VoidCallback? onContinueShopping;
  const CartTab({
    super.key,
    required this.user,
    this.client,
    this.onContinueShopping,
  });
  @override
  State<CartTab> createState() => _CartTabState();
}

class _CartTabState extends State<CartTab> {
  late final _service = CartService(client: widget.client);
  List<CartItem>? _items;
  Object? _error;
  bool _busy = false;
  final Set<String> _selected = {};
  String get _selectionKey => 'cart_selection_${widget.user.id}';
  List<CartItem> get _eligible =>
      (_items ?? []).where((i) => i.selectable).toList();
  List<CartItem> get _chosen =>
      _eligible.where((i) => _selected.contains(i.id)).toList();
  int _cents(double amount) => (amount * 100).round();
  int _total(Iterable<CartItem> items) =>
      items.fold(0, (sum, i) => sum + _cents(i.unitPrice) * i.quantity);
  String _money(int cents) => formatPeso(cents / 100);
  @override
  void initState() {
    super.initState();
    _load(initial: true);
  }

  Future<void> _saveSelection() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(_selectionKey, _selected.toList());
  }

  Future<void> _load({bool initial = false}) async {
    try {
      final items = await _service.fetchCart(widget.user.id);
      final prefs = initial ? await SharedPreferences.getInstance() : null;
      if (!mounted) return;
      setState(() {
        _items = items;
        _error = null;
        if (initial) {
          _selected.addAll(
            prefs!.getStringList(_selectionKey) ??
                items.where((i) => i.selectable).map((i) => i.id),
          );
        }
        _selected.retainAll(_eligible.map((i) => i.id));
      });
      await _saveSelection();
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
        });
      }
    }
  }

  void _select(Iterable<CartItem> items, bool checked) {
    setState(() {
      for (final item in items.where((i) => i.selectable)) {
        if (checked) {
          _selected.add(item.id);
        } else {
          _selected.remove(item.id);
        }
      }
    });
    _saveSelection();
  }

  Future<void> _quantity(CartItem item, int delta) async {
    final qty = delta < 0 && item.quantity > item.availableStock
        ? item.availableStock
        : item.quantity + delta;
    if (_busy || qty < 1 || (delta > 0 && qty > item.availableStock)) return;
    setState(() {
      _busy = true;
      _items = _items!
          .map((i) => i.id == item.id ? i.withQuantity(qty) : i)
          .toList();
    });
    try {
      await _service.updateQuantity(item.id, qty);
      await _load();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _items = _items!.map((i) => i.id == item.id ? item : i).toList();
      });
      AppSnackBar.error(context, friendlyError(e));
      await _load();
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _remove(List<CartItem> items) async {
    if (_busy || items.isEmpty) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          items.length == 1
              ? 'Remove this item?'
              : 'Remove ${items.length} items?',
        ),
        content: Text('These products will be removed from your cart.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('Keep items'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text('Remove'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted || _busy) return;
    setState(() {
      _busy = true;
    });
    try {
      await _service.removeItems(items.map((i) => i.id).toList());
      if (!mounted) return;
      setState(() {
        final ids = items.map((i) => i.id).toSet();
        _items!.removeWhere((i) => ids.contains(i.id));
        _selected.removeAll(ids);
      });
      await _saveSelection();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Removed from cart')));
      }
    } catch (e) {
      if (mounted) {
        AppSnackBar.error(context, friendlyError(e));
      }
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _variation(CartItem item) async {
    final option = await showModalBottomSheet<ProductOption>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: DraggableScrollableSheet(
          expand: false,
          builder: (context, controller) => ListView(
            controller: controller,
            padding: EdgeInsets.all(16),
            children: [
              Text(
                'Change variation',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              SizedBox(height: 8),
              for (final option in item.product.purchaseOptions)
                ListTile(
                  title: Text(option.label),
                  subtitle: Text(
                    '${formatPeso(option.price)} · ${option.stock} available',
                  ),
                  enabled: option.stock >= item.quantity,
                  selected:
                      option.group == item.color && option.value == item.size,
                  onTap: () => Navigator.pop(context, option),
                ),
            ],
          ),
        ),
      ),
    );
    if (option == null || !mounted || _busy) return;
    setState(() {
      _busy = true;
    });
    try {
      await _service.editVariation(item, option);
      await _load();
    } catch (e) {
      if (mounted) {
        AppSnackBar.error(context, friendlyError(e));
      }
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _checkout() async {
    if (_busy || _chosen.isEmpty) return;
    final before = {for (final i in _chosen) i.id: i};
    setState(() {
      _busy = true;
    });
    try {
      final fresh = await _service.fetchCart(widget.user.id);
      if (!mounted) return;
      final selected = fresh.where((i) => before.containsKey(i.id)).toList();
      final changed =
          selected.length != before.length ||
          selected.any(
            (i) =>
                !i.selectable ||
                i.quantity != before[i.id]!.quantity ||
                i.color != before[i.id]!.color ||
                i.size != before[i.id]!.size ||
                _cents(i.unitPrice) != _cents(before[i.id]!.unitPrice),
          );
      setState(() {
        _items = fresh;
        _error = null;
        _selected.retainAll(_eligible.map((i) => i.id));
      });
      await _saveSelection();
      if (!mounted) return;
      if (changed) {
        ScaffoldMessenger.of(context).clearSnackBars();
        AppSnackBar.error(
          context,
          'Prices, quantities or availability changed. Review your updated cart before checkout.',
        );
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(
            user: widget.user,
            items: selected,
            client: widget.client,
          ),
        ),
      );
      if (mounted) {
        await _load();
      }
    } catch (e) {
      if (mounted) {
        AppSnackBar.error(
          context,
          'Could not check your cart. ${friendlyError(e)}',
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Widget _checkbox(List<CartItem> items, String label) {
    final eligible = items.where((i) => i.selectable).toList();
    final count = eligible.where((i) => _selected.contains(i.id)).length;
    return Checkbox(
      semanticLabel: label,
      tristate: true,
      value: count == 0
          ? false
          : count == eligible.length
          ? true
          : null,
      onChanged: _busy || eligible.isEmpty
          ? null
          : (_) => _select(eligible, count != eligible.length),
    );
  }

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<CartItem>>{};
    for (final item in _items ?? <CartItem>[]) {
      final key = item.product.sellerId == 0
          ? 'unavailable:${item.product.sellerName}'
          : '${item.product.sellerId}';
      groups.putIfAbsent(key, () => []).add(item);
    }
    final quantity = (_items ?? []).fold(0, (sum, i) => sum + i.quantity);
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'My Cart',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            if (_items != null)
              Text(
                '${_items!.length} products · $quantity items',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
          ],
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          if (!_busy) await _load();
        },
        child: _body(groups),
      ),
      bottomNavigationBar: _items?.isNotEmpty == true ? _footer() : null,
    );
  }

  Widget _body(Map<String, List<CartItem>> groups) {
    if (_items == null && _error == null) {
      return Center(child: CircularProgressIndicator());
    }
    if (_items == null) {
      return Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Could not load your cart.'),
              Text(friendlyError(_error!)),
              TextButton(onPressed: _load, child: Text('Retry')),
            ],
          ),
        ),
      );
    }
    if (_items!.isEmpty) {
      return LayoutBuilder(
        builder: (context, constraints) => ListView(
          physics: AlwaysScrollableScrollPhysics(),
          children: [
            ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 38,
                      backgroundColor: AppColors.pinkSoft,
                      child: Icon(
                        Icons.shopping_cart_outlined,
                        size: 34,
                        color: AppColors.pink,
                      ),
                    ),
                    SizedBox(height: 20),
                    Text(
                      'Your Cart is Empty',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Find your next everyday favorite. Explore products from our shops.',
                      textAlign: TextAlign.center,
                    ),
                    SizedBox(height: 20),
                    FilledButton(
                      onPressed:
                          widget.onContinueShopping ??
                          () => Navigator.of(context).maybePop(),
                      child: Text('Continue Shopping'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    }
    return ListView(
      physics: AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(12, 4, 12, 20),
      children: [
        if (_error != null)
          MaterialBanner(
            content: Text('Could not refresh. ${friendlyError(_error!)}'),
            actions: [
              TextButton(onPressed: _busy ? null : _load, child: Text('Retry')),
            ],
          ),
        Row(
          children: [
            _checkbox(_items!, 'Select all products'),
            Expanded(
              child: Text(
                'Select All',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
            TextButton(
              onPressed: _busy || _chosen.isEmpty
                  ? null
                  : () => _remove(_chosen),
              child: Text('Remove selected'),
            ),
          ],
        ),
        if (_items!.any((i) => !i.selectable))
          Padding(
            padding: EdgeInsets.fromLTRB(8, 0, 8, 12),
            child: Text(
              'Unavailable items are excluded from checkout. Update their quantity or variation, or remove them.',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
          ),
        for (final items in groups.values) _shop(items),
        Padding(
          padding: EdgeInsets.all(8),
          child: Text(
            'Delivery fees and eligible vouchers are calculated at checkout.',
            style: TextStyle(fontSize: 12, color: AppColors.textMuted),
          ),
        ),
      ],
    );
  }

  Widget _shop(List<CartItem> items) => Container(
    margin: EdgeInsets.only(bottom: 12),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            _checkbox(items, 'Select ${items.first.product.sellerName}'),
            Icon(
              Icons.storefront_outlined,
              size: 20,
              color: AppColors.textMuted,
            ),
            SizedBox(width: 8),
            Expanded(
              child: TextButton(
                style: TextButton.styleFrom(
                  alignment: Alignment.centerLeft,
                  foregroundColor: AppColors.textPrimary,
                ),
                onPressed: items.first.product.sellerId == 0
                    ? null
                    : () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ShopScreen(
                            user: widget.user,
                            sellerId: items.first.product.sellerId,
                          ),
                        ),
                      ),
                child: Text(
                  items.first.product.sellerName,
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
        for (final item in items) ...[Divider(height: 1), _item(item)],
        Divider(height: 1),
        Padding(
          padding: EdgeInsets.all(14),
          child: Wrap(
            alignment: WrapAlignment.spaceBetween,
            spacing: 16,
            runSpacing: 4,
            children: [
              Text(
                'Selected shop subtotal',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              Text(
                _money(
                  _total(
                    items.where(
                      (i) => i.selectable && _selected.contains(i.id),
                    ),
                  ),
                ),
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ],
    ),
  );
  Widget _item(CartItem item) {
    final original =
        item.product.purchaseOptions.isEmpty && item.product.hasDiscount
        ? item.product.price
        : null;
    return Padding(
      padding: EdgeInsets.fromLTRB(0, 12, 12, 12),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _checkbox([item], 'Select ${item.product.name}'),
              SizedBox(
                width: 64,
                height: 72,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: ProductImage(
                    imageUrl:
                        item.selectedOption?.imageUrl ?? item.product.imageUrl,
                    placeholderIconSize: 24,
                  ),
                ),
              ),
              SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.product.name,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (item.variantLabel.isNotEmpty)
                      Padding(
                        padding: EdgeInsets.only(top: 4),
                        child: Text(
                          item.variantLabel,
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ),
                    SizedBox(height: 6),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          formatPeso(item.unitPrice),
                          style: TextStyle(
                            fontSize: 15,
                            color: AppColors.pink,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (original != null)
                          Text(
                            formatPeso(original),
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        if (original != null && original > 0)
                          Text(
                            '-${((1 - item.unitPrice / original) * 100).round()}%',
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.pink,
                            ),
                          ),
                      ],
                    ),
                    Padding(
                      padding: EdgeInsets.only(top: 4),
                      child: Text(
                        item.unavailableReason ??
                            '${item.availableStock} available${item.savedUnitPrice != null && _cents(item.savedUnitPrice!) != _cents(item.unitPrice) ? ' · Price updated' : ''}',
                        style: TextStyle(
                          fontSize: 12,
                          color: item.selectable
                              ? AppColors.textMuted
                              : AppColors.danger,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: EdgeInsets.only(left: 12, top: 8),
            child: Wrap(
              spacing: 8,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.center,
              alignment: WrapAlignment.spaceBetween,
              children: [
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _quantityButton(item, -1),
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 8),
                      child: Text(
                        '${item.quantity}',
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                    _quantityButton(item, 1),
                  ],
                ),
                Text(
                  'Subtotal ${_money(_cents(item.unitPrice) * item.quantity)}',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.only(left: 12),
            child: Row(
              children: [
                if (item.product.available &&
                    item.product.purchaseOptions.isNotEmpty)
                  Flexible(
                    child: TextButton(
                      onPressed: _busy ? null : () => _variation(item),
                      child: Text('Change variation'),
                    ),
                  ),
                Spacer(),
                TextButton.icon(
                  onPressed: _busy ? null : () => _remove([item]),
                  icon: Icon(Icons.delete_outline, size: 18),
                  label: Text('Remove'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _quantityButton(CartItem item, int delta) => IconButton(
    key: ValueKey('qty_${item.id}_$delta'),
    tooltip: delta < 0 ? 'Decrease quantity' : 'Increase quantity',
    constraints: BoxConstraints(minWidth: 44, minHeight: 44),
    style: IconButton.styleFrom(
      backgroundColor: AppColors.pinkSoft,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ),
    onPressed:
        _busy ||
            !item.product.available ||
            item.product.onVacation ||
            item.availableStock <= 0 ||
            (delta < 0
                ? item.quantity <= 1
                : item.quantity >= item.availableStock)
        ? null
        : () => _quantity(item, delta),
    icon: Icon(delta < 0 ? Icons.remove : Icons.add, size: 18),
  );
  Widget _footer() => Material(
    color: AppColors.white,
    elevation: 3,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(12, 4, 12, 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                _checkbox(_items!, 'Select all cart items'),
                Text('Select All'),
                Spacer(),
                Flexible(
                  child: Text(
                    '${_chosen.length} products · ${_chosen.fold(0, (sum, i) => sum + i.quantity)} items selected',
                    textAlign: TextAlign.end,
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Selected subtotal',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                      Text(
                        _money(_total(_chosen)),
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                          color: AppColors.pink,
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(width: 12),
                Flexible(
                  child: FilledButton(
                    onPressed: _busy || _chosen.isEmpty ? null : _checkout,
                    style: FilledButton.styleFrom(minimumSize: Size(120, 48)),
                    child: _busy
                        ? SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text('Checkout'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}
