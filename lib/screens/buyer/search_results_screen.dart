import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/product.dart';
import '../../services/backend_service.dart';
import '../../services/cart_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_search_bar.dart';
import '../../widgets/product_grid.dart';
import '../../widgets/app_snackbar.dart';
import 'product_detail_screen.dart';
import 'shop_screen.dart';

class SearchResultsScreen extends StatefulWidget {
  final AppUser user;
  final String query;
  final int? sellerId;
  final int? initialCategory;
  const SearchResultsScreen({
    super.key,
    required this.user,
    required this.query,
    this.sellerId,
    this.initialCategory,
  });
  @override
  State<SearchResultsScreen> createState() => _SearchResultsScreenState();
}

class _SearchResultsScreenState extends State<SearchResultsScreen> {
  final _min = TextEditingController(),
      _max = TextEditingController(),
      _location = TextEditingController();
  final _cart = CartService();
  List<Product>? _items;
  Object? _error;
  late String _query = widget.query;
  String _sort = 'latest';
  bool _fourStars = false, _more = false, _loading = false;
  int _page = 1, _generation = 0;
  String? _suggestion;
  List<dynamic> _shops = [];
  late int? _category = widget.initialCategory;
  late final _categories = BackendService.client
      .from('categories')
      .select('id,name')
      .order('name');
  @override
  void initState() {
    super.initState();
    _search();
  }

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _search({bool next = false}) async {
    if (next && _loading) return;
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
      if (!next) {
        _page = 1;
        _items = null;
      }
    });
    try {
      final result = await BackendService.client.request('catalog/search', {
        'query': _query,
        'minimum': _min.text.isEmpty ? null : double.tryParse(_min.text) ?? -1,
        'maximum': _max.text.isEmpty ? null : double.tryParse(_max.text) ?? -1,
        'location': _location.text.trim(),
        'rating': _fourStars ? 4 : null,
        'sort': _sort,
        'seller_id': widget.sellerId,
        'category_id': _category,
        'page': next ? _page + 1 : 1,
      });
      if (!mounted || generation != _generation) return;
      final products = (result['items'] as List)
          .map(
            (r) => Product.fromRow(
              row: Map<String, dynamic>.from(r as Map),
              seller: Map<String, dynamic>.from(r),
            ),
          )
          .toList();
      setState(() {
        _items = next ? [...?_items, ...products] : products;
        _page = result['page'] as int;
        _more = result['has_more'] == true;
        _suggestion = result['suggestion'] as String?;
        if (!next) _shops = result['shops'] as List? ?? [];
      });
    } catch (e) {
      if (mounted && generation == _generation) setState(() => _error = e);
    } finally {
      if (mounted && generation == _generation) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _add(Product p) async {
    if (p.hasVariations) {
      _open(p);
      return;
    }
    try {
      await _cart.addToCart(buyerId: widget.user.id, product: p, quantity: 1);
      if (mounted) AppSnackBar.success(context, 'Added to cart');
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  void _open(Product p) => Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => ProductDetailScreen(user: widget.user, productId: p.id),
    ),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: SizedBox(
        height: 44,
        child: AppSearchBar(
          initialValue: _query,
          onChanged: (_) {},
          onSubmitted: (v) {
            FocusScope.of(context).unfocus();
            _query = v;
            _search();
          },
        ),
      ),
    ),
    body: RefreshIndicator(
      onRefresh: () => _search(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ExpansionTile(
            title: const Text('Filter results'),
            children: [
              FutureBuilder<List<Map<String, dynamic>>>(
                future: _categories,
                builder: (context, snap) => DropdownButtonFormField<int>(
                  key: ValueKey(_category),
                  initialValue: _category,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('All categories'),
                    ),
                    for (final c in snap.data ?? [])
                      DropdownMenuItem(
                        value: c['id'] as int,
                        child: Text('${c['name']}'),
                      ),
                  ],
                  onChanged: (v) => setState(() => _category = v),
                ),
              ),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _min,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Minimum price',
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _max,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Maximum price',
                      ),
                    ),
                  ),
                ],
              ),
              TextField(
                controller: _location,
                decoration: const InputDecoration(
                  labelText: 'Seller city / province',
                ),
              ),
              CheckboxListTile(
                value: _fourStars,
                onChanged: (v) => setState(() => _fourStars = v ?? false),
                title: const Text('Rating 4 stars and above'),
              ),
              FilledButton(
                onPressed: () => _search(),
                child: const Text('Apply filters'),
              ),
              TextButton(
                onPressed: () {
                  _min.clear();
                  _max.clear();
                  _location.clear();
                  setState(() {
                    _fourStars = false;
                    _category = null;
                  });
                  _search();
                },
                child: const Text('Clear filters'),
              ),
            ],
          ),
          DropdownButtonFormField<String>(
            initialValue: _sort,
            decoration: const InputDecoration(labelText: 'Sort by'),
            items: const [
              DropdownMenuItem(value: 'latest', child: Text('Latest')),
              DropdownMenuItem(
                value: 'price_low',
                child: Text('Price: low to high'),
              ),
              DropdownMenuItem(
                value: 'price_high',
                child: Text('Price: high to low'),
              ),
              DropdownMenuItem(
                value: 'best_selling',
                child: Text('Best selling'),
              ),
            ],
            onChanged: (v) {
              _sort = v!;
              _search();
            },
          ),
          if (_shops.isNotEmpty)
            Card(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.all(16),
                    child: Text('Matching stores'),
                  ),
                  for (final shop in _shops)
                    ListTile(
                      leading: const Icon(Icons.storefront_outlined),
                      title: Text(
                        '${shop['business_name'] ?? shop['username']}',
                      ),
                      subtitle: Text(
                        [
                          shop['municipality'],
                          shop['province'],
                        ].whereType<String>().join(', '),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ShopScreen(
                            user: widget.user,
                            sellerId: shop['id'] as int,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          if (_suggestion != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text('Showing matches for $_suggestion'),
            ),
          if (_error != null) ...[
            Text(friendlyError(_error!)),
            TextButton(onPressed: () => _search(), child: const Text('Retry')),
          ],
          if (_loading) const LinearProgressIndicator(),
          ProductGrid(
            products: _items,
            onTap: _open,
            onAddToCart: _add,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            emptyMessage: 'No matching products.',
          ),
          if (_more)
            TextButton(
              onPressed: _loading ? null : () => _search(next: true),
              child: const Text('Load more'),
            ),
        ],
      ),
    ),
  );
}
