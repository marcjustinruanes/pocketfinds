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
      case 'placed':
      case 'preparing':
      case 'ready_for_pickup':
      case 'processing':
      case 'pending_payment':
      case 'pending_payment_verification':
      case 'pending_verification':
        return StatusStyle(AppColors.warning, AppColors.warningSoft);
      case 'in_transit':
      case 'picked_up':
      case 'at_sorting_center':
      case 'hub_transfer':
      case 'sorted':
      case 'assigned_to_rider':
      case 'out_for_delivery':
      case 'rescheduled':
      case 'return_in_transit':
        return StatusStyle(AppColors.info, AppColors.infoSoft);
      case 'completed':
      case 'delivered':
      case 'approved': // also a rider/account verification status
      case 'confirmed': // also a payment-verification status
      case 'paid':
      case 'payment_confirmed':
      case 'refunded':
        return StatusStyle(AppColors.success, AppColors.successSoft);
      case 'cancelled':
      case 'delivery_failed':
      case 'returned':
      case 'rejected':
      case 'expired':
      case 'suspended': // rider/account verification status
        return StatusStyle(AppColors.danger, AppColors.dangerSoft);
      default:
        return StatusStyle(AppColors.pinkDark, AppColors.pinkSoft);
    }
  }
}
