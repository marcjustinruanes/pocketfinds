import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import 'pressable.dart';

/// Google sign-in button using the same multicolor mark as the website.
class GoogleSignInButton extends StatelessWidget {
  final VoidCallback? onTap;
  final bool loading;

  const GoogleSignInButton({
    super.key,
    required this.onTap,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: loading ? null : onTap,
      child: Container(
        constraints: const BoxConstraints(
          minHeight: AppDimensions.buttonHeight,
        ),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: 12,
        ),
        decoration: BoxDecoration(
          color: AppColors.white,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: loading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SvgPicture.asset(
                    'assets/icons/google_logo.svg',
                    width: 20,
                    height: 20,
                    excludeFromSemantics: true,
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      'Continue with Google',
                      style: AppTypography.label,
                      textAlign: TextAlign.center,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
