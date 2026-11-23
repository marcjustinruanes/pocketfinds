import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../theme/app_colors.dart';
import '../../../../theme/app_spacing.dart';
import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';

class ContactStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController emailController;
  final TextEditingController otpController;
  final TextEditingController contactNoController;
  final bool otpSent;
  final bool otpVerified;
  final bool sendingCode;
  final bool verifyingCode;
  final int resendSecondsLeft;
  final VoidCallback? onSendCode;
  final VoidCallback onVerifyCode;

  const ContactStep({
    super.key,
    required this.formKey,
    required this.emailController,
    required this.otpController,
    required this.contactNoController,
    required this.otpSent,
    required this.otpVerified,
    required this.sendingCode,
    required this.verifyingCode,
    required this.resendSecondsLeft,
    required this.onSendCode,
    required this.onVerifyCode,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Email address *',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextFormField(
                  controller: emailController,
                  enabled: !otpVerified,
                  keyboardType: TextInputType.emailAddress,
                  validator: Validators.email,
                  decoration: const InputDecoration(
                    hintText: 'you@example.com',
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              _PillButton(
                label: otpVerified
                    ? 'Sent ✓'
                    : (otpSent && resendSecondsLeft > 0)
                    ? 'Resend (${resendSecondsLeft}s)'
                    : otpSent
                    ? 'Resend'
                    : 'Send Code',
                loading: sendingCode,
                onTap: otpVerified ? null : onSendCode,
              ),
            ],
          ),
          if (otpSent && !otpVerified) ...[
            const SizedBox(height: 4),
            Text(
              'Code sent! Check your inbox (and spam folder).',
              style: TextStyle(fontSize: 11.5, color: AppColors.textMuted),
            ),
          ],
          if (otpSent) ...[
            const SizedBox(height: 12),
            const Text(
              'Verification code *',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextFormField(
                    controller: otpController,
                    enabled: !otpVerified,
                    // Deliberately TextInputType.text, not .number: on
                    // Flutter Web, .number renders as a real HTML
                    // <input type="number">, which shows the browser's
                    // native up/down spinner arrows. digitsOnly below
                    // still blocks letters on every platform.
                    keyboardType: TextInputType.text,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    maxLength: 6,
                    validator: otpVerified ? null : Validators.otpCode,
                    decoration: const InputDecoration(
                      hintText: 'Enter 6-digit code',
                      counterText: '',
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                _PillButton(
                  label: otpVerified ? 'Verified ✓' : 'Verify',
                  loading: verifyingCode,
                  onTap: otpVerified ? null : onVerifyCode,
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              otpVerified
                  ? 'Your email is verified.'
                  : 'Enter the code sent to your email.',
              style: TextStyle(
                fontSize: 11.5,
                color: otpVerified ? AppColors.success : AppColors.textMuted,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          AuthTextField(
            label: 'Contact number *',
            controller: contactNoController,
            hint: '09XXXXXXXXX',
            // .text + digitsOnly, not .phone: same web-spinner-arrow +
            // letters-allowed issue as the OTP field above.
            keyboardType: TextInputType.text,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(11),
            ],
            validator: Validators.contactNo,
          ),
        ],
      ),
    );
  }
}

class _PillButton extends StatelessWidget {
  final String label;
  final bool loading;
  final VoidCallback? onTap;

  const _PillButton({
    required this.label,
    required this.loading,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: OutlinedButton(
        onPressed: loading ? null : onTap,
        style: OutlinedButton.styleFrom(
          side: BorderSide(color: AppColors.pink),
          foregroundColor: AppColors.pink,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999),
          ),
        ),
        child: loading
            ? const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Text(
                label,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 12.5,
                ),
              ),
      ),
    );
  }
}
