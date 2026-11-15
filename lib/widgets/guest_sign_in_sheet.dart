import 'package:flutter/material.dart';
import '../screens/auth/account_type_screen.dart';
import '../screens/auth/login_screen.dart';
import '../theme/app_colors.dart';

/// Mobile equivalent of the website's `guest/auth-modal.blade.php`: guests
/// get nudged to sign in the moment they tap a "protected" action like Cart,
/// Wishlist, or Buy Now (the site does this via `data-protected` buttons).
Future<void> showGuestSignInSheet(BuildContext context) {
  return showModalBottomSheet(
    context: context,
    backgroundColor: AppColors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (sheetContext) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: const [
                Icon(Icons.storefront, color: AppColors.pink),
                SizedBox(width: 8),
                Text(
                  'PocketFinds',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text(
              'Everything you need, in one place.',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text(
              'Sign in to save items, checkout, and track your orders.',
              style: TextStyle(color: AppColors.textMuted),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  );
                },
                child: const Text('Sign in'),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const AccountTypeScreen()),
                  );
                },
                child: const Text('Create an account'),
              ),
            ),
          ],
        ),
      );
    },
  );
}
