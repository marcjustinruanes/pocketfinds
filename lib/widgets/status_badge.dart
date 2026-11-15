import 'package:flutter/material.dart';

import '../utils/status_style.dart';

/// A pill-shaped status label, colored via [StatusStyle.forOrderStatus] —
/// extracted from the near-identical inline `Container` + pill decoration
/// that used to be hand-rolled separately in Orders, Order Detail,
/// Deliveries, Dashboard, History, and Delivery Detail.
class StatusBadge extends StatelessWidget {
  final String status;
  final String label;

  const StatusBadge({super.key, required this.status, required this.label});

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.forOrderStatus(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: style.background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: style.color,
        ),
      ),
    );
  }
}
