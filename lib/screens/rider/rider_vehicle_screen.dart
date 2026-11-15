import 'dart:io';

import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/backend_service.dart';
import '../../services/buyer_photo_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';

class RiderVehicleScreen extends StatefulWidget {
  const RiderVehicleScreen({super.key});
  @override
  State<RiderVehicleScreen> createState() => _RiderVehicleScreenState();
}

class _RiderVehicleScreenState extends State<RiderVehicleScreen> {
  final _form = GlobalKey<FormState>();
  final _fields = {
    for (final f in [
      'vehicle_brand',
      'vehicle_model',
      'plate_number',
      'license_number',
      'license_expiry',
    ])
      f: TextEditingController(),
  };
  String _type = 'two_wheels', _request = DeliveryService.requestId();
  final _files = <String, String>{};
  Map<String, dynamic>? _user, _submitted;
  List<dynamic> _requests = [];
  bool _busy = false, _dirty = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data =
          await BackendService.client.request('rider/profile', {}) as Map;
      if (!mounted) return;
      setState(() {
        _user = Map<String, dynamic>.from(data['user'] as Map);
        _type = '${_user!['vehicle_type'] ?? 'two_wheels'}';
        for (final f in _fields.keys) {
          _fields[f]!.text = '${_user![f] ?? ''}'.split('T').first;
        }
        _requests = data['requests'] as List;
      });
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    }
  }

  Future<void> _pick(String kind) async {
    final source = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final s in ['Camera', 'Gallery', 'Files'])
              ListTile(title: Text(s), onTap: () => Navigator.pop(c, s)),
          ],
        ),
      ),
    );
    if (source == null) return;
    setState(() => _busy = true);
    Directory? temporary;
    try {
      File? file;
      if (source == 'Files') {
        final chosen = await FilePicker.pickFiles(
          type: FileType.custom,
          allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
        );
        if (chosen.isNotEmpty && chosen.first.path != null) {
          file = File(chosen.first.path!);
        }
      } else {
        final picked = await ImagePicker().pickImage(
          source: source == 'Camera' ? ImageSource.camera : ImageSource.gallery,
          imageQuality: 85,
        );
        if (picked == null) return;
        final photo = await BuyerPhotoService.prepare(
          picked,
          camera: source == 'Camera',
        );
        temporary = await Directory.systemTemp.createTemp('rider-doc-');
        file = File('${temporary.path}/document.${photo.extension}');
        await file.writeAsBytes(photo.bytes);
      }
      if (file == null) return;
      if (await file.length() > 5 * 1024 * 1024) {
        throw Exception('Each document must be at most 5 MB.');
      }
      final ext = file.path.split('.').last.toLowerCase();
      final path =
          '${BackendService.client.auth.currentUser!.id}/${DeliveryService.requestId()}.$ext';
      await BackendService.client.storage
          .from('rider-documents')
          .upload(path, file);
      if (mounted) {
        setState(() {
          _files[kind] = path;
          _dirty = true;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (temporary != null) await temporary.delete(recursive: true);
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submit() async {
    if (_busy || !_form.currentState!.validate()) return;
    if (_files.length < 2) {
      setState(
        () => _error = 'Upload the license and OR/CR for re-verification.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      _submitted ??= {
        'request_id': _request,
        'vehicle_type': _type,
        ...{for (final f in _fields.keys) f: _fields[f]!.text.trim()},
        ..._files,
      };
      await BackendService.client.request('rider/vehicle', _submitted);
      if (mounted) {
        setState(() => _dirty = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Submitted for logistics verification. Current approved details stay in use.',
            ),
          ),
        );
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (e is DatabaseException &&
          e.statusCode != null &&
          e.statusCode! < 500) {
        _submitted = null;
        _request = DeliveryService.requestId();
      }
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _back() async {
    if (_busy) return;
    final leave =
        !_dirty ||
        await showDialog<bool>(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('Discard vehicle changes?'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(c, false),
                    child: const Text('Keep editing'),
                  ),
                  TextButton(
                    onPressed: () => Navigator.pop(c, true),
                    child: const Text('Discard'),
                  ),
                ],
              ),
            ) ==
            true;
    if (leave && mounted) Navigator.pop(context);
  }

  @override
  void dispose() {
    for (final c in _fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_dirty && !_busy,
    onPopInvokedWithResult: (p, _) {
      if (!p) _back();
    },
    child: Scaffold(
      appBar: AppBar(title: const Text('Vehicle and documents')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_user != null) ...[
            Text(
              'License expiry: ${_user!['license_expiry'] ?? 'Not provided'}',
            ),
            for (final kind in ['license_file', 'or_cr_file'])
              TextButton(
                onPressed: () async {
                  final uid = BackendService.client.auth.currentUser!.id;
                  final path =
                      '$uid/${kind == 'license_file' ? 'legacy-license' : 'legacy-or-cr'}';
                  await launchUrl(
                    Uri.parse(
                      BackendService.client.storage
                          .from('rider-documents')
                          .getPublicUrl(path),
                    ),
                    mode: LaunchMode.externalApplication,
                  );
                },
                child: Text(
                  'View current ${kind == 'license_file' ? 'license' : 'OR/CR'}',
                ),
              ),
            for (final r in _requests)
              ListTile(
                title: Text('Vehicle change: ${r['status']}'),
                subtitle: Text(
                  '${r['reason'] ?? 'Waiting for logistics re-verification'}',
                ),
              ),
            Form(
              key: _form,
              child: Column(
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: const InputDecoration(
                      labelText: 'Vehicle type',
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'two_wheels',
                        child: Text('Motorcycle'),
                      ),
                      DropdownMenuItem(
                        value: 'four_wheels',
                        child: Text('Car / Van'),
                      ),
                    ],
                    onChanged: _busy
                        ? null
                        : (v) => setState(() {
                            _type = v!;
                            _dirty = true;
                          }),
                  ),
                  for (final f in _fields.keys)
                    TextFormField(
                      controller: _fields[f],
                      readOnly: f == 'license_expiry',
                      onTap: f != 'license_expiry'
                          ? null
                          : () async {
                              final today = DateUtils.dateOnly(DateTime.now());
                              final saved =
                                  DateTime.tryParse(_fields[f]!.text) ?? today;
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: saved.isBefore(today)
                                    ? today
                                    : saved,
                                firstDate: today,
                                lastDate: DateTime(today.year + 30),
                              );
                              if (picked != null && mounted) {
                                setState(() {
                                  _fields[f]!.text =
                                      '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
                                  _dirty = true;
                                });
                              }
                            },
                      enabled: !_busy && _submitted == null,
                      onChanged: (_) => setState(() => _dirty = true),
                      textCapitalization:
                          f == 'plate_number' || f == 'license_number'
                          ? TextCapitalization.characters
                          : TextCapitalization.words,
                      decoration: InputDecoration(
                        labelText:
                            f.replaceAll('_', ' ') +
                            (f == 'license_expiry' ? ' (YYYY-MM-DD)' : ''),
                      ),
                      validator: (v) =>
                          v == null || v.trim().isEmpty ? 'Required' : null,
                    ),
                  for (final kind in ['license_file', 'or_cr_file'])
                    OutlinedButton(
                      onPressed: _busy || _submitted != null
                          ? null
                          : () => _pick(kind),
                      child: Text(
                        '${_files.containsKey(kind) ? 'Replace' : 'Upload'} ${kind == 'license_file' ? 'license' : 'OR/CR'} (JPG, PNG, PDF)',
                      ),
                    ),
                  TextButton(
                    onPressed: Geolocator.openAppSettings,
                    child: const Text('Camera / photo settings'),
                  ),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    child: Text(
                      _busy ? 'Submitting…' : 'Submit for verification',
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (_error != null)
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
        ],
      ),
    ),
  );
}
