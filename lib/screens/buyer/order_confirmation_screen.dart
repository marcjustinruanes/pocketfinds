import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/success_pop.dart';
import '../../utils/formatters.dart';

/// Shown right after a successful checkout. The delivery estimate is the
/// same shipping-option text already shown at the Shipping Method step
/// (not a separately fabricated date range) — kept as free text since the
/// two flat options ("usually arrives within a week" / "faster delivery
/// for a higher fee") aren't precise calendar dates.
class OrderConfirmationScreen extends StatelessWidget {
  final String orderNumber;
  final String estimatedDelivery;
  final VoidCallback onTrackOrder;
  final double? orderTotal;
  final String? paymentMethod;

  const OrderConfirmationScreen({
    super.key,
    required this.orderNumber,
    required this.estimatedDelivery,
    required this.onTrackOrder,
    this.orderTotal,
    this.paymentMethod,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: SingleChildScrollView(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SuccessPop(
                    child: Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        color: AppColors.successSoft,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.celebration_outlined,
                        color: AppColors.success,
                        size: 48,
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  Text(
                    'Order Placed Successfully!',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    'Order #$orderNumber',
                    style: TextStyle(
                      fontSize: 14,
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  if (orderTotal != null)
                    Text(
                      formatPeso(orderTotal!),
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        color: AppColors.pinkDark,
                      ),
                    ),
                  if (paymentMethod != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8, bottom: 16),
                      child: Text(
                        'Payment: $paymentMethod',
                        textAlign: TextAlign.center,
                      ),
                    ),
                  if (estimatedDelivery.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(AppRadius.card),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.schedule, size: 16, color: AppColors.pink),
                          const SizedBox(width: 8),
                          Flexible(
                            child: Text(
                              'Estimated delivery: $estimatedDelivery',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: AppSpacing.xxl),
                  PrimaryButton(label: 'Track Order', onPressed: onTrackOrder),
                  const SizedBox(height: AppSpacing.md),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: () =>
                          Navigator.of(context)
                              .popUntil((route) => route.isFirst),
                      child: const Text('Continue Shopping'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
