import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../utils/friendly_error.dart';

class RiderJobHistoryScreen extends StatefulWidget {
  const RiderJobHistoryScreen({super.key});
  @override
  State<RiderJobHistoryScreen> createState() => _RiderJobHistoryScreenState();
}

class _RiderJobHistoryScreenState extends State<RiderJobHistoryScreen> {
  String _filter = 'All';
  late Future<dynamic> _future = _load();
  Future<dynamic> _load() => BackendService.client.request('rider/history', {});
  Future<void> _refresh() async {
    setState(() {
      _future = _load();
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Pickup and delivery history')),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<dynamic>(
        future: _future,
        builder: (context, s) {
          if (s.hasError) {
            return ListView(
              children: [
                Text(friendlyError(s.error!)),
                TextButton(onPressed: _refresh, child: const Text('Retry')),
              ],
            );
          }
          if (!s.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final rows = (s.data as List)
              .where(
                (r) => switch (_filter) {
                  'Pickups' => r['leg'] == 'pickup',
                  'Deliveries' => r['leg'] == 'delivery',
                  'Attempts' => (r['attempt_count'] as num? ?? 0) > 0,
                  'Returns' => [
                    'returned',
                    'return_in_transit',
                  ].contains(r['result']),
                  'Canceled' => r['result'] == 'cancelled',
                  _ => true,
                },
              )
              .toList();
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final label in [
                    'All',
                    'Pickups',
                    'Deliveries',
                    'Attempts',
                    'Returns',
                    'Canceled',
                  ])
                    ChoiceChip(
                      label: Text(label),
                      selected: _filter == label,
                      onSelected: (_) => setState(() => _filter = label),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              if (rows.isEmpty)
                const ListTile(
                  title: Text('No completed or attempted jobs yet.'),
                ),
              for (final r in rows)
                Card(
                  child: ExpansionTile(
                    tilePadding: const EdgeInsets.all(16),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    title: Text('${r['tracking_number'] ?? 'Parcel'}'),
                    subtitle: Text(
                      '${r['leg']} - ${r['result']}\n${r['created_at']}\n${r['attempt_count'] ?? 0} failed attempt(s)',
                    ),
                    children: [
                      if ((r['events'] as List? ?? []).isEmpty)
                        const Text('No app activity recorded for this job.'),
                      for (final event in r['events'] as List? ?? [])
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(
                            Icons.radio_button_checked,
                            size: 18,
                          ),
                          title: Text(
                            '${event['action']}'
                                .replaceAll('seller-pickup-', '')
                                .replaceAll('-', ' '),
                          ),
                          subtitle: Text(
                            '${event['status']} - ${event['created_at']}',
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    ),
  );
}
