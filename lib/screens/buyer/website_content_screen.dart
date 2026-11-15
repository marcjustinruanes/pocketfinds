import 'package:flutter/material.dart';

import '../../services/backend_service.dart';
import '../../utils/friendly_error.dart';

import 'package:intl/intl.dart';

class WebsiteContentScreen extends StatefulWidget {
  final String kind;
  final String? itemId;
  final BackendClient? client;
  const WebsiteContentScreen({
    super.key,
    required this.kind,
    this.itemId,
    this.client,
  });
  @override
  State<WebsiteContentScreen> createState() => _WebsiteContentScreenState();
}

class _WebsiteContentScreenState extends State<WebsiteContentScreen> {
  String _query = '';
  late final _client = widget.client ?? BackendService.client;
  late Future<dynamic> _future = _client.request(
    'website-buyer/${widget.kind}',
    {},
  );
  Future<void> _refresh() async {
    setState(
      () => _future = _client.request('website-buyer/${widget.kind}', {}),
    );
    try {
      await _future;
    } catch (_) {
      /* FutureBuilder displays the error. */
    }
  }

  String _plain(dynamic value) => '${value ?? ''}';
  String _date(dynamic value) {
    final date = DateTime.tryParse('$value');
    return date == null
        ? ''
        : DateFormat('MMM d, y ? h:mm a').format(date.toLocal());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.kind == 'faqs' ? 'Frequently Asked Questions' : 'Announcements',
      ),
    ),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<dynamic>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Text(friendlyError(snapshot.error!)),
                      TextButton(
                        onPressed: _refresh,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }
          final all = snapshot.data['items'] as List;
          final items = all
              .where(
                (row) =>
                    (widget.itemId == null ||
                        '${row['id']}' == widget.itemId) &&
                    '${row['title'] ?? row['question']} ${row['body'] ?? row['answer']}'
                        .toLowerCase()
                        .contains(_query),
              )
              .toList();
          return ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              if (widget.itemId == null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: TextField(
                    decoration: const InputDecoration(
                      hintText: 'Search',
                      prefixIcon: Icon(Icons.search),
                    ),
                    onChanged: (v) =>
                        setState(() => _query = v.trim().toLowerCase()),
                  ),
                ),
              if (items.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('No matching published items.'),
                ),
              for (final row in items)
                Card(
                  child: ExpansionTile(
                    key: ValueKey('${row['id']}-${widget.itemId}'),
                    initiallyExpanded: widget.itemId != null,
                    subtitle: widget.kind == 'announcements'
                        ? Text(_date(row['created_at']))
                        : null,
                    title: Text(_plain(row['title'] ?? row['question'] ?? '')),
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: SelectableText(
                          _plain(
                            row['content'] ??
                                row['body'] ??
                                row['answer'] ??
                                '',
                          ),
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
