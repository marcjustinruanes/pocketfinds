import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

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
        Text(
          title,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
        if (onSeeAll != null)
          TextButton(
            onPressed: onSeeAll,
            child: const Text(
              'See All →',
              style: TextStyle(color: AppColors.pink),
            ),
          ),
      ],
    );
  }
}
