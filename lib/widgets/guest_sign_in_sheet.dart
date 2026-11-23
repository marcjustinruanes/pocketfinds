import 'package:flutter/material.dart';

import '../screens/auth/login_screen.dart';
import '../screens/auth/account_type_screen.dart';
import '../utils/auth_page_route.dart';
import '../theme/app_colors.dart';
import 'brand_wordmark.dart';

/// Website-style login modal; only registration leaves the guest page.
Future<void> showGuestSignInSheet(BuildContext context) async {
  final register = await showDialog<bool>(
    context: context,
    barrierColor: Colors.black54,
    builder: (dialogContext) => Dialog(
      backgroundColor: AppColors.white,
      surfaceTintColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const FittedBox(
                          fit: BoxFit.scaleDown,
                          child: BrandWordmark(),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Find It. Love It. Pocket It.',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close login',
                    onPressed: () => Navigator.pop(dialogContext, false),
                    icon: const Icon(Icons.close),
                    visualDensity: VisualDensity.compact,
                  ),
                ],
              ),
              const SizedBox(height: 28),
              LoginScreen(
                embedded: true,
                onCreateAccount: () => Navigator.pop(dialogContext, true),
              ),
            ],
          ),
        ),
      ),
    ),
  );
  if (register == true && context.mounted) {
    await Navigator.of(context).push(authRoute(const AccountTypeScreen()));
  }
}
