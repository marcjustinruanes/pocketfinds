import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';

/// The app's actual Terms of Service + Privacy Policy — static, authored
/// copy (not a fabricated "reviewed by legal" document; a real starting
/// policy a small marketplace app ships with).
class PoliciesScreen extends StatelessWidget {
  const PoliciesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: Text('App Policies & Rules', style: AppTypography.pageTitle),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Terms of Service'),
              Tab(text: 'Privacy Policy'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _PolicyBody(sections: _termsSections),
            _PolicyBody(sections: _privacySections),
          ],
        ),
      ),
    );
  }
}

class _PolicyBody extends StatelessWidget {
  final List<(String, String)> sections;
  const _PolicyBody({required this.sections});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: sections.length,
      separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.lg),
      itemBuilder: (context, index) {
        final (title, body) = sections[index];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: AppTypography.sectionTitle.copyWith(fontSize: 14),
            ),
            const SizedBox(height: 6),
            Text(body, style: AppTypography.body.copyWith(height: 1.5)),
          ],
        );
      },
    );
  }
}

const _termsSections = <(String, String)>[
  (
    '1. Acceptance of Terms',
    'By creating a PocketFinds account or using the app, you agree to these Terms. If you do not agree, please do not use PocketFinds.',
  ),
  (
    '2. Accounts',
    "You're responsible for keeping your login credentials secure and for all activity under your account. Buyer and Rider accounts are reviewed and approved before use; PocketFinds may suspend an account that violates these Terms.",
  ),
  (
    '3. Orders & Payment',
    'Placing an order is an offer to purchase at the listed price plus any applicable shipping fee and voucher discount. Sellers are responsible for the accuracy of their own product listings.',
  ),
  (
    '4. Delivery',
    "Orders are fulfilled by independent riders through PocketFinds' logistics network. Estimated delivery times are not guaranteed. If a delivery fails, the rider will record a reason and the order may be rescheduled or returned to the seller.",
  ),
  (
    '5. Cancellations & Returns',
    'Contact the seller or PocketFinds support as soon as possible about an order issue. Refunds and returns are handled case by case through Help Center.',
  ),
  (
    '6. Prohibited Conduct',
    'You may not use PocketFinds to list or request prohibited goods, harass other users, or attempt to circumvent account or payment safeguards.',
  ),
  (
    '7. Limitation of Liability',
    'PocketFinds facilitates transactions between independent buyers, sellers, and riders and is not itself the seller of marketplace goods. To the extent permitted by law, PocketFinds is not liable for indirect or consequential damages arising from marketplace transactions.',
  ),
  (
    '8. Changes to These Terms',
    'PocketFinds may update these Terms from time to time. Continued use of the app after a change means you accept the revised Terms.',
  ),
];

const _privacySections = <(String, String)>[
  (
    '1. Information We Collect',
    'Account details you provide at registration (name, email, contact number, address, a government ID for verification), order and delivery history, messages you send in-app, and content you choose to add to your profile (photo, social links).',
  ),
  (
    '2. How We Use It',
    'To operate your account, process orders and deliveries, show you order status and messages, verify Buyer/Rider applications, and provide support when you contact Help Center.',
  ),
  (
    '3. Third-Party Services',
    'PocketFinds stores account and marketplace data in its MySQL database. Google Sign-In is an optional login method, subject to Google’s privacy practices.',
  ),
  (
    '4. Data Retention',
    "Your data is kept while your account is active. Deactivating your account (Settings > Deactivate Account) signs you out and blocks login; contact support if you'd like your data permanently deleted.",
  ),
  (
    '5. Your Choices',
    'You can update your personal info, socials, username, email, and password, and turn push notifications on or off, at any time from Settings.',
  ),
  (
    '6. Contact',
    'Questions about this policy or your data can be sent through Help Center > Message Support.',
  ),
];
