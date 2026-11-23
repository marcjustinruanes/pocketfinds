import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/staggered_fade_in.dart';

/// Payment Methods — a read-only view of the real, active payment methods
/// (`payment_methods`, same table/columns Checkout reads from), so a buyer
/// can see what's accepted without being mid-checkout. Adding/removing a
/// method is an admin/seller-side action, not something a buyer manages
/// here — this screen is informational.
class PaymentMethodsInfoScreen extends StatefulWidget {
  const PaymentMethodsInfoScreen({super.key});

  @override
  State<PaymentMethodsInfoScreen> createState() =>
      _PaymentMethodsInfoScreenState();
}

class _PaymentMethodsInfoScreenState extends State<PaymentMethodsInfoScreen> {
  late Future<List<Map<String, dynamic>>> _future = _load();

  Future<List<Map<String, dynamic>>> _load() {
    return BackendService.client
        .from('payment_methods')
        .select('id, name, description, requires_verification')
        .eq('is_active', true)
        .order('sort_order');
  }

  IconData _icon(String name) {
    final n = name.toLowerCase();
    if (n.contains('cod') || n.contains('cash')) return Icons.payments_outlined;
    if (n.contains('gcash') || n.contains('wallet')) {
      return Icons.account_balance_wallet_outlined;
    }
    if (n.contains('card') || n.contains('credit') || n.contains('debit')) {
      return Icons.credit_card_outlined;
    }
    if (n.contains('bank')) return Icons.account_balance_outlined;
    if (n.contains('paypal')) return Icons.paypal_outlined;
    return Icons.payment_outlined;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('Payment Methods', style: AppTypography.pageTitle),
      ),
      body: RefreshIndicator(
        color: AppColors.pink,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(
                    message: 'Could not load payment methods.',
                    error: friendlyError(snapshot.error!),
                  ),
                ],
              );
            }
            final methods = snapshot.data ?? [];
            if (methods.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(
                    icon: Icons.payment_outlined,
                    title: 'No payment methods available',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: methods.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final method = methods[index];
                final name = method['name'] as String;
                final description = method['description'] as String?;
                final needsVerification =
                    method['requires_verification'] == true;
                return StaggeredFadeIn(
                  index: index,
                  child: Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.border),
                      boxShadow: AppShadows.sm,
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: AppColors.pinkSoft,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            _icon(name),
                            color: AppColors.pink,
                            size: 20,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                name,
                                style: AppTypography.body.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if (description != null)
                                Text(description, style: AppTypography.caption),
                            ],
                          ),
                        ),
                        if (needsVerification)
                          Icon(
                            Icons.verified_user_outlined,
                            size: 16,
                            color: AppColors.textMuted,
                          ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
