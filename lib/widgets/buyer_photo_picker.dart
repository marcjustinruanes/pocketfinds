import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';

import '../services/buyer_photo_service.dart';
import '../utils/friendly_error.dart';

class BuyerPhotoPicker extends StatefulWidget {
  final List<BuyerPhoto> photos;
  final bool enabled;
  const BuyerPhotoPicker({
    super.key,
    required this.photos,
    this.enabled = true,
  });
  @override
  State<BuyerPhotoPicker> createState() => _BuyerPhotoPickerState();
}

class _BuyerPhotoPickerState extends State<BuyerPhotoPicker> {
  String? _error;
  bool _busy = false, _denied = false;
  Future<void> _pick(bool camera) async {
    if (widget.photos.length >= 5) {
      setState(() => _error = 'Attach at most five photos.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _denied = false;
    });
    try {
      final picker = ImagePicker();
      final files = camera
          ? [
              await picker.pickImage(
                source: ImageSource.camera,
                imageQuality: 90,
              ),
            ].whereType<XFile>().toList()
          : await picker.pickMultiImage(limit: 5 - widget.photos.length);
      if (files.length + widget.photos.length > 5) {
        throw const FormatException('Attach at most five photos.');
      }
      final prepared = <BuyerPhoto>[];
      for (final file in files) {
        prepared.add(await BuyerPhotoService.prepare(file, camera: camera));
      }
      if (mounted) setState(() => widget.photos.addAll(prepared));
    } on PlatformException catch (e) {
      if (mounted) {
        setState(() {
          _denied =
              e.code.toLowerCase().contains('denied') ||
              e.code.toLowerCase().contains('access');
          _error = _denied
              ? 'Camera or photo access is off. Enable it in Settings or choose another source.'
              : e.message;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is FormatException ? e.message : friendlyError(e),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (var i = 0; i < widget.photos.length; i++)
            SizedBox(
              width: 92,
              height: 100,
              child: Stack(
                children: [
                  Image.memory(
                    widget.photos[i].bytes,
                    width: 92,
                    height: 100,
                    fit: BoxFit.cover,
                  ),
                  Positioned(
                    right: 0,
                    top: 0,
                    child: IconButton(
                      tooltip: 'Remove photo',
                      onPressed: widget.enabled && !_busy
                          ? () => setState(() => widget.photos.removeAt(i))
                          : null,
                      icon: const Icon(Icons.cancel, color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
      Wrap(
        spacing: 12,
        children: [
          OutlinedButton.icon(
            onPressed: widget.enabled && !_busy ? () => _pick(true) : null,
            icon: const Icon(Icons.camera_alt_outlined),
            label: const Text('Camera'),
          ),
          OutlinedButton.icon(
            onPressed: widget.enabled && !_busy ? () => _pick(false) : null,
            icon: const Icon(Icons.photo_library_outlined),
            label: const Text('Gallery'),
          ),
        ],
      ),
      const Text('JPG or PNG, up to 5 MB each. Maximum five photos.'),
      if (_error != null)
        Text(
          _error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
      if (_denied)
        TextButton(
          onPressed: Geolocator.openAppSettings,
          child: const Text('Open Settings'),
        ),
    ],
  );
}
