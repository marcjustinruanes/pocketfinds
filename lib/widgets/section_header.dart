import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

import 'package:google_fonts/google_fonts.dart';

/// A "Section Title ... See All" row, matching `.section-head` on the site.
class SectionHeader extends StatelessWidget {
  final String title;
  final VoidCallback? onSeeAll;

  const SectionHeader({super.key, required this.title, this.onSeeAll});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            title,
            style: title == 'Shop by Category'
                ? GoogleFonts.allura(
                    fontSize: 36,
                    color: AppColors.pinkDark,
                    height: 1.2,
                  )
                : AppTypography.sectionTitle.copyWith(
                    fontSize: 21,
                    letterSpacing: -0.5,
                  ),
          ),
        ),
        if (onSeeAll != null)
          TextButton(
            onPressed: onSeeAll,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'See All',
                  style: TextStyle(color: AppColors.pinkDark, fontSize: 12),
                ),
                const SizedBox(width: 4),
                Icon(Icons.arrow_forward, color: AppColors.pinkDark, size: 14),
              ],
            ),
          ),
      ],
    );
  }
}
