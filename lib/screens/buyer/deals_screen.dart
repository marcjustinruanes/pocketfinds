import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/product.dart';
import '../../services/backend_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/product_image.dart';
import 'product_detail_screen.dart';

class DealsScreen extends StatefulWidget {
  final AppUser user;
  final BackendClient? client;
  const DealsScreen({super.key, required this.user, this.client});
  @override
  State<DealsScreen> createState() => _DealsScreenState();
}

class _DealsScreenState extends State<DealsScreen>
    with WidgetsBindingObserver, DeliveryRefresh<DealsScreen> {
  List<dynamic> _items = [];
  int _page = 0;
  bool _loading = false, _more = false;
  String? _error;
  final Set<int> _saving = {}, _saved = {};
  late final _client = widget.client ?? BackendService.client;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Future<void> refreshDeliveryData() => _load();

  Future<void> _load({bool next = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _client.request('catalog/deals', {
        'page': next ? _page + 1 : 1,
      }) as Map;
      if (!mounted) return;
      setState(() {
        _items = next
            ? [..._items, ...data['items'] as List]
            : data['items'] as List;
        _page = data['page'] as int;
        _more = data['has_more'] == true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _remind(int id) async {
    if (_saving.contains(id)) return;
    setState(() => _saving.add(id));
    try {
      await _client.request('market/reminder', {'sale_id': id});
      if (mounted) setState(() => _saved.add(id));
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Deals')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Store discounts and flash sales. Prices and promotional stock are checked again at checkout.',
          ),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null) ...[
            Text(_error!),
            TextButton(onPressed: () => _load(), child: const Text('Retry')),
          ],
          if (!_loading && _error == null && _items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text('No deals available right now.'),
            ),
          for (final item in _items) _card(item as Map),
          if (_more)
            TextButton(
              onPressed: _loading ? null : () => _load(next: true),
              child: const Text('Load more'),
            ),
        ],
      ),
    ),
  );

  Widget _card(Map item) {
    final sale = item['sale'] as Map?;
    final start = DateTime.tryParse('${sale?['starts_at']}');
    final upcoming = start != null && start.isAfter(DateTime.now());
    final id = sale == null ? null : (sale['id'] as num).toInt();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: SizedBox(
                width: 64,
                height: 64,
                child: ProductImage(
                  imageUrl: Product.resolveImageUrl(item['image'] as String?),
                ),
              ),
              title: Text('${item['name']}'),
              subtitle: Text(
                '${item['seller']}\n${formatPeso(double.parse('${item['price']}'))}',
              ),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ProductDetailScreen(
                    user: widget.user,
                    productId: '${item['id']}',
                  ),
                ),
              ),
            ),
            if (sale != null)
              Text(
                upcoming
                    ? 'Upcoming flash sale: ${formatPeso(double.parse('${sale['price']}'))} · starts ${start.toLocal()}'
                    : 'Flash sale · ${sale['remaining']} promotional units remaining',
              ),
            if (id != null)
              TextButton(
                onPressed: _saving.contains(id) || _saved.contains(id)
                    ? null
                    : () => _remind(id),
                child: Text(
                  _saved.contains(id) ? 'Reminder saved' : 'Remind me',
                ),
              ),
          ],
        ),
      ),
    );
  }
}
