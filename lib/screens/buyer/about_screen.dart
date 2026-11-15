import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../theme/app_colors.dart';
import '../../theme/app_typography.dart';
import '../../widgets/brand_wordmark.dart';

/// The website's brand and footer story, arranged for a mobile screen.
class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(title: Text('About', style: AppTypography.pageTitle)),
    body: ListView(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 640),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: AspectRatio(
                    aspectRatio: 1.9,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.asset(
                          'assets/brand/storefront.jpg',
                          fit: BoxFit.cover,
                          excludeFromSemantics: true,
                        ),
                        const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Color(0x154A0F2F), Color(0xE64A0F2F)],
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(22),
                          child: Align(
                            alignment: Alignment.bottomLeft,
                            child: Text(
                              'Little finds.\nEveryday favourites.',
                              style: GoogleFonts.dmSerifDisplay(
                                fontSize: 28,
                                color: const Color(0xFFFFE3ED),
                                height: 1.12,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 28),
                Text(
                  'OUR STORY',
                  style: AppTypography.statLabel.copyWith(
                    color: AppColors.pinkDark,
                    letterSpacing: 2,
                  ),
                ),
                const SizedBox(height: 10),
                const BrandWordmark(),
                const SizedBox(height: 14),
                Text(
                  'Discover everyday essentials and new favourites from local shops.',
                  style: AppTypography.pageTitle.copyWith(
                    fontSize: 23,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  'PocketFinds connects buyers with independent sellers and a network of delivery riders. Browse products, message a shop, and follow your order from checkout to your doorstep.',
                  style: AppTypography.body.copyWith(
                    color: AppColors.textMuted,
                    height: 1.8,
                  ),
                ),
                const SizedBox(height: 28),
                const _AboutStep(
                  number: '01',
                  icon: Icons.search_rounded,
                  title: 'Find something you love',
                  description: 'Explore categories, everyday essentials, and new arrivals.',
                ),
                const _AboutStep(
                  number: '02',
                  icon: Icons.storefront_outlined,
                  title: 'Get to know the shop',
                  description:
                      'Discover independent sellers and message them directly.',
                ),
                const _AboutStep(
                  number: '03',
                  icon: Icons.local_shipping_outlined,
                  title: 'Follow your delivery',
                  description: 'Keep your orders and delivery updates together in one place.',
                ),
                const SizedBox(height: 28),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 24,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.pinkSoft,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    children: [
                      Text(
                        'Find It. Love It. Pocket It.',
                        textAlign: TextAlign.center,
                        style: GoogleFonts.allura(
                          fontSize: 34,
                          color: AppColors.pinkDark,
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text('Version 1.0.0', style: AppTypography.caption),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  '© 2026 PocketFinds. All rights reserved.',
                  textAlign: TextAlign.center,
                  style: AppTypography.caption.copyWith(fontSize: 10.5),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _AboutStep extends StatelessWidget {
  final String number;
  final IconData icon;
  final String title;
  final String description;
  const _AboutStep({
    required this.number,
    required this.icon,
    required this.title,
    required this.description,
  });
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 18),
    decoration: BoxDecoration(
      border: Border(top: BorderSide(color: AppColors.border)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: AppColors.pinkSoft,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, size: 21, color: AppColors.pinkDark),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: AppTypography.productTitle),
              const SizedBox(height: 5),
              Text(
                description,
                style: AppTypography.caption.copyWith(height: 1.6),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text(
          number,
          style: AppTypography.statLabel.copyWith(color: AppColors.pinkDark),
        ),
      ],
    ),
  );
}
