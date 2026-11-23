import 'dart:async';

import 'package:flutter/material.dart';

import '../../../services/auth_service.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_typography.dart';
import '../../../utils/friendly_error.dart';
import '../../../utils/validators.dart';
import '../../../widgets/auth_text_field.dart';
import '../../../widgets/hero_auth_scaffold.dart';
import '../../../widgets/primary_button.dart';
import '../../../widgets/app_snackbar.dart';
import '../login_screen.dart';

class VerifyRegistrationScreen extends StatefulWidget {
  final String token;
  final String email;
  const VerifyRegistrationScreen({
    super.key,
    required this.token,
    required this.email,
  });
  @override
  State<VerifyRegistrationScreen> createState() =>
      _VerifyRegistrationScreenState();
}

class _VerifyRegistrationScreenState extends State<VerifyRegistrationScreen> {
  final _code = TextEditingController();
  final _form = GlobalKey<FormState>();
  final _auth = AuthService();
  Timer? _timer;
  int _seconds = 60;
  bool _busy = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  void _startTimer() {
    _timer?.cancel();
    _seconds = 60;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() => _seconds--);
      if (_seconds <= 0) timer.cancel();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    super.dispose();
  }

  Future<void> _resend() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _auth.resendRegistration(widget.token);
      if (mounted) {
        setState(_startTimer);
        AppSnackBar.success(
          context,
          'A new code was sent. Check your inbox and spam folder.',
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _auth.verifyRegistration(widget.token, _code.text);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Application submitted'),
          content: const Text(
            'Your email is verified. Please wait for administrator or logistics-center approval. The decision will be sent to your email. You can sign in after approval.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Got it'),
            ),
          ],
        ),
      );
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => route.isFirst,
      );
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
          Icon(
            Icons.mark_email_read_outlined,
            color: AppColors.pinkDark,
            size: 42,
          ),
          const SizedBox(height: 20),
          Text(
            'Verify your email',
            style: AppTypography.pageTitle,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 10),
          Text(
            'Enter the 6-digit code sent to ${widget.email}. The code expires in 5 minutes.',
            style: AppTypography.body,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Text(_error!, style: TextStyle(color: AppColors.danger)),
            ),
          AuthTextField(
            label: 'Verification code',
            controller: _code,
            keyboardType: TextInputType.number,
            validator: Validators.otpCode,
          ),
          const SizedBox(height: 20),
          PrimaryButton(
            label: 'Verify email',
            loading: _busy,
            onPressed: _verify,
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: _busy || _seconds > 0 ? null : _resend,
            child: Text(
              _seconds > 0 ? 'Resend code in ${_seconds}s' : 'Send a new code',
            ),
          ),
          Text(
            'Already registered? Sign in to resume verification if you close this screen.',
            style: AppTypography.caption,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}
