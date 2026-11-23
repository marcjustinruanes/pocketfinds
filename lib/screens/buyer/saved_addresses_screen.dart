import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../services/backend_service.dart';
import '../../services/buyer_workflow_service.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../shared/address_form_screen.dart';

class SavedAddressesScreen extends StatefulWidget {
  final AppUser user;
  final bool select;
  final BackendClient? client;
  const SavedAddressesScreen({
    super.key,
    required this.user,
    this.select = false,
    this.client,
  });
  @override
  State<SavedAddressesScreen> createState() => _SavedAddressesScreenState();
}

class _SavedAddressesScreenState extends State<SavedAddressesScreen> {
  late final _service = BuyerWorkflowService(client: widget.client);
  late Future<List<Map<String, dynamic>>> _future = _service.addresses();
  bool _busy = false;
  void _reload() => setState(() {
    _future = _service.addresses();
  });
  Future<void> _edit([Map<String, dynamic>? address]) async {
    final result = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => AddressFormScreen(
          user: widget.user,
          savedAddress: address,
          onSaveAddress: (data) =>
              _service.saveAddress(data, id: address?['id'] as int?),
        ),
      ),
    );
    if (!mounted || result == null) return;
    if (widget.select) {
      Navigator.pop(context, result);
    } else {
      _reload();
    }
  }

  Future<void> _change(String action, Map<String, dynamic> address) async {
    if (action == 'edit') {
      await _edit(address);
      return;
    }
    if (action == 'delete') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Remove saved address?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Keep'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Remove'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() => _busy = true);
    try {
      await _service.act('address-$action', {'id': address['id']});
      if (mounted) _reload();
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.select ? 'Choose delivery address' : 'My addresses'),
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _busy ? null : () => _edit(),
      icon: const Icon(Icons.add),
      label: const Text('Add address'),
    ),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(friendlyError(snapshot.error!)),
                TextButton(onPressed: _reload, child: const Text('Retry')),
              ],
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.data!.isEmpty) {
          return const Center(
            child: Text('Add a delivery address to get started.'),
          );
        }
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          children: snapshot.data!
              .map(
                (a) => Card(
                  child: ListTile(
                    onTap: widget.select && !_busy
                        ? () => Navigator.pop(context, a)
                        : null,
                    title: Text(
                      '${a['label'] ?? 'Address'}${a['is_default'] == true ? ' · Default' : ''}',
                    ),
                    subtitle: Text(
                      '${a['recipient_name']} · ${a['contact_no']}\n${a['full_address']}',
                    ),
                    isThreeLine: true,
                    trailing: PopupMenuButton<String>(
                      enabled: !_busy,
                      onSelected: (value) => _change(value, a),
                      itemBuilder: (_) => [
                        const PopupMenuItem(value: 'edit', child: Text('Edit')),
                        if (a['is_default'] != true)
                          const PopupMenuItem(
                            value: 'default',
                            child: Text('Set as default'),
                          ),
                        if (a['is_default'] != true)
                          const PopupMenuItem(
                            value: 'delete',
                            child: Text('Remove'),
                          ),
                      ],
                    ),
                  ),
                ),
              )
              .toList(),
        );
      },
    ),
  );
}
