import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// The website's original logo and two-tone wordmark.
class BrandWordmark extends StatelessWidget {
  const BrandWordmark({super.key});

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Image.asset(
        'assets/brand/logo.png',
        width: 32,
        height: 32,
        excludeFromSemantics: true,
      ),
      const SizedBox(width: 7),
      Text.rich(
        TextSpan(
          text: 'Pocket',
          children: [
            TextSpan(
              text: 'Finds',
              style: TextStyle(color: AppColors.pink),
            ),
          ],
        ),
        semanticsLabel: 'PocketFinds',
        style: TextStyle(
          fontSize: 19,
          fontWeight: FontWeight.w900,
          letterSpacing: -0.7,
          color: AppColors.textPrimary,
        ),
      ),
    ],
  );
}
