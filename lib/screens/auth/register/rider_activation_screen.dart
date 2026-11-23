import 'package:flutter/material.dart';

import '../../../services/backend_service.dart';
import '../../../utils/friendly_error.dart';
import '../../../utils/validators.dart';
import '../../../widgets/auth_text_field.dart';
import '../../../widgets/hero_auth_scaffold.dart';
import '../../../widgets/primary_button.dart';

/// Matches the website's logistics invitation and password activation flow.
class RiderActivationScreen extends StatefulWidget {
  final String? invitation;
  const RiderActivationScreen({super.key, this.invitation});
  @override
  State<RiderActivationScreen> createState() => _RiderActivationScreenState();
}

class _RiderActivationScreenState extends State<RiderActivationScreen> {
  final _form = GlobalKey<FormState>();
  late final _invitation = TextEditingController(text: widget.invitation);
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _busy = false;
  bool _activated = false;
  String? _error;

  @override
  void dispose() {
    _invitation.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _activate() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await BackendService.client.request('auth/activate-rider', {
        'invitation': _invitation.text.trim(),
        'password': _password.text,
        'password_confirmation': _confirmation.text,
      });
      if (mounted) setState(() => _activated = true);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => HeroAuthScaffold(
    child: Form(
      key: _form,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Activate your rider account',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 12),
          if (_activated)
            const Text('Your rider account is active. You can now sign in.')
          else ...[
            const Text(
              'Your logistics company registers your details, license and vehicle, then emails your invitation. Paste that link here and set your password, just like the website.',
            ),
            const SizedBox(height: 20),
            AuthTextField(
              controller: _invitation,
              label: 'Activation link from email',
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Paste your invitation link.'
                  : null,
            ),
            const SizedBox(height: 12),
            AuthTextField(
              controller: _password,
              label: 'Password',
              obscureText: true,
              validator: Validators.password,
            ),
            const SizedBox(height: 8),
            const Text(
              'Use at least 8 characters with uppercase, lowercase, a number and a symbol.',
            ),
            const SizedBox(height: 12),
            AuthTextField(
              controller: _confirmation,
              label: 'Confirm password',
              obscureText: true,
              validator: (value) =>
                  value == _password.text ? null : 'Passwords do not match.',
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 20),
            PrimaryButton(
              label: 'Activate account',
              loading: _busy,
              onPressed: _busy ? null : _activate,
            ),
          ],
          TextButton(
            onPressed: _busy ? null : () => Navigator.of(context).pop(),
            child: const Text('Back to account options'),
          ),
        ],
      ),
    ),
  );
}
