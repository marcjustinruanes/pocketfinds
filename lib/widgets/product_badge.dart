import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

enum ProductBadgeType { sale, newArrival, lowStock, outOfStock, freeShipping }

/// Small pill badges for product cards/detail — all derived from real
/// columns (`Product.hasDiscount`/`isNew`/`isLowStock`), never fabricated.
/// "Best Seller" is deliberately not part of this set — no reliable real
/// per-product sales signal exists yet in this pass. "Free Shipping" is
/// always true here (PocketFinds doesn't charge per-item shipping — the
/// flat delivery fee is charged once per seller at checkout, not per
/// product), so it's shown as a simple factual label, not a promotion.
class ProductBadge extends StatelessWidget {
  final ProductBadgeType type;
  final int? discountPercent;

  const ProductBadge({super.key, required this.type, this.discountPercent});

  ({Color color, Color background, String label}) get _style => switch (type) {
    ProductBadgeType.sale => (
      color: AppColors.white,
      background: AppColors.danger,
      label: discountPercent != null ? '-$discountPercent%' : 'SALE',
    ),
    ProductBadgeType.newArrival => (
      color: AppColors.white,
      background: AppColors.info,
      label: 'NEW',
    ),
    ProductBadgeType.lowStock => (
      color: AppColors.white,
      background: AppColors.warning,
      label: 'LOW STOCK',
    ),
    ProductBadgeType.outOfStock => (
      color: AppColors.white,
      background: AppColors.danger,
      label: 'OUT OF STOCK',
    ),
    ProductBadgeType.freeShipping => (
      color: AppColors.pinkDark,
      background: AppColors.pinkSoft,
      label: 'FREE SHIPPING',
    ),
  };

  @override
  Widget build(BuildContext context) {
    final s = _style;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: s.background,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        s.label,
        style: TextStyle(
          color: s.color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.3,
        ),
      ),
    );
  }
}
