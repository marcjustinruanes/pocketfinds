import 'package:flutter/material.dart';

import '../models/category.dart';
import '../theme/app_colors.dart';
import '../utils/category_icons.dart';
import '../utils/backend_config.dart';

/// The site's circular category carousel, using icons where the app's
/// category API has no image field.
class StorefrontCategories extends StatelessWidget {
  final List<Category> categories;
  final int? selectedId;
  final ValueChanged<Category> onSelected;
  const StorefrontCategories({
    super.key,
    required this.categories,
    required this.onSelected,
    this.selectedId,
  });

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 146 * MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 1.6),
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: categories.length,
      separatorBuilder: (_, _) => const SizedBox(width: 12),
      itemBuilder: (context, index) {
        final category = categories[index];
        final selected = category.id == selectedId;
        return SizedBox(
          width: 104,
          child: Semantics(
            button: true,
            selected: selected,
            child: InkWell(
              onTap: () => onSelected(category),
              borderRadius: BorderRadius.circular(12),
              child: Column(
                children: [
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      color: AppColors.pinkSoft,
                      border: Border.all(
                        color: selected ? AppColors.pink : AppColors.pinkBorder,
                        width: selected ? 2 : 1,
                      ),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(13),
                      child: Image.network(
                        '${BackendConfig.url}/categories/${category.id}/image',
                        fit: BoxFit.cover,
                        excludeFromSemantics: true,
                        errorBuilder: (_, _, _) => Icon(
                          iconForCategory(category.name),
                          size: 32,
                          color: AppColors.pinkDark,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    category.name.toUpperCase(),
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: selected
                          ? AppColors.pinkDark
                          : AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Explore collection ↗',
                    style: TextStyle(fontSize: 8, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    ),
  );
}
