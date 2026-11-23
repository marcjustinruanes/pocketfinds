import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import 'package:geolocator/geolocator.dart';

import '../../services/backend_service.dart';
import '../../services/buyer_photo_service.dart';
import '../../services/rider_service.dart';
import '../../services/delivery_service.dart';
import '../../utils/friendly_error.dart';

class DeliveryCompletionScreen extends StatefulWidget {
  final String shipmentId;
  final double codAmount;
  const DeliveryCompletionScreen({
    super.key,
    required this.shipmentId,
    required this.codAmount,
  });
  @override
  State<DeliveryCompletionScreen> createState() =>
      _DeliveryCompletionScreenState();
}

class _DeliveryCompletionScreenState extends State<DeliveryCompletionScreen> {
  static const _storage = FlutterSecureStorage();
  final _amount = TextEditingController();
  Map<String, dynamic> _draft = {};
  bool _busy = false, _loaded = false;
  String? _error;
  Future<void> _writes = Future.value();
  String get _key =>
      'delivery:${BackendService.client.auth.currentUser!.id}:${widget.shipmentId}';
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final saved = await _storage.read(key: _key);
      if (saved != null) {
        _draft = Map<String, dynamic>.from(jsonDecode(saved) as Map);
      }
      _amount.text =
          '${_draft['cod_amount'] ?? widget.codAmount.toStringAsFixed(2)}';
    } catch (e) {
      _error = 'Secure draft storage is unavailable. ${friendlyError(e)}';
    }
    if (mounted) setState(() => _loaded = true);
  }

  Future<void> _save() {
    _draft.remove('code');
    _draft['cod_amount'] = _amount.text;
    final value = jsonEncode(_draft);
    final key = _key;
    _writes = _writes
        .catchError((_) {})
        .then((_) => _storage.write(key: key, value: value));
    return _writes;
  }

  void _edited() {
    _save().catchError((e) {
      if (mounted) {
        setState(
          () => _error = 'Could not save the secure draft: ${friendlyError(e)}',
        );
      }
    });
  }

  Future<void> _capture() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final challenge = await BackendService.client.request(
        'delivery/capture',
        {'shipment_id': widget.shipmentId},
      ) as Map;
      final picked = await ImagePicker().pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );
      if (picked == null) return;
      final photo = await BuyerPhotoService.prepare(picked, camera: true);
      final decoded = img.decodeImage(photo.bytes);
      if (decoded == null) throw Exception('Retake a readable photo.');
      final bytes = img.encodeJpg(decoded, quality: 85);
      if (bytes.length > 5 * 1024 * 1024) {
        throw Exception(
          'Retake the photo at a lower resolution (maximum 5 MB).',
        );
      }
      final folder = await getApplicationDocumentsDirectory();
      final file = File(
        '${folder.path}/delivery-${BackendService.client.auth.currentUser!.id}-${widget.shipmentId}.jpg',
      );
      await file.writeAsBytes(bytes, flush: true);
      _draft = {
        'photo_file': file.path,
        'photo':
            '${BackendService.client.auth.currentUser!.id}/${challenge['token']}.jpg',
        'request_id': DeliveryService.requestId(),
      };
      await _save();
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submit() async {
    if (_busy) return;
    if (_draft['submitted'] == null && _draft['photo_file'] == null) {
      setState(
        () => _error =
            'Take a live proof photo before confirming delivery.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_draft['submitted'] == null) {
        final amount = widget.codAmount == 0
            ? 0.0
            : double.tryParse(_amount.text);
        if (amount == null ||
            (amount * 100).round() != (widget.codAmount * 100).round()) {
          throw UserVisibleException(
            'Collected COD must exactly match ${widget.codAmount.toStringAsFixed(2)}.',
          );
        }
        final p = await RiderService.location();
        _draft['submitted'] = {
          'shipment_id': widget.shipmentId,
          'request_id': _draft['request_id'] ?? DeliveryService.requestId(),
          'delivery_proof': _draft['photo'],
          'cod_confirmed': widget.codAmount > 0,
          'cod_amount': amount,
          'latitude': p.latitude,
          'longitude': p.longitude,
        };
        await _save();
      }
      if (_draft['uploaded'] != true) {
        await BackendService.client.storage
            .from('delivery-proofs')
            .upload(
              '${_draft['photo']}',
              File('${_draft['photo_file']}'),
              fileOptions: const FileOptions(upsert: false),
            );
        _draft['uploaded'] = true;
        await _save();
      }
      await BackendService.client.request(
        'delivery/complete',
        _draft['submitted'],
      );
      await _writes;
      await _storage.delete(key: _key);
      final file = File('${_draft['photo_file']}');
      if (await file.exists()) await file.delete();
      DeliveryService.updates.add(null);
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (e is DatabaseException &&
          e.statusCode != null &&
          e.statusCode! >= 400 &&
          e.statusCode! < 500) {
        // A definitive rejection allows correction; unknown network outcomes retain
        // the exact request for replay rather than submitting another delivery.
        _draft.remove('submitted');
        _draft['request_id'] = DeliveryService.requestId();
        await _save();
      }
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final locked = _busy || _draft['submitted'] != null;
    return Scaffold(
      appBar: AppBar(title: const Text('Confirm delivery')),
      body: !_loaded
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                const Text(
                  'Take the photo at handover and confirm payment. Your unfinished submission is saved securely on this phone.',
                ),
                if (_draft['photo_file'] != null)
                  Image.file(
                    File('${_draft['photo_file']}'),
                    height: 220,
                    errorBuilder: (_, _, _) => const Text(
                      'Photo unavailable. Retake it before submitting.',
                    ),
                  ),
                OutlinedButton(
                  onPressed: locked ? null : _capture,
                  child: const Text('Take live proof photo'),
                ),
                if (widget.codAmount > 0)
                  TextField(
                    controller: _amount,
                    enabled: !locked,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    onChanged: (_) => _edited(),
                    decoration: InputDecoration(
                      labelText:
                          'COD collected · expected ${widget.codAmount.toStringAsFixed(2)}',
                    ),
                  )
                else
                  const Text('Prepaid · no COD to collect'),
                if (_error != null)
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                TextButton(
                  onPressed: Geolocator.openLocationSettings,
                  child: const Text('Turn on Location'),
                ),
                TextButton(
                  onPressed: Geolocator.openAppSettings,
                  child: const Text('Camera / location permissions'),
                ),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: Text(
                    _busy
                        ? 'Verifying…'
                        : _draft['submitted'] != null
                        ? 'Retry saved delivery'
                        : 'Complete Delivery',
                  ),
                ),
              ],
            ),
    );
  }
}
