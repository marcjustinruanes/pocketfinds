import 'package:flutter/material.dart';

import 'dart:convert';

import '../../services/website_payment_service.dart';

import '../../models/app_user.dart';
import '../../models/cart_item.dart';
import '../../services/backend_service.dart';
import '../../services/buyer_workflow_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/formatters.dart';
import '../../models/product.dart';
import '../../widgets/product_image.dart';
import '../../utils/friendly_error.dart';
import 'saved_addresses_screen.dart';
import 'gcash_payment_screen.dart';
import 'orders_tab.dart';
import 'vouchers_screen.dart';
import '../shared/address_form_screen.dart';
import 'order_confirmation_screen.dart';
import '../../theme/app_colors.dart';

/// The displayed summary and submitted prices come from the same server quote.
class CheckoutScreen extends StatefulWidget {
  final BackendClient? client;
  final AppUser user;
  final List<CartItem> items;
  final int? initialPaymentMethodId, initialAddressId;
  final String? initialVoucherCode, initialNote;
  final List<String> initialVoucherCodes;
  final bool returnToBuyerWeb;
  const CheckoutScreen({
    super.key,
    this.client,
    required this.user,
    required this.items,
    this.initialPaymentMethodId,
    this.initialAddressId,
    this.initialVoucherCode,
    this.initialVoucherCodes = const [],
    this.initialNote,
    this.returnToBuyerWeb = false,
  });
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  BackendClient get _client => widget.client ?? BackendService.client;
  final _note = TextEditingController(), _code = TextEditingController();
  final _codes = <String>[];
  Map<String, dynamic>? _address, _quote, _lastQuote, _submitted;
  List<Map<String, dynamic>> _methods = [];
  int? _method;
  bool _busy = false, _loading = true, _initialized = false;
  String? _error;
  String _checkoutId = DeliveryService.requestId();
  int _generation = 0;
  Future<Map<String, dynamic>>? _gcashConfiguration;
  @override
  void initState() {
    super.initState();
    _note.text = widget.initialNote ?? '';
    final code = widget.initialVoucherCode?.trim().toUpperCase();
    if (code != null && code.isNotEmpty) _codes.add(code);
    for (final value in widget.initialVoucherCodes) {
      if (value.trim().isNotEmpty && !_codes.contains(value)) _codes.add(value);
    }
    _initialize();
  }

  @override
  void dispose() {
    _note.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _initialize() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait<dynamic>([
        BuyerWorkflowService(client: _client).addresses(),
        _client
            .from('payment_methods')
            .select('id,name,description')
            .eq('is_active', true)
            .order('sort_order'),
      ]);
      if (!mounted) return;
      final addresses = results[0] as List<Map<String, dynamic>>;
      setState(() {
        _address = addresses.isEmpty
            ? null
            : addresses.firstWhere(
                (a) => a['is_default'] == true || a['is_default'] == 1,
                orElse: () => addresses.first,
              );
        _methods = List<Map<String, dynamic>>.from(results[1] as List);
        _initialized = true;
        if (widget.initialAddressId != null) {
          for (final address in addresses) {
            if (address['id'] == widget.initialAddressId) _address = address;
          }
        }
        if (_methods.any(
          (method) => method['id'] == widget.initialPaymentMethodId,
        )) {
          _method = widget.initialPaymentMethodId;
        }
      });
      await _refreshQuote();
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = friendlyError(e);
          _loading = false;
        });
      }
    }
  }

  Map<String, dynamic> get _selection => {
    'cart_ids': widget.items.map((i) => i.id).toList(),
    'delivery_address_id': _address?['id'],
    'voucher_codes': _codes,
    'website_checkout': true,
    'website_payment': true,
  };
  Future<void> _refreshQuote() async {
    if (_submitted != null) return;
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
      _quote = null;
    });
    try {
      final q = await _client.request('market/quote', _selection);
      if (mounted && generation == _generation) {
        setState(() {
          _quote = Map<String, dynamic>.from(q as Map);
          _lastQuote = _quote;
        });
      }
    } catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = friendlyError(e));
      }
    } finally {
      if (mounted && generation == _generation) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _chooseAddress() async {
    final selected = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => SavedAddressesScreen(
          user: widget.user,
          select: true,
          client: _client,
        ),
      ),
    );
    if (selected != null && mounted) {
      setState(() => _address = selected);
      await _refreshQuote();
    }
  }

  Future<void> _voucher([String? value]) async {
    final code = (value ?? _code.text).trim().toUpperCase();
    if (code.isEmpty) return;
    if (!_codes.contains(code)) _codes.add(code);
    _code.clear();
    await _refreshQuote();
  }

  Future<void> _place() async {
    if (_busy || _loading) return;
    if (_address == null) {
      setState(() => _error = 'Choose a delivery address.');
      return;
    }
    if (_method == null) {
      setState(() => _error = 'Choose a payment method.');
      return;
    }
    if (_quote == null && _submitted == null) {
      setState(() => _error = 'Review a valid order summary first.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    _submitted ??= {
      ..._selection,
      'checkout_id': _checkoutId,
      'quote_hash': _quote!['quote_hash'],
      'payment_method_id': _method,
      'buyer_note': _note.text.trim(),
    };
    try {
      final rows = await _client.request('market/checkout', _submitted) as List;
      if (!mounted) return;
      if (rows.any((r) => r['platform_payment'] == true)) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => GcashPaymentScreen(
              startAtSubmission: true,
              orderIds: [for (final row in rows) '${row['id']}'],
            ),
          ),
          result: true,
        );
      } else if (rows.any((r) => r['payment_pending'] == true)) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => GcashPaymentScreen(
              service: WebsitePaymentService(client: _client),
              orderIds: [for (final row in rows) '${row['id']}'],
            ),
          ),
          result: true,
        );
      } else {
        final payment = _methods.firstWhere((m) => m['id'] == _method);
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (confirmationContext) => OrderConfirmationScreen(
              orderNumber: rows.map((r) => r['order_number']).join(', '),
              estimatedDelivery: '',
              orderTotal: _number(_quote?['total']),
              paymentMethod: '${payment['name']}',
              onTrackOrder: () {
                if (widget.returnToBuyerWeb) {
                  Navigator.pop(confirmationContext, true);
                } else {
                  Navigator.of(confirmationContext).pushReplacement(
                    MaterialPageRoute(
                      builder: (_) => OrdersTab(user: widget.user),
                    ),
                  );
                }
              },
            ),
          ),
          result: true,
        );
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
      if (e is DatabaseException &&
          e.statusCode != null &&
          e.statusCode! >= 400 &&
          e.statusCode! < 500) {
        _submitted = null;
        _checkoutId = DeliveryService.requestId();
        // Keep the error visible; buyer explicitly refreshes and reviews the new summary.
        if (mounted) setState(() => _quote = null);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _back() async {
    if (_busy) return;
    final leave = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Leave checkout?'),
        content: const Text(
          'Your cart stays saved. Check order history before starting another checkout if a previous request lost its connection.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Keep checkout'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Leave'),
          ),
        ],
      ),
    );
    if (leave == true && mounted) Navigator.pop(context);
  }

  String _shopName(Map group) {
    final ids = (group['items'] as List).map((i) => i['product_id']).toSet();
    for (final item in widget.items) {
      if (ids.contains(item.productId)) return item.product.sellerName;
    }
    return 'Shop order';
  }

  double _number(dynamic v) => double.tryParse('$v') ?? 0;
  int _cents(dynamic value) {
    final text = value is num ? value.toStringAsFixed(2) : '$value';
    final parts = text.split('.');
    return (int.tryParse(parts.first) ?? 0) * 100 +
        (int.tryParse(
              (parts.length > 1 ? parts[1] : '')
                  .padRight(2, '0')
                  .substring(0, 2),
            ) ??
            0);
  }

  String _money(int cents) => formatPeso(cents / 100);
  int _deliveryDiscount(Map group) => (group['vouchers'] as List? ?? [])
      .where((voucher) => voucher['kind'] == 'shipping')
      .fold<int>(0, (sum, voucher) => sum + _cents(voucher['amount']));
  List<Map<String, dynamic>> get _groups {
    final raw = (_quote ?? _lastQuote)?['orders'];
    return [
      for (final group
          in raw is Map
              ? raw.values
              : raw is List
              ? raw
              : [])
        Map<String, dynamic>.from(group as Map),
    ];
  }

  bool get _addressReady =>
      _address != null &&
      '${_address!['recipient_name'] ?? ''}'.trim().isNotEmpty &&
      RegExp(r'^(09[0-9]{9}|\+639[0-9]{9})$')
          .hasMatch('${_address!['contact_no'] ?? ''}'.replaceAll(' ', '')) &&
      '${_address!['full_address'] ?? ''}'.trim().isNotEmpty &&
      !_groups.any((g) => g['shipping_pending'] == true);

  Future<void> _addAddress() async {
    final saved = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => AddressFormScreen(
          user: widget.user,
          onSaveAddress: (data) =>
              BuyerWorkflowService(client: _client).saveAddress(data),
        ),
      ),
    );
    if (mounted && saved != null) {
      setState(() => _address = saved);
      await _refreshQuote();
    }
  }

  Widget _card(String title, List<Widget> children, {IconData? icon}) =>
      Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 20, color: AppColors.pinkDark),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            Divider(height: 28, color: AppColors.border),
            ...children,
          ],
        ),
      );
  Widget _row(
    String label,
    String value, {
    bool strong = false,
    bool discount = false,
  }) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 3,
          child: Text(
            label,
            style: TextStyle(
              color: strong ? AppColors.textPrimary : AppColors.textMuted,
              fontWeight: strong ? FontWeight.w700 : FontWeight.w400,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          flex: 2,
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              color: discount
                  ? AppColors.success
                  : strong
                  ? AppColors.pinkDark
                  : AppColors.textPrimary,
              fontSize: strong ? 18 : 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
  Widget _product(Map item) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(
            width: 64,
            height: 64,
            child: ProductImage(
              imageUrl:
                  Product.resolveImageUrl(item['img']?.toString()) ??
                  widget.items
                      .where((cart) => cart.productId == item['product_id'])
                      .firstOrNull
                      ?.product
                      .imageUrl,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${item['name']}',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  height: 1.4,
                ),
              ),
              if ('${item['variation_value'] ?? ''}'.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '${item['variation_group'] ?? 'Variation'}: ${item['variation_value']}',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ),
              if (item['flash_sale_id'] != null)
                Text(
                  'Flash sale price',
                  style: TextStyle(fontSize: 12, color: AppColors.pinkDark),
                ),
              const SizedBox(height: 8),
              Text(
                '${_money(_cents(item['price']))} each  x${item['qty']}',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
              const SizedBox(height: 4),
              Text(
                'Subtotal ${_money(_cents(item['price']) * (item['qty'] as num).toInt())}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ],
    ),
  );
  Widget _gcashDetails(
    List<Map<String, dynamic>> groups,
  ) => FutureBuilder<Map<String, dynamic>>(
    future: _gcashConfiguration ??= WebsitePaymentService(client: _client)
        .configuration(),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return Column(
          children: [
            Text(friendlyError(snapshot.error!)),
            TextButton(
              onPressed: () {
                setState(() {
                  _gcashConfiguration = WebsitePaymentService(client: _client)
                      .configuration();
                });
              },
              child: const Text('Reload GCash details'),
            ),
          ],
        );
      }
      if (!snapshot.hasData) return const LinearProgressIndicator();
      final config = snapshot.data!;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 16),
          if (config['qr_data'] != null)
            Image.memory(
              base64Decode('${config['qr_data']}'.split(',').last),
              height: 180,
              fit: BoxFit.contain,
              semanticLabel: 'Official PocketFinds GCash QR code',
            ),
          SelectableText(
            '${config['account_name']}',
            textAlign: TextAlign.center,
          ),
          SelectableText(
            '${config['account_number']}',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          const Text(
            'Place your order before transferring. Send the exact amount and upload your receipt for admin verification.',
          ),
          if (groups.length > 1) ...[
            const SizedBox(height: 8),
            const Text(
              'Each seller order needs a separate transfer and receipt.',
            ),
            for (final group in groups)
              Text('${_shopName(group)}: ${_money(_cents(group['total']))}'),
          ],
        ],
      );
    },
  );
  @override
  Widget build(BuildContext context) {
    final groups = _groups;
    final locked = _busy || _submitted != null;
    final selected = _methods.where((m) => m['id'] == _method).firstOrNull;
    final gcash = '${selected?['name']}'.toLowerCase() == 'gcash';
    final subtotal = groups.fold<int>(
      0,
      (sum, g) => sum + _cents(g['subtotal']),
    );
    final shipping = groups.fold<int>(
      0,
      (sum, g) => sum + _cents(g['shipping_amount']),
    );
    final discounts = groups.fold<int>(
      0,
      (sum, g) => sum + _cents(g['discount_amount']),
    );
    final productDiscount = groups.fold<int>(
      0,
      (sum, g) => sum + _cents(g['seller_discount']),
    );
    final lines = [for (final g in groups) ...g['items'] as List];
    final quantity = lines.fold<int>(
      0,
      (sum, i) => sum + (i['qty'] as num).toInt(),
    );
    final productCount = lines.map((i) => '${i['product_id']}').toSet().length;
    final applied = <String, int>{};
    for (final g in _quote == null ? <Map<String, dynamic>>[] : groups) {
      for (final v in g['vouchers'] as List? ?? []) {
        final code = '${v['code']}';
        applied[code] = (applied[code] ?? 0) + _cents(v['amount']);
      }
    }
    final canPlace =
        !_busy &&
        !_loading &&
        _method != null &&
        (_submitted != null ||
            (_quote != null && groups.isNotEmpty && _addressReady));
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Checkout'),
          leading: BackButton(onPressed: _back),
        ),
        bottomNavigationBar: _initialized
            ? SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    border: Border(top: BorderSide(color: AppColors.border)),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              _loading ? 'Updating total...' : 'Total payment',
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Flexible(
                            child: Text(
                              _quote == null
                                  ? '--'
                                  : _money(_cents(_quote!['total'])),
                              textAlign: TextAlign.end,
                              style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: AppColors.pinkDark,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Text(
                            _error!,
                            style: TextStyle(color: AppColors.danger),
                          ),
                        ),
                      FilledButton(
                        onPressed: canPlace ? _place : null,
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(48, 48),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 14,
                          ),
                        ),
                        child: Text(
                          _busy
                              ? 'Submitting...'
                              : _submitted != null
                              ? 'Retry the same checkout'
                              : 'Place order',
                        ),
                      ),
                      if (_quote == null && !_loading && _submitted == null)
                        TextButton(
                          onPressed: _refreshQuote,
                          child: const Text('Refresh and review summary'),
                        ),
                    ],
                  ),
                ),
              )
            : null,
        body: !_initialized && !_loading
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _error ?? 'Checkout could not load.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: _initialize,
                        child: const Text('Retry checkout'),
                      ),
                    ],
                  ),
                ),
              )
            : RefreshIndicator(
                onRefresh: _refreshQuote,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                  children: [
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.only(bottom: 16),
                        child: LinearProgressIndicator(),
                      ),
                    if (_error != null)
                      _card('Review your checkout', [
                        Text(
                          _error!,
                          style: TextStyle(color: AppColors.danger),
                        ),
                      ], icon: Icons.error_outline),
                    for (final warning in _quote?['warnings'] as List? ?? [])
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          '$warning',
                          style: TextStyle(color: AppColors.warning),
                        ),
                      ),
                    _card('Delivery address', [
                      if (_address == null)
                        const Text(
                          'No delivery address selected. Add a complete address to calculate delivery and place your order.',
                        )
                      else ...[
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              '${_address!['recipient_name']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (_address!['is_default'] == true ||
                                _address!['is_default'] == 1)
                              Chip(
                                label: const Text('Default'),
                                visualDensity: VisualDensity.compact,
                              ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text('${_address!['contact_no']}'),
                        const SizedBox(height: 8),
                        Text(
                          '${_address!['full_address']}',
                          style: const TextStyle(height: 1.5),
                        ),
                        if ('${_address!['postal_code'] ?? ''}'.isNotEmpty)
                          Text('Postal code: ${_address!['postal_code']}'),
                        if ('${_address!['delivery_instructions'] ?? ''}'
                            .isNotEmpty)
                          Text(
                            'Instructions: ${_address!['delivery_instructions']}',
                          ),
                      ],
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          OutlinedButton.icon(
                            onPressed: locked ? null : _chooseAddress,
                            icon: const Icon(
                              Icons.edit_location_alt_outlined,
                              size: 18,
                            ),
                            label: const Text('Change address'),
                          ),
                          TextButton.icon(
                            onPressed: locked ? null : _addAddress,
                            icon: const Icon(Icons.add, size: 18),
                            label: const Text('Add new address'),
                          ),
                        ],
                      ),
                    ], icon: Icons.location_on_outlined),
                    for (final group in groups)
                      _card(_shopName(group), [
                        if (_quote == null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(
                              _loading ? 'Updating prices and delivery...' : 'Last checked details. Refresh and review prices before ordering.',
                              style: TextStyle(color: AppColors.warning),
                            ),
                          ),
                        for (final item in group['items'] as List)
                          _product(item as Map),
                        Divider(height: 24, color: AppColors.border),
                        Text(
                          'Delivery by logistics',
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'The seller arranges the carrier after checkout. Delivery is charged separately for each shop.',
                          style: TextStyle(fontSize: 12, height: 1.4),
                        ),
                        _row(
                          'Product subtotal',
                          _money(_cents(group['subtotal'])),
                        ),
                        _row(
                          'Delivery fee',
                          group['shipping_pending'] == true
                              ? 'Address required'
                              : _money(_cents(group['shipping_amount'])),
                        ),
                        for (final voucher in group['vouchers'] as List? ?? [])
                          _row(
                            '${voucher['kind'] == 'shipping' ? 'Delivery voucher' : 'Voucher'} (${voucher['code']})',
                            '-${_money(_cents(voucher['amount']))}',
                            discount: true,
                          ),
                        if (_deliveryDiscount(group) > 0)
                          _row(
                            'Delivery after vouchers',
                            _money(
                              (_cents(group['shipping_amount']) -
                                      _deliveryDiscount(group))
                                  .clamp(0, _cents(group['shipping_amount'])),
                            ),
                            discount: true,
                          ),
                        if (group['delivery_policy'] is Map &&
                            group['delivery_policy']['label'] != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Text(
                              'Estimated delivery: ${group['delivery_policy']['label']}',
                              style: TextStyle(
                                color: AppColors.textMuted,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        if (group['delivery_estimate'] != null)
                          Text(
                            'Estimated delivery: ${group['delivery_estimate']}',
                          ),
                        Divider(height: 24, color: AppColors.border),
                        _row(
                          'Shop total',
                          _money(_cents(group['total'])),
                          strong: true,
                        ),
                      ], icon: Icons.storefront_outlined),
                    _card('Vouchers & discounts', [
                      if (applied.isEmpty)
                        const Text(
                          'Choose an eligible product or delivery voucher, or enter a code.',
                        ),
                      for (final entry in applied.entries)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Row(
                            children: [
                              Icon(
                                Icons.check_circle_outline,
                                size: 18,
                                color: AppColors.success,
                              ),
                              const SizedBox(width: 8),
                              Expanded(child: Text('${entry.key} applied')),
                              Text(
                                '-${_money(entry.value)}',
                                style: TextStyle(color: AppColors.success),
                              ),
                            ],
                          ),
                        ),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final code in _codes)
                            InputChip(
                              label: Text(code),
                              onDeleted: locked
                                  ? null
                                  : () {
                                      _codes.remove(code);
                                      _refreshQuote();
                                    },
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _code,
                        enabled: !locked && !_loading,
                        textCapitalization: TextCapitalization.characters,
                        decoration: const InputDecoration(
                          labelText: 'Voucher code',
                        ),
                        onSubmitted: locked ? null : _voucher,
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          OutlinedButton(
                            onPressed: locked || _loading
                                ? null
                                : () => _voucher(),
                            child: const Text('Apply code'),
                          ),
                          TextButton(
                            onPressed: locked
                                ? null
                                : () async {
                                    final code = await Navigator.of(context)
                                        .push<String>(
                                          MaterialPageRoute(
                                            builder: (_) => VouchersScreen(
                                              select: true,
                                              websiteCheckout: true,
                                            ),
                                          ),
                                        );
                                    if (mounted && code != null) {
                                      await _voucher(code);
                                    }
                                  },
                            child: const Text('Choose from voucher wallet'),
                          ),
                        ],
                      ),
                      for (final option
                          in _quote?['voucher_options'] as List? ?? [])
                        if (!applied.containsKey('${option['code']}'))
                          Material(
                            color: Colors.transparent,
                            child: ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${option['code']}'),
                              subtitle: Text(
                                option['eligible'] == true
                                    ? 'Eligible for this checkout'
                                    : '${option['reason']}',
                              ),
                              trailing: option['eligible'] == true
                                  ? TextButton(
                                      onPressed: locked || _loading
                                          ? null
                                          : () => _voucher('${option['code']}'),
                                      child: const Text('Apply'),
                                    )
                                  : null,
                            ),
                          ),
                    ], icon: Icons.discount_outlined),
                    _card('Payment', [
                      if (_methods.isEmpty)
                        const Text(
                          'No supported payment methods are available. Please try again later.',
                        ),
                      for (final method in _methods)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Material(
                            color: _method == method['id']
                                ? AppColors.pinkSoft
                                : AppColors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                              side: BorderSide(
                                color: _method == method['id']
                                    ? AppColors.pink
                                    : AppColors.border,
                              ),
                            ),
                            child: ListTile(
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 8,
                              ),
                              leading: Icon(
                                '${method['name']}'.toLowerCase() == 'gcash'
                                    ? Icons.account_balance_wallet_outlined
                                    : Icons.payments_outlined,
                                color: AppColors.pinkDark,
                              ),
                              title: Text(
                                '${method['name']}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(
                                '${method['name']}'.toLowerCase() == 'gcash'
                                    ? 'Pay by GCash and submit a receipt for verification.'
                                    : 'Pay the full order total when your parcel arrives.',
                              ),
                              trailing: Icon(
                                _method == method['id']
                                    ? Icons.radio_button_checked
                                    : Icons.radio_button_unchecked,
                                color: _method == method['id']
                                    ? AppColors.pink
                                    : AppColors.textMuted,
                              ),
                              onTap: locked
                                  ? null
                                  : () => setState(
                                      () => _method = method['id'] as int,
                                    ),
                            ),
                          ),
                        ),
                      if (gcash) _gcashDetails(groups),
                    ], icon: Icons.payment_outlined),
                    _card('Order notes', [
                      TextField(
                        controller: _note,
                        enabled: !locked,
                        maxLength: 200,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'Optional note',
                          hintText: 'Special purchase or delivery instructions',
                        ),
                      ),
                    ], icon: Icons.note_alt_outlined),
                    if (_quote != null)
                      _card('Payment details', [
                        Text(
                          '$productCount products  /  $quantity items  /  ${groups.length} shops',
                        ),
                        const SizedBox(height: 8),
                        _row('Product subtotal', _money(subtotal)),
                        _row('Delivery fee', _money(shipping)),
                        _row(
                          'Product discounts',
                          '-${_money(productDiscount)}',
                          discount: productDiscount > 0,
                        ),
                        _row(
                          'Voucher discounts',
                          '-${_money(discounts - productDiscount)}',
                          discount: discounts > productDiscount,
                        ),
                        _row(
                          'Total discounts (included above)',
                          '-${_money(discounts)}',
                          discount: discounts > 0,
                        ),
                        Divider(height: 24, color: AppColors.border),
                        _row(
                          'Total payment',
                          _money(_cents(_quote!['total'])),
                          strong: true,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Payment: ${selected?['name'] ?? 'Select a payment method'}',
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Product and flash-sale prices already include applicable item reductions. Discounts are applied once. Stock, prices and voucher eligibility are checked again when you place the order.',
                          style: TextStyle(fontSize: 12, height: 1.5),
                        ),
                      ], icon: Icons.receipt_long_outlined),
                    TextButton(
                      onPressed: locked ? null : _back,
                      child: const Text('Back to cart to change items'),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
