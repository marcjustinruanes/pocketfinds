import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';
import '../../utils/formatters.dart';
import '../../widgets/titled_card.dart';

class RiderCodCard extends StatefulWidget {
  final Map<String, dynamic> data;
  final Future<void> Function() onRefresh;
  const RiderCodCard({super.key, required this.data, required this.onRefresh});
  @override
  State<RiderCodCard> createState() => _RiderCodCardState();
}

class _RiderCodCardState extends State<RiderCodCard> {
  final _amount = TextEditingController(), _reference = TextEditingController();
  String _request = DeliveryService.requestId();
  Map<String, dynamic>? _submitted;
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    super.dispose();
  }

  Future<void> _remit() async {
    final amount = double.tryParse(_amount.text);
    if (amount == null || amount <= 0 || _reference.text.trim().isEmpty) {
      setState(
        () => _error = 'Enter the remitted amount and transaction reference.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      _submitted ??= {
        'request_id': _request,
        'amount': amount,
        'reference': _reference.text.trim(),
      };
      await BackendService.client.request('rider/remit', _submitted);
      _submitted = null;
      _request = DeliveryService.requestId();
      _amount.clear();
      _reference.clear();
      await widget.onRefresh();
    } catch (e) {
      if (e is DatabaseException &&
          e.statusCode != null &&
          e.statusCode! < 500) {
        _submitted = null;
        _request = DeliveryService.requestId();
      }
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => TitledCard(
    title: 'COD to remit',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Outstanding: ${formatPeso(double.tryParse('${widget.data['outstanding']}') ?? 0)}',
        ),
        const Text(
          'Remit by the end of the day. Your balance changes after logistics confirms actual receipt. A short payment leaves the difference outstanding.',
        ),
        for (final job in (widget.data['jobs'] as List? ?? []).where(
          (j) => (double.tryParse('${j['cod_amount']}') ?? 0) > 0,
        ))
          Text(
            '${job['tracking_number']}: ${formatPeso(double.parse('${job['cod_amount']}'))}',
          ),
        for (final r in widget.data['remittances'] as List? ?? [])
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(
              '${r['status']} · ${formatPeso(double.parse('${r['amount']}'))}',
            ),
            subtitle: Text('${r['reference']}'),
          ),
        TextField(
          controller: _amount,
          enabled: !_busy && _submitted == null,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Amount remitted'),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _reference,
          enabled: !_busy && _submitted == null,
          decoration: const InputDecoration(
            labelText: 'Reference / receipt number',
          ),
        ),
        const SizedBox(height: 16),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        FilledButton(
          style: FilledButton.styleFrom(
            minimumSize: const Size(48, 48),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          ),
          onPressed: _busy ? null : _remit,
          child: Text(
            _busy
                ? 'Submitting…'
                : _submitted == null
                ? 'Submit remittance'
                : 'Retry saved remittance',
          ),
        ),
      ],
    ),
  );
}
