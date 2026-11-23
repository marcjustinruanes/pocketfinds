import 'package:flutter/material.dart';

import '../../services/delivery_service.dart';
import '../../services/rider_web_module_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';

class RiderPayoutsScreen extends StatefulWidget {
  final RiderWebModuleService? service;
  const RiderPayoutsScreen({super.key, this.service});
  @override
  State<RiderPayoutsScreen> createState() => _RiderPayoutsScreenState();
}

class _RiderPayoutsScreenState extends State<RiderPayoutsScreen>
    with WidgetsBindingObserver, DeliveryRefresh<RiderPayoutsScreen> {
  late final RiderWebModuleService _service;
  late Future<Map<String, dynamic>> _future;
  bool _busy = false;
  @override
  void initState() {
    super.initState();
    _service = widget.service ?? RiderWebModuleService();
    _future = _service.payouts();
  }

  @override
  Future<void> refreshDeliveryData() => _refresh();
  Future<void> _refresh() async {
    final next = _service.payouts();
    setState(() {
      _future = next;
    });
    try {
      await next;
    } catch (_) {
      /* Render the retry state below. */
    }
  }

  Future<void> _account() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => _PayoutAccountScreen(service: _service),
      ),
    );
    if (saved == true && mounted) await _refresh();
  }

  Future<void> _acknowledge(Map item) async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Have you received this payout?'),
        content: Text(
          '${formatPeso((item['amount'] as num).toDouble())}\nReference: ${item['reference'] ?? 'Not recorded'}\n\nConfirm only after the money reaches your account.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('I received it'),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _service.acknowledge('${item['id']}', '${item['source']}');
      if (mounted) await _refresh();
    } catch (error) {
      if (mounted) AppSnackBar.error(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Payout account & receipts')),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(friendlyError(snapshot.error!)),
                TextButton(onPressed: _refresh, child: const Text('Try again')),
              ],
            );
          }
          final accounts = snapshot.data?['destinations'] as List? ?? [];
          final payouts = snapshot.data?['payouts'] as List? ?? [];
          return ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Receiving account',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 8),
                      if (accounts.isEmpty)
                        const Text(
                          'Add a GCash account for admin-processed payouts.',
                        )
                      else ...[
                        Text('${accounts.first['account_name']}'),
                        Text('GCash · ${accounts.first['account_number']}'),
                      ],
                      TextButton(
                        onPressed: _busy ? null : _account,
                        child: Text(
                          accounts.isEmpty
                              ? 'Add receiving account'
                              : 'Update receiving account',
                        ),
                      ),
                      const Text(
                        'Account updates apply to future payouts. Previous payout account records are retained.',
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Payout history',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Text(
                  'Admin processes the transfer. Confirm receipt only when you receive the money.',
                ),
              ),
              if (payouts.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'No payouts recorded yet.',
                    textAlign: TextAlign.center,
                  ),
                ),
              for (final item in payouts)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          formatPeso((item['amount'] as num).toDouble()),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(
                          'Status: ${'${item['status']}'.replaceAll('_', ' ')}',
                        ),
                        Text(
                          'Reference: ${item['reference'] ?? 'Not recorded'}',
                        ),
                        Text(
                          'Release date: ${item['released_at'] ?? 'Not released'}',
                        ),
                        if (item['acknowledged_at'] != null)
                          Text('Receipt confirmed: ${item['acknowledged_at']}')
                        else if (item['can_acknowledge'] == true)
                          OutlinedButton(
                            onPressed: _busy
                                ? null
                                : () => _acknowledge(item as Map),
                            child: const Text('Confirm payout received'),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    ),
  );
}

class _PayoutAccountScreen extends StatefulWidget {
  final RiderWebModuleService service;
  const _PayoutAccountScreen({required this.service});
  @override
  State<_PayoutAccountScreen> createState() => _PayoutAccountScreenState();
}

class _PayoutAccountScreenState extends State<_PayoutAccountScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController(), _number = TextEditingController();
  final _requestId = DeliveryService.requestId();
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _name.dispose();
    _number.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.service.destination(_name.text, _number.text, _requestId);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('GCash receiving account')),
    body: Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Enter the registered name and number of the GCash account where admin should send your payout.',
          ),
          const SizedBox(height: 20),
          TextFormField(
            controller: _name,
            enabled: !_busy,
            decoration: const InputDecoration(
              labelText: 'Registered account name',
            ),
            validator: (value) => (value?.trim().length ?? 0) < 2
                ? 'Enter the registered account name.'
                : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _number,
            enabled: !_busy,
            keyboardType: TextInputType.phone,
            maxLength: 11,
            decoration: const InputDecoration(
              labelText: 'GCash mobile number',
              hintText: '09XXXXXXXXX',
            ),
            validator: (value) =>
                RegExp(r'^09\d{9}$').hasMatch(value?.trim() ?? '')
                ? null
                : 'Enter an 11-digit number starting with 09.',
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: _busy ? null : _save,
            child: Text(_busy ? 'Saving…' : 'Save receiving account'),
          ),
        ],
      ),
    ),
  );
}
