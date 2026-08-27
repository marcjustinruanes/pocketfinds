import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Maps an order/delivery status string to a semantic color so a glance at
/// the Orders list or a Delivery card tells you where things stand, instead
/// of every status rendering in the same neutral pink chip.
class StatusStyle {
  final Color color;
  final Color background;

  const StatusStyle(this.color, this.background);

  static StatusStyle forOrderStatus(String status) {
    switch (status) {
      case 'to_ship':
        return const StatusStyle(Color(0xFFB45309), Color(0xFFFEF3C7)); // amber
      case 'in_transit':
      case 'out_for_delivery':
        return const StatusStyle(Color(0xFF1D4ED8), Color(0xFFDBEAFE)); // blue
      case 'completed':
      case 'delivered':
        return StatusStyle(AppColors.success, const Color(0xFFDCFCE7)); // green
      case 'cancelled':
      case 'rejected':
        return StatusStyle(AppColors.danger, AppColors.dangerSoft); // red
      default:
        return const StatusStyle(AppColors.pinkDark, AppColors.pinkSoft);
    }
  }
}
