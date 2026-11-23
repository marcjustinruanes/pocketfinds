import 'dart:async';

import 'package:flutter/material.dart';

import '../services/backend_service.dart';
import '../utils/formatters.dart';
import '../utils/friendly_error.dart';

class ProductOffers extends StatefulWidget {
  final String productId;
  final bool signedIn;
  const ProductOffers({
    super.key,
    required this.productId,
    required this.signedIn,
  });
  @override
  State<ProductOffers> createState() => _ProductOffersState();
}

class _ProductOffersState extends State<ProductOffers> {
  Map<String, dynamic>? _data;
  String? _message;
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _data?['sale'] != null) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final d = await BackendService.client.request('catalog/details', {
        'product_id': widget.productId,
      });
      if (mounted) setState(() => _data = Map<String, dynamic>.from(d as Map));
    } catch (e) {
      if (mounted) setState(() => _message = friendlyError(e));
    }
  }

  Future<void> _act(String action, Map<String, dynamic> fields) async {
    try {
      await BackendService.client.request('market/$action', fields);
      if (mounted) {
        setState(
          () => _message = action == 'claim'
              ? 'Voucher saved to your wallet.'
              : 'Reminder saved.',
        );
      }
    } catch (e) {
      if (mounted) setState(() => _message = friendlyError(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final sale = _data?['sale'] as Map?;
    final end = DateTime.tryParse('${sale?['ends_at']}');
    final start = DateTime.tryParse('${sale?['starts_at']}');
    final upcoming = start != null && start.isAfter(DateTime.now());
    final left = end?.difference(DateTime.now());
    if (_data?['on_vacation'] != true &&
        _data?['purchase_limit'] == null &&
        sale == null &&
        _message == null) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_data?['on_vacation'] == true)
            const Text('Shop on vacation - purchases are unavailable.'),
          if (_data?['purchase_limit'] != null)
            Text('Purchase limit: ${_data!['purchase_limit']} per buyer'),
          if (sale != null && left != null && left > Duration.zero) ...[
            if (upcoming) Text('Starts: ${start.toLocal()}'),
            Text(
              'Flash sale: ${formatPeso(double.tryParse('${sale['price']}') ?? 0)}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Text(
              'Ends in ${left.inHours}:${(left.inMinutes % 60).toString().padLeft(2, '0')}:${(left.inSeconds % 60).toString().padLeft(2, '0')}',
            ),
            if (!upcoming)
              Text(
                '${_data?['flash_remaining']} flash units remaining. Extra units use the current regular or store-sale price.',
              ),
            TextButton(
              onPressed: widget.signedIn
                  ? () => _act('reminder', {'sale_id': sale['id']})
                  : null,
              child: const Text('Remind me'),
            ),
          ],
          if (_message != null) Text(_message!),
        ],
      ),
    );
  }
}
