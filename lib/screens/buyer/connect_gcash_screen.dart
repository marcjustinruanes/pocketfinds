import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../utils/friendly_error.dart';

class ConnectGcashScreen extends StatefulWidget {
  final BackendClient? client;
  const ConnectGcashScreen({super.key, this.client});
  @override
  State<ConnectGcashScreen> createState() => _ConnectGcashScreenState();
}

class _ConnectGcashScreenState extends State<ConnectGcashScreen> {
  final _name = TextEditingController();
  final _number = TextEditingController();
  final _code = TextEditingController();
  late final _client = widget.client ?? BackendService.client;
  bool _sent = false, _busy = false;
  int _remaining = 0;
  String? _error;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _name.dispose();
    _number.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit(bool verify) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client.request(
        'website-buyer/payment-${verify ? 'verify' : 'send-code'}',
        verify
            ? {'otp': _code.text.trim()}
            : {
                'type': 'gcash',
                'account_name': _name.text.trim(),
                'account_number': _number.text.trim(),
              },
      );
      if (!mounted) return;
      if (verify) {
        Navigator.pop(context, true);
        return;
      }
      setState(() {
        _sent = true;
        _remaining = 60;
      });
      _timer?.cancel();
      _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) {
          timer.cancel();
          return;
        }
        setState(() => _remaining--);
        if (_remaining == 0) timer.cancel();
      });
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Connect GCash')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'Verify this GCash account using the code sent to your registered email. Connecting an account does not verify an order payment.',
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _name,
          readOnly: _sent || _busy,
          decoration: const InputDecoration(labelText: 'Account name'),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _number,
          readOnly: _sent || _busy,
          keyboardType: TextInputType.phone,
          decoration: const InputDecoration(
            labelText: 'GCash number (09XXXXXXXXX)',
          ),
        ),
        if (_sent) ...[
          const SizedBox(height: 16),
          TextField(
            controller: _code,
            keyboardType: TextInputType.number,
            maxLength: 6,
            decoration: const InputDecoration(labelText: '6-digit email code'),
          ),
        ],
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: _busy ? null : () => _submit(_sent),
          child: Text(
            _busy
                ? 'Please wait...'
                : _sent
                ? 'Verify and connect'
                : 'Send code',
          ),
        ),
        if (_sent) ...[
          TextButton(
            onPressed: _busy || _remaining > 0 ? null : () => _submit(false),
            child: Text(
              _remaining > 0 ? 'Resend in ${_remaining}s' : 'Resend code',
            ),
          ),
          TextButton(
            onPressed: _busy
                ? null
                : () => setState(() {
                    _sent = false;
                    _code.clear();
                    _error = null;
                  }),
            child: const Text('Change account details'),
          ),
        ],
      ],
    ),
  );
}
