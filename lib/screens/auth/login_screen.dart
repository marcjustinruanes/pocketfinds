import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../services/backend_service.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/auth_page_route.dart';
import '../../utils/friendly_error.dart';
import '../../utils/validators.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/auth_text_field.dart';
import '../../widgets/google_sign_in_button.dart';
import '../../widgets/hero_auth_scaffold.dart';
import '../../widgets/primary_button.dart';
import 'account_type_screen.dart';
import 'register/verify_registration_screen.dart';

class LoginScreen extends StatefulWidget {
  final bool embedded;
  final VoidCallback? onCreateAccount;
  const LoginScreen({super.key, this.embedded = false, this.onCreateAccount});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _authService = AuthService();

  bool _submitting = false;
  bool _remember = true;
  bool _googleSubmitting = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_submitting || _googleSubmitting) return;
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      // A successful sign-in fires an auth-state event that the app-wide
      // listener in main.dart reacts to — it resolves the profile (pending/
      // rejected/buyer/rider/unsupported) and navigates accordingly, so
      // there's nothing more to do here on success.
      final response = await _authService.signIn(
        email: _emailController.text,
        password: _passwordController.text,
        remember: _remember,
      );
      _continueVerification(response);
    } on AuthException catch (e) {
      if (mounted) setState(() => _errorMessage = e.message);
    } catch (e) {
      if (mounted) setState(() => _errorMessage = friendlyError(e));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _submitGoogle() async {
    if (_submitting || _googleSubmitting) return;
    setState(() {
      _googleSubmitting = true;
      _errorMessage = null;
    });
    try {
      // Same as _submit: the global auth listener handles navigation once
      // this resolves to a session.
      final response = await _authService.signInWithGoogle();
      if (response != null) _continueVerification(response);
    } on AuthException catch (e) {
      if (mounted) setState(() => _errorMessage = e.message);
    } catch (e) {
      if (mounted) setState(() => _errorMessage = friendlyError(e));
    } finally {
      if (mounted) setState(() => _googleSubmitting = false);
    }
  }

  Future<void> _forgotPassword() async {
    final formKey = GlobalKey<FormState>();
    final controller = TextEditingController(text: _emailController.text);
    final email = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reset your password'),
        content: Form(
          key: formKey,
          child: TextFormField(
            controller: controller,
            keyboardType: TextInputType.emailAddress,
            autofocus: true,
            autofillHints: const [AutofillHints.email],
            validator: Validators.email,
            decoration: const InputDecoration(hintText: 'you@example.com'),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              if (formKey.currentState!.validate()) {
                Navigator.pop(context, controller.text.trim());
              }
            },
            child: const Text('Send link'),
          ),
        ],
      ),
    );

    if (email == null || email.isEmpty || !mounted) return;
    try {
      await _authService.sendPasswordResetEmail(email);
      if (!mounted) return;
      AppSnackBar.success(
        context,
        'Password reset link sent — check your email.',
      );
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(
        context,
        'Could not send reset email: ${friendlyError(e)}',
      );
    }
  }

  void _continueVerification(AuthResponse response) {
    if (!mounted || response.registrationToken == null) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => VerifyRegistrationScreen(
          token: response.registrationToken!,
          email: response.email ?? _emailController.text.trim(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final form = Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (!widget.embedded) ...[
            Text(
              'Welcome back',
              textAlign: TextAlign.left,
              style: GoogleFonts.inter(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Enter your credentials to continue.',
              textAlign: TextAlign.left,
              style: GoogleFonts.inter(
                fontSize: 13,
                color: AppColors.textMuted,
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
          ],
          if (_errorMessage != null) ...[
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: AppColors.dangerSoft,
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(
                  color: AppColors.danger.withValues(alpha: 0.25),
                ),
              ),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: AppColors.danger, size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _errorMessage!,
                      style: AppTypography.caption.copyWith(
                        color: AppColors.danger,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          AuthTextField(
            label: 'Email or username',
            showLabel: !widget.embedded,
            controller: _emailController,
            hint: widget.embedded ? 'Email or username' : 'you@example.com',
            keyboardType: TextInputType.emailAddress,
            textCapitalization: TextCapitalization.none,
            autofillHints: const [AutofillHints.username],
            textInputAction: TextInputAction.next,
            validator: (value) =>
                Validators.required(value, label: 'Email or username'),
          ),
          const SizedBox(height: 16),
          AuthTextField(
            label: 'Password',
            showLabel: !widget.embedded,
            controller: _passwordController,
            hint: widget.embedded ? 'Password' : 'Enter your password',
            obscureText: true,
            autofillHints: const [AutofillHints.password],
            textInputAction: TextInputAction.done,
            onFieldSubmitted: (_) => _submit(),
            validator: (value) => Validators.required(value, label: 'Password'),
          ),
          const SizedBox(height: 6),
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              SizedBox(
                width: 150,
                child: CheckboxListTile(
                  value: _remember,
                  onChanged: _submitting || _googleSubmitting
                      ? null
                      : (value) => setState(() => _remember = value ?? false),
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: const Text(
                    'Remember me',
                    style: TextStyle(fontSize: 12),
                  ),
                ),
              ),
              TextButton(
                onPressed: _submitting ? null : _forgotPassword,
                child: Text(
                  'Forgot password?',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.pinkDark,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          PrimaryButton(
            label: 'Sign in',
            loading: _submitting,
            onPressed: _googleSubmitting ? null : _submit,
          ),
          const SizedBox(height: AppSpacing.xl),
          Row(
            children: [
              Expanded(child: Divider(color: AppColors.border)),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Text(
                  'or',
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    color: AppColors.textMuted,
                  ),
                ),
              ),
              Expanded(child: Divider(color: AppColors.border)),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          GoogleSignInButton(
            onTap: _submitting ? null : _submitGoogle,
            loading: _googleSubmitting,
          ),
          const SizedBox(height: AppSpacing.xl),
          Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                "Don't have an account? ",
                style: GoogleFonts.inter(
                  fontSize: 13,
                  color: AppColors.textMuted,
                ),
              ),
              TextButton(
                onPressed: _submitting
                    ? null
                    : widget.onCreateAccount ??
                          () =>
                              Navigator.of(context)
                                  .push(authRoute(const AccountTypeScreen())),
                child: const Text('Create one'),
              ),
            ],
          ),
        ],
      ),
    );
    if (widget.embedded) return form;
    return HeroAuthScaffold(
      registration: true,
      footer: Text(
        'Your data is safe and secure with us.',
        textAlign: TextAlign.center,
        style: GoogleFonts.inter(fontSize: 12, color: AppColors.textMuted),
      ),
      child: form,
    );
  }
}
