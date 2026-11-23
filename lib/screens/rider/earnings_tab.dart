import 'package:flutter/material.dart';

import '../../services/delivery_service.dart';
import '../../services/rider_earnings.dart';
import '../../services/rider_service.dart';
import 'rider_cod_card.dart';
import 'rider_finance_summary.dart';
import '../../services/rider_web_module_service.dart';
import '../../widgets/adaptive_card_grid.dart';
import 'rider_payouts_screen.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/mini_bar_chart.dart';
import '../../widgets/rider_stat_tile.dart';
import '../../widgets/skeleton.dart';
import '../../widgets/titled_card.dart';

/// Earnings totals and charts use recorded compensation from the shared ledger.
class EarningsTab extends StatefulWidget {
  const EarningsTab({super.key});

  @override
  State<EarningsTab> createState() => _EarningsTabState();
}

class _EarningsTabState extends State<EarningsTab>
    with WidgetsBindingObserver, DeliveryRefresh<EarningsTab> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  Map<String, dynamic> _ledger = {};
  Map<String, dynamic> _shared = {};
  late Future<RiderEarnings> _future;

  // The raw (date, fee) pairs behind the stat tiles — kept around so the
  // chart's 7/30-day filter can be recomputed instantly, client-side,
  // without a re-fetch every time the filter changes.
  List<({DateTime date, double fee})> _entries = const [];
  int _chartDays = 7;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<RiderEarnings> _load() async {
    final records = await Future.wait([
      RiderService.earnings(),
      RiderWebModuleService().call('overview'),
    ]);
    _ledger = records[0];
    _shared = records[1];
    _entries = (_shared['jobs'] as List)
        .map(
          (r) => (
            date: DateTime.parse('${r['created_at']}'),
            fee: double.parse('${r['amount']}'),
          ),
        )
        .toList();
    return RiderEarnings.fromEntries(_entries);
  }

  Future<void> _refresh() async {
    setState(() {
      _future = _load();
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('Earnings', style: AppTypography.pageTitle)),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<RiderEarnings>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Row(
                    children: List.generate(3, (i) {
                      return Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(
                            left: i == 0 ? 0 : 6,
                            right: i == 2 ? 0 : 6,
                          ),
                          child: const SizedBox(height: 88, child: Skeleton()),
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  const SizedBox(height: 200, child: Skeleton()),
                ],
              );
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(
                    message: 'Could not load earnings.',
                    error: friendlyError(snapshot.error!),
                    onRetry: _refresh,
                  ),
                ],
              );
            }
            final earnings = snapshot.data!;
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                Text(
                  'Your earnings',
                  style: AppTypography.sectionTitle.copyWith(fontSize: 16),
                ),
                const SizedBox(height: 12),
                AdaptiveCardGrid(
                  children: [
                    RiderStatTile(
                      label: 'Today',
                      value: formatPeso(earnings.today),
                      icon: Icons.today_outlined,
                      color: AppColors.pinkDark,
                      background: AppColors.pinkSoft,
                    ),
                    RiderStatTile(
                      label: 'This week',
                      value: formatPeso(earnings.thisWeek),
                      icon: Icons.calendar_view_week_outlined,
                      color: AppColors.info,
                      background: AppColors.infoSoft,
                    ),
                    RiderStatTile(
                      label: 'This month',
                      value: formatPeso(earnings.thisMonth),
                      icon: Icons.calendar_month_outlined,
                      color: AppColors.success,
                      background: AppColors.successSoft,
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.xl),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 12,
                        runSpacing: 8,
                        children: [
                          Text(
                            'Earnings trend',
                            style: AppTypography.sectionTitle.copyWith(
                              fontSize: 15,
                            ),
                          ),
                          _PeriodDropdown(
                            value: _chartDays,
                            onChanged: (value) =>
                                setState(() => _chartDays = value),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Divider(height: 1, color: AppColors.border),
                      const SizedBox(height: 16),
                      if (_entries.isEmpty ||
                          RiderEarnings.dailySeries(
                            _entries,
                            _chartDays,
                          ).every((day) => day.amount == 0))
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          child: Row(
                            children: [
                              Icon(
                                Icons.bar_chart_rounded,
                                color: AppColors.pinkDark,
                                size: 28,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  'No earnings in this period. Completed work will appear here.',
                                  style: AppTypography.body.copyWith(
                                    color: AppColors.textMuted,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        )
                      else
                        MiniBarChart(
                          days: RiderEarnings.dailySeries(_entries, _chartDays),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                TitledCard(
                  title: 'Lifetime',
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          'Completed tasks',
                          style: AppTypography.body,
                        ),
                      ),
                      Text(
                        '${earnings.completedCount}',
                        style: AppTypography.priceLarge.copyWith(fontSize: 18),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                RiderFinanceSummary(
                  data: _shared,
                  onRefresh: _refresh,
                  onOpenPayouts: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const RiderPayoutsScreen(),
                    ),
                  ),
                ),
                ExpansionTile(
                  tilePadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 4,
                  ),
                  childrenPadding: const EdgeInsets.all(16),
                  collapsedBackgroundColor: AppColors.white,
                  backgroundColor: AppColors.white,
                  collapsedShape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadius.card),
                    side: BorderSide(color: AppColors.border),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadius.card),
                    side: BorderSide(color: AppColors.border),
                  ),
                  title: Text(
                    'Earlier app records',
                    style: AppTypography.productTitle,
                  ),
                  subtitle: const Text(
                    'Separate from website logistics balances',
                  ),
                  children: [
                    RiderCodCard(data: _ledger, onRefresh: _refresh),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'Successful final deliveries earn ₱10 per parcel under the current policy. Failed attempts do not earn this compensation. Logistics funds, prepares, verifies and sends payouts, then records payment evidence. Check receipt before acknowledging your payout.',
                        ),
                        for (final entry in {
                          'total': 'Total credited earnings',
                          'pending': 'Pending completion / dispute policy',
                          'payable': 'Payable — awaiting logistics payout',
                          'approved': 'Approved',
                          'released': 'Released',
                          'failed': 'Failed payouts',
                          'reversed': 'Reversed payouts',
                        }.entries)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              '${entry.value}: ${formatPeso(double.tryParse('${(_ledger['payout_totals'] as Map?)?[entry.key]}') ?? 0)}',
                            ),
                          ),
                        const SizedBox(height: 12),
                        Text(
                          'Completed deliveries: ${(_ledger['payout_totals'] as Map?)?['completed_deliveries'] ?? 0}',
                        ),
                        const SizedBox(height: 12),
                        Text('Task credits', style: AppTypography.sectionTitle),
                        if ((_ledger['jobs'] as List? ?? []).isEmpty)
                          const Text('No task credits yet.'),
                        for (final job in _ledger['jobs'] as List? ?? [])
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              '${job['tracking_number'] ?? job['shipment_id']} • ${formatPeso(double.parse('${job['amount']}'))}',
                            ),
                            subtitle: Text(
                              '${job['leg']} • ${job['shipping_status']}\nCredited: ${job['created_at']}',
                            ),
                          ),
                        Text(
                          'Payout history',
                          style: AppTypography.sectionTitle,
                        ),
                        if ((_ledger['payouts'] as List? ?? []).isEmpty)
                          const Text('No payouts recorded yet.'),
                        for (final p in _ledger['payouts'] as List? ?? [])
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              '${formatPeso(double.parse('${p['amount']}'))} • ${p['status']}',
                            ),
                            subtitle: Text(
                              'Delivery: ${p['shipment_id']}\nReference: ${p['reference'] ?? 'Not released'}\nRelease date: ${p['released_at'] ?? 'Not released'}',
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

/// A compact "7 days / 30 days" period selector for the earnings chart.
class _PeriodDropdown extends StatelessWidget {
  final int value;
  final ValueChanged<int> onChanged;

  const _PeriodDropdown({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 48),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.pinkSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: value,
          isDense: true,
          icon: Icon(Icons.expand_more, size: 16, color: AppColors.pinkDark),
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AppColors.pinkDark,
          ),
          items: const [
            DropdownMenuItem(value: 7, child: Text('Last 7 days')),
            DropdownMenuItem(value: 30, child: Text('Last 30 days')),
          ],
          onChanged: (v) {
            if (v != null) onChanged(v);
          },
        ),
      ),
    );
  }
}
