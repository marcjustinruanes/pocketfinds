import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../services/buyer_photo_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';
import '../../utils/formatters.dart';
import '../../widgets/buyer_photo_picker.dart';

class OrderAftercareScreen extends StatefulWidget {
  final String orderId;
  const OrderAftercareScreen({super.key, required this.orderId});
  @override
  State<OrderAftercareScreen> createState() => _OrderAftercareScreenState();
}

class _OrderAftercareScreenState extends State<OrderAftercareScreen>
    with WidgetsBindingObserver, DeliveryRefresh<OrderAftercareScreen> {
  @override
  Future<void> refreshDeliveryData() => _load();
  Map<String, dynamic>? _data;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await BackendService.client.request('aftercare/details', {
        'order_id': widget.orderId,
      });
      if (mounted) {
        setState(() {
          _data = Map<String, dynamic>.from(data as Map);
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    }
  }

  Future<void> _edit({
    int? item,
    Map<String, dynamic>? review,
    String? reviewType,
  }) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _AftercareForm(
          orderId: widget.orderId,
          items: List<Map<String, dynamic>>.from(_data!['items'] as List),
          item: item,
          review: review,
          reviewType: reviewType,
          prepaid: _data!['prepaid'] == true,
        ),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final request = _data?['return'] as Map?;
    final refund = _data?['refund'] as Map?;
    final reviews = _data?['reviews'] as List? ?? [];
    final items = _data?['items'] as List? ?? [];
    return Scaffold(
      appBar: AppBar(title: const Text('Returns and item reviews')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (_data == null && _error == null)
              const LinearProgressIndicator(),
            if (_error != null) ...[
              Text(_error!),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ],
            if (refund != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(
                    'Repayment: ${refund['status']}\n${formatPeso(double.tryParse('${refund['amount']}') ?? 0)}\n${refund['reference'] ?? 'Actual repayment is not yet confirmed.'}',
                  ),
                ),
              ),
            if (request != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Return status: ${request['status']}',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text('${request['reason']}\n${request['description']}'),
                      for (final photo in request['photos'] as List? ?? [])
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Image.network(
                            BackendService.client.storage
                                .from('return-evidence')
                                .getPublicUrl('$photo'),
                            height: 180,
                            errorBuilder: (_, _, _) => const Text(
                              'Return photo unavailable. Pull to refresh.',
                            ),
                          ),
                        ),
                      Text(
                        'Requested amount: ${formatPeso(double.tryParse('${request['amount']}') ?? 0)}',
                      ),
                      if (request['decision_reason'] != null)
                        Text('Decision: ${request['decision_reason']}'),
                      if (request['refund_reference'] != null)
                        Text(
                          'Repayment reference: ${request['refund_reference']}',
                        ),
                      const Text(
                        'A request or approval is not confirmation of a refund. Actual repayment must be recorded by authorized staff.',
                      ),
                      if (_data?['can_escalate'] == true)
                        TextButton(
                          onPressed: () async {
                            try {
                              await BackendService.client.request(
                                'aftercare/escalate',
                                {'order_id': widget.orderId},
                              );
                              await _load();
                            } catch (e) {
                              if (mounted) {
                                setState(() => _error = friendlyError(e));
                              }
                            }
                          },
                          child: const Text('Escalate dispute'),
                        ),
                    ],
                  ),
                ),
              ),
            if (_data?['can_return'] == true)
              FilledButton(
                onPressed: () => _edit(),
                child: const Text('Request return / refund'),
              ),
            const SizedBox(height: 20),
            if (_data?['can_review'] == true)
              for (final type in ['shop', 'rider'])
                OutlinedButton(
                  onPressed: () {
                    final existing = (_data?['service_reviews'] as List? ?? [])
                        .where((r) => r['review_type'] == type)
                        .toList();
                    _edit(
                      reviewType: type,
                      review: existing.isEmpty
                          ? null
                          : Map<String, dynamic>.from(existing.first as Map),
                    );
                  },
                  child: Text('Review $type'),
                ),
            for (final r in _data?['service_reviews'] as List? ?? []) ...[
              Text(
                '${r['review_type']} review: ${r['rating']}/5 · ${r['comment'] ?? ''}',
              ),
              for (final media in r['media'] as List? ?? [])
                if (media['type'] == 'image')
                  Image.network(
                    BackendService.client.storage
                        .from('review-media')
                        .getPublicUrl('${media['path']}'),
                    height: 160,
                    errorBuilder: (_, _, _) => const Text(
                      'Review photo unavailable. Pull to refresh.',
                    ),
                  ),
            ],
            for (var i = 0; i < items.length; i++)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Builder(
                    builder: (context) {
                      final found = reviews
                          .where((r) => r['item_key'] == i)
                          .toList();
                      final review = found.isEmpty
                          ? null
                          : Map<String, dynamic>.from(found.first as Map);
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            '${items[i]['name']}\n${items[i]['variation_value'] ?? ''}',
                          ),
                          if (review != null) ...[
                            Text(
                              '${review['rating']}/5\n${review['comment'] ?? ''}',
                            ),
                            Wrap(
                              spacing: 8,
                              children: [
                                for (final path
                                    in review['photos'] as List? ?? [])
                                  Image.network(
                                    BackendService.client.storage
                                        .from('review-media')
                                        .getPublicUrl('$path'),
                                    width: 80,
                                    height: 80,
                                    errorBuilder: (_, _, _) =>
                                        const Icon(Icons.broken_image_outlined),
                                  ),
                              ],
                            ),
                          ],
                          if (_data?['can_review'] == true &&
                              (review == null || review['edited_at'] == null))
                            TextButton(
                              onPressed: () => _edit(item: i, review: review),
                              child: Text(
                                review == null
                                    ? 'Review item'
                                    : 'Edit review (once)',
                              ),
                            ),
                        ],
                      );
                    },
                  ),
                ),
              ),
            if (_data?['can_review'] != true)
              const Text(
                'Reviews are available for 30 days after completion. Returns are available within seven days after delivery, including completed orders.',
              ),
          ],
        ),
      ),
    );
  }
}

class _AftercareForm extends StatefulWidget {
  final String orderId;
  final List<Map<String, dynamic>> items;
  final int? item;
  final Map<String, dynamic>? review;
  final bool prepaid;
  final String? reviewType;
  const _AftercareForm({
    required this.orderId,
    required this.items,
    this.item,
    this.review,
    required this.prepaid,
    this.reviewType,
  });
  @override
  State<_AftercareForm> createState() => _AftercareFormState();
}

class _AftercareFormState extends State<_AftercareForm> {
  final _form = GlobalKey<FormState>();
  late final _description = TextEditingController(
    text: widget.review?['comment'] as String?,
  );
  final _name = TextEditingController(), _number = TextEditingController();
  final _photos = <BuyerPhoto>[];
  late final _selected = widget.items.asMap().keys.toSet();
  late int _rating = (widget.review?['rating'] as num?)?.toInt() ?? 5;
  String _reason = 'Damaged item';
  String? _error;
  bool _busy = false;
  String _request = DeliveryService.requestId();
  Map<String, dynamic>? _submitted;
  bool get _return => widget.item == null && widget.reviewType == null;
  @override
  void dispose() {
    _description.dispose();
    _name.dispose();
    _number.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy || !_form.currentState!.validate()) return;
    if (_return && (_selected.isEmpty || _photos.isEmpty)) {
      setState(
        () => _error = 'Choose an item and attach at least one evidence photo.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_submitted == null) {
        final paths = <String>[];
        for (final p in _photos) {
          paths.add(
            await BuyerPhotoService.upload(
              p,
              _return ? 'return-evidence' : 'review-media',
            ),
          );
        }
        _submitted = {
          'order_id': widget.orderId,
          'request_id': _request,
          'photos': paths,
          if (_return) ...{
            'item_keys': _selected.toList(),
            'reason': _reason,
            'description': _description.text.trim(),
            'refund_account_name': _name.text.trim(),
            'refund_gcash_number': _number.text.trim(),
          } else ...{
            if (widget.reviewType != null) 'review_type': widget.reviewType,
            'item_key': widget.item,
            'rating': _rating,
            'comment': _description.text.trim(),
            'edit': widget.review != null,
          },
        };
        if (!_return && _photos.isEmpty && widget.review != null) {
          _submitted!['photos'] = widget.review!['photos'];
        }
      }
      await BackendService.client.request(
        _return
            ? 'aftercare/return'
            : widget.reviewType != null
            ? 'aftercare/service-review'
            : 'aftercare/review',
        _submitted,
      );
      DeliveryService.updates.add(null);
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
      if (e is DatabaseException && e.statusCode != null) {
        _submitted = null;
        _request = DeliveryService.requestId();
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
        title: const Text('Discard this form?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Keep editing'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Discard'),
          ),
        ],
      ),
    );
    if (leave == true && mounted) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) _back();
    },
    child: Scaffold(
      appBar: AppBar(
        title: Text(
          _return
              ? 'Request return / refund'
              : widget.reviewType == null
              ? 'Item review'
              : 'Review ${widget.reviewType}',
        ),
        leading: BackButton(onPressed: _back),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (_return) ...[
              for (var i = 0; i < widget.items.length; i++)
                CheckboxListTile(
                  value: _selected.contains(i),
                  onChanged: _busy
                      ? null
                      : (v) => setState(() {
                          if (v == true) {
                            _selected.add(i);
                          } else {
                            _selected.remove(i);
                          }
                        }),
                  title: Text('${widget.items[i]['name']}'),
                ),
              DropdownButtonFormField<String>(
                initialValue: _reason,
                items:
                    [
                          'Damaged item',
                          'Wrong item',
                          'Missing item',
                          'Not as described',
                        ]
                        .map((v) => DropdownMenuItem(value: v, child: Text(v)))
                        .toList(),
                onChanged: (v) => _reason = v!,
              ),
            ] else
              DropdownButtonFormField<int>(
                initialValue: _rating,
                decoration: const InputDecoration(labelText: 'Rating'),
                items: [1, 2, 3, 4, 5]
                    .map(
                      (v) =>
                          DropdownMenuItem(value: v, child: Text('$v stars')),
                    )
                    .toList(),
                onChanged: (v) => _rating = v!,
              ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _description,
              maxLength: 500,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: _return
                    ? 'Describe the problem'
                    : 'Review (optional)',
              ),
              validator: (v) => _return && (v ?? '').trim().length < 10
                  ? 'Use at least ten characters.'
                  : null,
            ),
            BuyerPhotoPicker(
              photos: _photos,
              enabled: !_busy && _submitted == null,
            ),
            if (_return && !widget.prepaid) ...[
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'GCash refund account name',
                ),
                validator: (v) => (v ?? '').trim().length < 2
                    ? 'Enter the account name.'
                    : null,
              ),
              TextFormField(
                controller: _number,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'GCash refund number (09XXXXXXXXX)',
                ),
                validator: (v) => RegExp(r'^09\d{9}$').hasMatch(v ?? '')
                    ? null
                    : 'Enter a valid GCash number.',
              ),
            ],
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _busy ? null : _save,
              child: Text(
                _busy
                    ? 'Submitting...'
                    : _submitted != null
                    ? 'Retry same submission'
                    : 'Submit',
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
