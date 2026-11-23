import 'package:flutter/material.dart';

import '../../../../theme/app_colors.dart';
import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';

/// Final step: username + password, then the wizard's submit button
/// finishes registration (upload documents, set the password, run
/// `complete_registration()`).
class AccountStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController usernameController;
  final TextEditingController passwordController;
  final TextEditingController confirmPasswordController;

  const AccountStep({
    super.key,
    required this.formKey,
    required this.usernameController,
    required this.passwordController,
    required this.confirmPasswordController,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AuthTextField(
            label: 'Username *',
            controller: usernameController,
            hint: 'At least 8 characters',
            validator: Validators.username,
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Password *',
            controller: passwordController,
            obscureText: true,
            validator: Validators.password,
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Confirm password *',
            controller: confirmPasswordController,
            obscureText: true,
            validator: (v) =>
                Validators.confirmPassword(v, passwordController.text),
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline,
                size: 14,
                color: AppColors.textMuted.withValues(alpha: 0.8),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Your ID and selfie will be reviewed by an admin before your '
                  "account is approved. You'll be able to sign in once it's approved.",
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textMuted.withValues(alpha: 0.9),
                    height: 1.3,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
