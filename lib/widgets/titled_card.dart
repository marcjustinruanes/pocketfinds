import 'package:flutter/material.dart';
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
            Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
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
    final style = TextStyle(
      fontWeight: bold ? FontWeight.w800 : FontWeight.w400,
      fontSize: bold ? 16 : 13,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text(formatPeso(amount), style: style),
        ],
      ),
    );
  }
}
