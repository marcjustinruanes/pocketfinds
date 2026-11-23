import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../utils/friendly_error.dart';

class VerifyContactChangeScreen extends StatefulWidget {
  final bool phone;
  const VerifyContactChangeScreen({super.key, this.phone = false});
  @override
  State<VerifyContactChangeScreen> createState() =>
      _VerifyContactChangeScreenState();
}

class _VerifyContactChangeScreenState extends State<VerifyContactChangeScreen> {
  final _value = TextEditingController(), _code = TextEditingController();
  String? _ticket, _error;
  bool _sent = false, _busy = false;
  @override
  void dispose() {
    _value.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final client = BackendService.client;
      if (_sent) {
        await client.request(
          widget.phone ? 'auth/confirm-phone' : 'auth/confirm-email',
          {
            if (widget.phone)
              'ticket': _ticket
            else
              'email': _value.text.trim(),
            'code': _code.text.trim(),
          },
        );
        if (mounted) Navigator.pop(context, true);
      } else {
        final result = await client.request(
          widget.phone ? 'auth/phone-code' : 'auth/update',
          {widget.phone ? 'phone' : 'email': _value.text.trim()},
        );
        if (mounted) {
          setState(() {
            _ticket = result['ticket'] as String?;
            _sent = true;
          });
        }
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.phone ? 'Change mobile number' : 'Change email'),
    ),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text(
          widget.phone
              ? 'We will email a code to your current verified address to authorize this number change.'
              : 'Verify the code sent to your new email before your login address changes.',
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _value,
          readOnly: _sent || _busy,
          keyboardType: widget.phone
              ? TextInputType.phone
              : TextInputType.emailAddress,
          decoration: InputDecoration(
            labelText: widget.phone ? 'New mobile number' : 'New email address',
          ),
        ),
        if (_sent) ...[
          const SizedBox(height: 16),
          TextField(
            controller: _code,
            keyboardType: TextInputType.number,
            maxLength: 6,
            autofillHints: const [AutofillHints.oneTimeCode],
            decoration: const InputDecoration(
              labelText: 'Email verification code',
            ),
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
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: Text(
            _busy
                ? 'Please wait...'
                : _sent
                ? 'Verify and save'
                : 'Send code',
          ),
        ),
        if (_sent)
          TextButton(
            onPressed: _busy
                ? null
                : () => setState(() {
                    _sent = false;
                    _code.clear();
                  }),
            child: const Text('Change details / resend code'),
          ),
        const Text(
          'Codes expire after five minutes. Wait one minute before resending.',
        ),
      ],
    ),
  );
}
