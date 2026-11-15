import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_motion.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';

/// One real platform voucher, as returned by
/// `fetch_available_platform_vouchers()`.
class PlatformVoucher {
  final String id;
  final String title;
  final String? description;
  final String type; // free_shipping | discount
  final double? discountAmount;
  final double? discountPercent;
  final double? minimumSpend;
  final DateTime? expiresAt;
  final bool eligible;

  const PlatformVoucher({
    required this.id,
    required this.title,
    this.description,
    required this.type,
    this.discountAmount,
    this.discountPercent,
    this.minimumSpend,
    this.expiresAt,
    required this.eligible,
  });

  factory PlatformVoucher.fromRow(Map<String, dynamic> row) {
    return PlatformVoucher(
      id: row['id'] as String,
      title: row['title'] as String,
      description: row['description'] as String?,
      type: row['type'] as String,
      discountAmount: row['discount_amount'] == null
          ? null
          : double.tryParse('${row['discount_amount']}'),
      discountPercent: row['discount_percent'] == null
          ? null
          : double.tryParse('${row['discount_percent']}'),
      minimumSpend: row['minimum_spend'] == null
          ? null
          : double.tryParse('${row['minimum_spend']}'),
      expiresAt: row['expires_at'] == null
          ? null
          : DateTime.tryParse(row['expires_at'].toString()),
      eligible: row['eligible'] == true,
    );
  }

  String get benefitLabel => switch (type) {
    'free_shipping' => 'Free Shipping',
    _ =>
      discountPercent != null
          ? '${discountPercent!.toStringAsFixed(0)}% off'
          : '${formatPeso(discountAmount ?? 0)} off',
  };

  IconData get icon => switch (type) {
    'free_shipping' => Icons.local_shipping_outlined,
    _ => Icons.sell_outlined,
  };
}

Future<List<PlatformVoucher>> fetchAvailablePlatformVouchers(
  double subtotal,
) async {
  final rows = await BackendService.client.rpc(
    'fetch_available_platform_vouchers',
    params: {'p_subtotal': subtotal},
  );
  return List<Map<String, dynamic>>.from(rows as List)
      .map(PlatformVoucher.fromRow)
      .toList();
}

/// A bottom sheet listing real platform-wide vouchers — Free Shipping,
/// % discounts — clearly marking ones that don't meet this
/// checkout's minimum spend as unavailable rather than hiding them.
Future<PlatformVoucher?> showPlatformVoucherSheet(
  BuildContext context,
  double subtotal,
) {
  return showModalBottomSheet<PlatformVoucher>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (_) => _PlatformVoucherSheet(subtotal: subtotal),
  );
}

class _PlatformVoucherSheet extends StatefulWidget {
  final double subtotal;
  const _PlatformVoucherSheet({required this.subtotal});

  @override
  State<_PlatformVoucherSheet> createState() => _PlatformVoucherSheetState();
}

class _PlatformVoucherSheetState extends State<_PlatformVoucherSheet> {
  late final Future<List<PlatformVoucher>> _future =
      fetchAvailablePlatformVouchers(widget.subtotal);

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Platform Vouchers',
                      style: AppTypography.sectionTitle,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.6,
              ),
              child: FutureBuilder<List<PlatformVoucher>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Padding(
                      padding: EdgeInsets.all(32),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  }
                  final vouchers = snapshot.data ?? [];
                  if (vouchers.isEmpty) {
                    return Padding(
                      padding: const EdgeInsets.all(32),
                      child: Center(
                        child: Text(
                          'No vouchers available right now.',
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ),
                    );
                  }
                  return ListView.separated(
                    shrinkWrap: true,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: vouchers.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => _VoucherTile(
                      voucher: vouchers[index],
                      onTap: vouchers[index].eligible
                          ? () => Navigator.of(context).pop(vouchers[index])
                          : null,
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}

class _VoucherTile extends StatelessWidget {
  final PlatformVoucher voucher;
  final VoidCallback? onTap;

  const _VoucherTile({required this.voucher, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final disabled = !voucher.eligible;
    return AnimatedOpacity(
      duration: AppMotion.fast,
      opacity: disabled ? 0.5 : 1,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: disabled ? AppColors.pageBackground : AppColors.pinkSoft,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: disabled ? AppColors.border : AppColors.pinkBorder,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.white,
                  shape: BoxShape.circle,
                ),
                child: Icon(voucher.icon, color: AppColors.pink, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      voucher.title,
                      style: AppTypography.productTitle.copyWith(fontSize: 14),
                    ),
                    Text(
                      voucher.benefitLabel,
                      style: TextStyle(
                        color: AppColors.pinkDark,
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                      ),
                    ),
                    if (voucher.minimumSpend != null)
                      Text(
                        disabled
                            ? 'Min. spend ${formatPeso(voucher.minimumSpend!)}'
                            : 'Min. spend ${formatPeso(voucher.minimumSpend!)} met',
                        style: AppTypography.caption,
                      ),
                  ],
                ),
              ),
              if (!disabled)
                Icon(Icons.chevron_right, color: AppColors.textMuted),
            ],
          ),
        ),
      ),
    );
  }
}
