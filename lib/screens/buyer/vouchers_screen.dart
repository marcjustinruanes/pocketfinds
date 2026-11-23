import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';

class VouchersScreen extends StatefulWidget {
  final bool select;
  final bool websiteCheckout;
  const VouchersScreen({
    super.key,
    this.select = false,
    this.websiteCheckout = false,
  });
  @override
  State<VouchersScreen> createState() => _VouchersScreenState();
}

class _VouchersScreenState extends State<VouchersScreen> {
  List<Map<String, dynamic>>? _items;
  String? _error;
  int? _busy;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final rows = await BackendService.client.request('market/vouchers', {
        if (widget.websiteCheckout) 'website_checkout': true,
      });
      if (mounted) {
        setState(() {
          _items = List<Map<String, dynamic>>.from(rows as List);
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    }
  }

  Future<void> _claim(Map<String, dynamic> v) async {
    setState(() => _busy = v['id'] as int);
    try {
      await BackendService.client.request('market/claim', {
        'voucher_id': v['id'],
        if (widget.websiteCheckout) 'website_checkout': true,
      });
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  double _n(dynamic v) => double.tryParse('$v') ?? 0;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Voucher wallet')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_items == null && _error == null) const LinearProgressIndicator(),
          if (_error != null) ...[
            Text(_error!),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
          if (_items?.isEmpty == true)
            const Text('No vouchers are currently available.'),
          for (final v in _items ?? <Map<String, dynamic>>[])
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      '${v['code']} - ${v['kind']}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text(
                      v['discount_type'] == 'percent'
                          ? '${v['amount']}% off'
                          : '${formatPeso(_n(v['amount']))} off',
                    ),
                    Text('Minimum spend ${formatPeso(_n(v['minimum_spend']))}'),
                    if (v['cap'] != null)
                      Text('Maximum discount ${formatPeso(_n(v['cap']))}'),
                    Text('Starts ${v['starts_at']}\nEnds ${v['ends_at']}'),
                    Text('Uses per buyer: ${v['per_buyer_limit']}'),
                    if (v['claim_id'] != null)
                      const Text('Claimed')
                    else
                      OutlinedButton(
                        onPressed: _busy != null ? null : () => _claim(v),
                        child: Text(
                          _busy == v['id'] ? 'Claiming...' : 'Claim voucher',
                        ),
                      ),
                    if (widget.select && v['claim_id'] != null)
                      FilledButton(
                        onPressed: () => Navigator.pop(context, '${v['code']}'),
                        child: const Text('Use voucher'),
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}
