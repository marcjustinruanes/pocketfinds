import 'package:flutter/material.dart';

import '../../services/delivery_service.dart';
import '../../services/backend_service.dart';
import '../../services/rider_web_module_service.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/titled_card.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_typography.dart';

/// Shared website balances; submitted cash is never treated as confirmed cash.
class RiderFinanceSummary extends StatefulWidget {
  final Map<String, dynamic> data;
  final Future<void> Function() onRefresh;
  final VoidCallback? onOpenPayouts;
  const RiderFinanceSummary({
    super.key,
    required this.data,
    required this.onRefresh,
    this.onOpenPayouts,
  });
  @override
  State<RiderFinanceSummary> createState() => _RiderFinanceSummaryState();
}

class _RiderFinanceSummaryState extends State<RiderFinanceSummary> {
  bool _busy = false;
  String? _error;
  Map<String, dynamic>? _pending;

  Future<void> _submit([Map<String, dynamic>? collection]) async {
    if (_busy) return;
    if (_pending == null && collection != null) {
      final controller = TextEditingController(
        text: '${collection['available']}',
      );
      final value = await showDialog<String>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Record COD handover'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Submit only cash physically handed to your assigned hub. Logistics will count and confirm it.',
              ),
              const SizedBox(height: 16),
              TextField(
                controller: controller,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Amount handed over',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, controller.text.trim()),
              child: const Text('Submit'),
            ),
          ],
        ),
      );
      if (!mounted || value == null) return;
      if (!RegExp(r'^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$').hasMatch(value) ||
          (double.tryParse(value) ?? 0) <= 0 ||
          double.parse(value) >
              (double.tryParse('${collection['available']}') ?? 0)) {
        setState(
          () => _error = 'Enter an amount within the available balance.',
        );
        return;
      }
      _pending = {
        'key': DeliveryService.requestId(),
        'allocations': {collection['id']: value},
      };
    }
    if (_pending == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await RiderWebModuleService().call('cash-submit', _pending!);
      _pending = null;
      DeliveryService.updates.add(null);
      await widget.onRefresh();
    } catch (e) {
      if (e is DatabaseException &&
          e.statusCode != null &&
          e.statusCode! >= 400 &&
          e.statusCode! < 500) {
        _pending = null;
      }
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _amount(
    String label,
    dynamic amount, {
    Color? color,
    bool prominent = false,
  }) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 3,
          child: Text(
            label,
            style: AppTypography.body.copyWith(
              fontSize: 13,
              fontWeight: prominent ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
        ),
        const SizedBox(width: 16),
        Flexible(
          flex: 2,
          child: Text(
            formatPeso(double.tryParse('$amount') ?? 0),
            textAlign: TextAlign.end,
            style: AppTypography.price.copyWith(
              fontWeight: FontWeight.w700,
              fontSize: prominent ? 20 : 14,
              color: color ?? AppColors.textPrimary,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _dailyPay(Map<String, dynamic> day) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text('${day['date']}', style: AppTypography.label),
      const SizedBox(height: 4),
      Text('${day['successful_deliveries']} successful deliveries'),
      _amount('Delivery earnings', day['earned'], color: AppColors.pinkDark),
      _amount('Paid by logistics', day['paid'], color: AppColors.success),
      _amount('Awaiting daily payout', day['unpaid'], color: AppColors.warning),
      Divider(height: 24, color: AppColors.border),
    ],
  );

  @override
  Widget build(BuildContext context) {
    final earnings = widget.data['earnings'] as Map? ?? {};
    final cash = widget.data['cash'] as Map? ?? {};
    final collections = widget.data['collections'] as List? ?? [];
    final daily = widget.data['daily'] as List? ?? [];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TitledCard(
          title: 'Daily delivery pay',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'PHP 10 per successful delivery',
                style: AppTypography.productTitle.copyWith(
                  color: AppColors.pinkDark,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Earned when delivery is successfully completed. Logistics pays daily. Buyer Order Received confirmation does not delay this earning. Failed attempts earn no delivery pay.',
              ),
              Divider(height: 24, color: AppColors.border),
              if (daily.isEmpty)
                const Text('No successful deliveries recorded yet.'),
              if (daily.isNotEmpty)
                _dailyPay(Map<String, dynamic>.from(daily.first as Map)),
              if (daily.length > 1)
                ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  title: const Text('Earlier delivery days'),
                  children: [
                    for (final day in daily.skip(1))
                      _dailyPay(Map<String, dynamic>.from(day as Map)),
                  ],
                ),
              Text(
                'Paid amounts appear only after logistics records the actual payout. Older completed records retain their recorded amounts.',
                style: AppTypography.caption,
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        TitledCard(
          title: 'Earnings & payout stages',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Recorded by logistics. Earned compensation and money received are shown separately.',
                style: AppTypography.caption.copyWith(height: 1.5),
              ),
              const SizedBox(height: 8),
              _amount(
                'Total earned',
                earnings['earned'],
                prominent: true,
                color: AppColors.pinkDark,
              ),
              Divider(height: 24, color: AppColors.border),
              _amount('Pending work confirmation', earnings['pending']),
              Divider(height: 1, color: AppColors.border),
              _amount(
                'Earned - awaiting payout preparation',
                earnings['awaiting_payout'],
              ),
              Divider(height: 1, color: AppColors.border),
              _amount('Payout prepared', earnings['prepared']),
              Divider(height: 1, color: AppColors.border),
              _amount('Verified - awaiting payment', earnings['verified']),
              Divider(height: 1, color: AppColors.border),
              _amount(
                'On hold - contact logistics',
                earnings['on_hold'],
                color: AppColors.warning,
              ),
              Divider(height: 24, color: AppColors.border),
              _amount(
                'Paid by logistics',
                earnings['paid'],
                color: AppColors.success,
              ),
              _amount(
                'Receipt acknowledged',
                earnings['acknowledged'],
                color: AppColors.success,
              ),
              const SizedBox(height: 8),
              Text(
                'Acknowledged receipts are included in paid amounts. Open payout receipts to confirm only money you actually received.',
                style: AppTypography.caption.copyWith(height: 1.5),
              ),
              if (widget.onOpenPayouts != null) ...[
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: widget.onOpenPayouts,
                  icon: const Icon(
                    Icons.account_balance_wallet_outlined,
                    size: 20,
                  ),
                  label: const Text('Payout account & receipts'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(48, 48),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        TitledCard(
          title: 'COD cash to hand over',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Collect the full COD total, including shipping, and remit it to your logistics hub. Do not deduct your delivery pay from buyer cash.',
                style: AppTypography.body,
              ),
              const SizedBox(height: 12),
              _amount('Collected from buyers', cash['collected']),
              Divider(height: 1, color: AppColors.border),
              _amount(
                'Confirmed by logistics',
                cash['confirmed'],
                color: AppColors.success,
              ),
              Divider(height: 1, color: AppColors.border),
              _amount(
                'Submitted - awaiting count',
                cash['submitted'],
                color: AppColors.warning,
              ),
              Divider(height: 1, color: AppColors.border),
              _amount(
                'Outstanding, including submitted',
                cash['outstanding'],
                prominent: true,
              ),
              Divider(height: 24, color: AppColors.border),
              _amount(
                'Available to submit',
                cash['available'],
                color: AppColors.pinkDark,
              ),
              Text(
                'Submitting a handover does not clear your balance. Only the confirmed hub count reduces outstanding COD.',
                style: AppTypography.caption.copyWith(height: 1.5),
              ),
              const SizedBox(height: 12),
              Text(
                'After hub cash confirmation and the buyer Order Received action, logistics distributes 10% of product proceeds to the website owner and 90% to the seller. Shipping belongs to logistics. Rider delivery pay is separate.',
                style: AppTypography.caption.copyWith(height: 1.5),
              ),
              if (collections.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 12),
                  child: Text('No recorded COD collections.'),
                ),
              for (final raw in collections) ...[
                const Divider(height: 24),
                Text(
                  '${raw['tracking_number'] ?? raw['id']}',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                _amount('Outstanding', raw['outstanding']),
                Text(
                  'Handover deadline: ${DateTime.tryParse('${raw['due_at']}')?.toLocal().toString().substring(0, 16) ?? raw['due_at']}',
                ),
                if ((double.tryParse('${raw['available']}') ?? 0) > 0) ...[
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _busy || _pending != null
                        ? null
                        : () => _submit(Map<String, dynamic>.from(raw as Map)),
                    icon: const Icon(Icons.payments_outlined),
                    label: const Text('Record cash handover'),
                    style: FilledButton.styleFrom(
                      minimumSize: const Size(48, 48),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 14,
                      ),
                    ),
                  ),
                ],
              ],
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              if (_pending != null)
                FilledButton(
                  onPressed: _busy ? null : () => _submit(),
                  child: Text(_busy ? 'Submitting...' : 'Retry saved handover'),
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }
}
