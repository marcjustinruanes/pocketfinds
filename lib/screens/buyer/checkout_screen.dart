import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/cart_item.dart';
import '../../services/cart_service.dart';
import '../../services/order_service.dart';
import '../../services/supabase_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/titled_card.dart';

/// One delivery option — mirrors the two flat fees Laravel's
/// `BuyerController::cartShippingOptions()` hardcodes (not DB data, so
/// replicating it as a constant here isn't "hardcoding dynamic data").
class _ShippingOption {
  final String label;
  final String detail;
  final double amount;
  const _ShippingOption(this.label, this.detail, this.amount);
}

const _shippingOptions = [
  _ShippingOption('Standard delivery', 'Usually arrives within a week', 65),
  _ShippingOption('Express delivery', 'Faster delivery for a higher fee', 120),
];

/// Mobile translation of the checkout step of
/// `resources/views/buyer/cart.blade.php` + `BuyerController::checkout()`:
/// review address, pick shipping + payment method, place the order.
class CheckoutScreen extends StatefulWidget {
  final AppUser user;
  final List<CartItem> items;

  const CheckoutScreen({super.key, required this.user, required this.items});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _orderService = OrderService();
  final _cartService = CartService();

  late Future<List<Map<String, dynamic>>> _paymentMethodsFuture;
  int _shippingIndex = 0;
  int? _paymentMethodId;
  bool _placingOrder = false;

  double get _subtotal =>
      widget.items.fold(0, (sum, item) => sum + item.lineTotal);
  double get _shippingAmount => _shippingOptions[_shippingIndex].amount;
  double get _total => _subtotal + _shippingAmount;

  @override
  void initState() {
    super.initState();
    _paymentMethodsFuture = SupabaseService.client
        .from('payment_methods')
        .select('id, name, description')
        .eq('is_active', true)
        .order('sort_order');
  }

  Future<void> _placeOrder() async {
    if (_paymentMethodId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Please select a payment method.')));
      return;
    }
    final address = widget.user.fullAddress;
    if (address.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please add a delivery address in your profile first.'),
        ),
      );
      return;
    }

    setState(() => _placingOrder = true);
    try {
      final paymentMethods = await _paymentMethodsFuture;
      final paymentMethodName = paymentMethods
          .firstWhere((p) => p['id'] == _paymentMethodId)['name'] as String;

      await _orderService.createOrders(
        buyerId: widget.user.id,
        items: widget.items,
        shippingAmount: _shippingAmount,
        paymentMethodId: _paymentMethodId!,
        paymentMethodName: paymentMethodName,
        shippingAddress: {
          'house_no': widget.user.houseNo ?? 'Not provided',
          'street': widget.user.street ?? 'Not provided',
          'barangay': widget.user.barangay ?? 'Not provided',
          'municipality': widget.user.municipality ?? 'Not provided',
          'province': widget.user.province ?? 'Not provided',
        },
      );

      // Only clear items that actually came from the persisted cart —
      // a "Buy Now" item has a blank id since it was never added there.
      final cartBackedIds = widget.items
          .where((i) => i.id.isNotEmpty)
          .map((i) => i.id)
          .toList();
      await _cartService.removeItems(cartBackedIds);

      if (!mounted) return;
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => AlertDialog(
          title: const Text('Order placed!'),
          content: const Text(
            'Your order was submitted successfully. You can track it from '
            'the Orders tab.',
          ),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not place order: ${friendlyError(e)}')));
    } finally {
      if (mounted) setState(() => _placingOrder = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 160),
        children: [
          TitledCard(
            title: 'Delivery address',
            child: Text(
              widget.user.fullAddress.isEmpty
                  ? 'No address on file — add one in your profile.'
                  : widget.user.fullAddress,
              style: TextStyle(
                color: widget.user.fullAddress.isEmpty
                    ? AppColors.danger
                    : AppColors.textPrimary,
              ),
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Order items',
            child: Column(
              children: widget.items.map((item) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${item.product.name}'
                          '${item.variantLabel.isNotEmpty ? ' (${item.variantLabel})' : ''} '
                          '× ${item.quantity}',
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                      Text(formatPeso(item.lineTotal)),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Shipping option',
            child: RadioGroup<int>(
              groupValue: _shippingIndex,
              onChanged: (value) => setState(() => _shippingIndex = value!),
              child: Column(
                children: List.generate(_shippingOptions.length, (index) {
                  final option = _shippingOptions[index];
                  return RadioListTile<int>(
                    contentPadding: EdgeInsets.zero,
                    value: index,
                    title: Text(option.label),
                    subtitle: Text(option.detail),
                    secondary: Text(formatPeso(option.amount)),
                  );
                }),
              ),
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Payment method',
            child: FutureBuilder<List<Map<String, dynamic>>>(
              future: _paymentMethodsFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }
                final methods = snapshot.data ?? [];
                if (methods.isEmpty) {
                  return const Text(
                    'No payment methods are available right now.',
                    style: TextStyle(color: AppColors.textMuted),
                  );
                }
                _paymentMethodId ??= methods.first['id'] as int;
                return RadioGroup<int>(
                  groupValue: _paymentMethodId,
                  onChanged: (value) => setState(() => _paymentMethodId = value),
                  child: Column(
                    children: methods.map((method) {
                      return RadioListTile<int>(
                        contentPadding: EdgeInsets.zero,
                        value: method['id'] as int,
                        title: Text(method['name'] as String),
                        subtitle: method['description'] != null
                            ? Text(method['description'] as String)
                            : null,
                      );
                    }).toList(),
                  ),
                );
              },
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(
            color: AppColors.white,
            boxShadow: AppShadows.md,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SummaryRow('Subtotal', _subtotal),
              SummaryRow('Shipping', _shippingAmount),
              const Divider(),
              SummaryRow('Total', _total, bold: true),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _placingOrder ? null : _placeOrder,
                  child: _placingOrder
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Text('Place Order'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
