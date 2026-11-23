import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';
import '../utils/formatters.dart';

/// A card with a bold title and content below — used by Checkout, Order
/// Details, and Delivery Details (was three separate copies of the same
/// `_card()` private helper before being pulled out here).
class TitledCard extends StatelessWidget {
  final String title;
  final Widget child;

  const TitledCard({super.key, required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: AppTypography.sectionTitle.copyWith(fontSize: 15),
            ),
            const SizedBox(height: 12),
            Divider(height: 1, color: AppColors.border),
            const SizedBox(height: 16),
            child,
          ],
        ),
      ),
    );
  }
}

/// A "Label ... ₱amount" row, optionally bold for totals — used inside
/// order-summary cards on Checkout and Order Details.
class SummaryRow extends StatelessWidget {
  final String label;
  final double amount;
  final bool bold;

  const SummaryRow(this.label, this.amount, {super.key, this.bold = false});

  @override
  Widget build(BuildContext context) {
    final labelStyle = bold
        ? AppTypography.body.copyWith(fontWeight: FontWeight.w800, fontSize: 15)
        : AppTypography.body;
    final amountStyle = bold
        ? AppTypography.priceLarge.copyWith(fontSize: 16)
        : AppTypography.price.copyWith(fontSize: 13);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: labelStyle),
          Text(formatPeso(amount), style: amountStyle),
        ],
      ),
    );
  }
}
