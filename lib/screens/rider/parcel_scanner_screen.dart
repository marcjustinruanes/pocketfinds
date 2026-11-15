import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:geolocator/geolocator.dart';

class ParcelScannerScreen extends StatefulWidget {
  const ParcelScannerScreen({super.key});
  @override
  State<ParcelScannerScreen> createState() => _ParcelScannerScreenState();
}

class _ParcelScannerScreenState extends State<ParcelScannerScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _done = false;
  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Scan parcel'),
      actions: [
        IconButton(
          onPressed: _controller.toggleTorch,
          icon: const Icon(Icons.flashlight_on_outlined),
          tooltip: 'Toggle flashlight',
        ),
      ],
    ),
    body: MobileScanner(
      controller: _controller,
      onDetect: (capture) {
        for (final barcode in capture.barcodes) {
          final code = barcode.rawValue;
          if (!_done && code != null && code.isNotEmpty) {
            _done = true;
            Navigator.pop(context, code);
            break;
          }
        }
      },
      errorBuilder: (context, error) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.no_photography_outlined, size: 40),
              const SizedBox(height: 16),
              const Text(
                'Camera unavailable. Allow camera access in device settings, or enter the parcel tracking ID.',
              ),
              TextButton(
                onPressed: Geolocator.openAppSettings,
                child: const Text('Open Settings'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Enter tracking ID instead'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
