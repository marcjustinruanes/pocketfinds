import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class CameraScreen extends StatefulWidget {
  final String title;
  final Widget Function(ValueChanged<String?> onCode)? previewBuilder;
  const CameraScreen({super.key, required this.title, this.previewBuilder});
  @override
  State<CameraScreen> createState() => _CameraScreenState();
}

class _CameraScreenState extends State<CameraScreen> {
  final controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  final manual = TextEditingController();
  bool picked = false;
  void choose(String? code) {
    if (picked || code == null || code.trim().isEmpty) return;
    picked = true;
    Navigator.pop(context, code.trim());
  }

  @override
  void dispose() {
    controller.dispose();
    manual.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.title),
      actions: [
        IconButton(
          tooltip: 'Toggle flashlight',
          onPressed: controller.toggleTorch,
          icon: const Icon(Icons.flash_on),
        ),
      ],
    ),
    body: Column(
      children: [
        Expanded(
          child: Stack(
            fit: StackFit.expand,
            children: [
              widget.previewBuilder?.call(choose) ??
                  MobileScanner(
                    controller: controller,
                    onDetect: (capture) {
                      if (capture.barcodes.isNotEmpty) {
                        choose(capture.barcodes.first.rawValue);
                      }
                    },
                    errorBuilder: (context, error) => const Center(
                      child: Text(
                        'Camera unavailable. Allow permission or enter a code below.',
                      ),
                    ),
                  ),
              IgnorePointer(
                child: Center(
                  child: Container(
                    width: 260,
                    height: 180,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white, width: 3),
                      borderRadius: BorderRadius.circular(20),
                    ),
                  ),
                ),
              ),
              const Positioned(
                left: 20,
                right: 20,
                bottom: 20,
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: Color(0xbb22202a),
                      borderRadius: BorderRadius.all(Radius.circular(12)),
                    ),
                    child: Padding(
                      padding: EdgeInsets.all(12),
                      child: Text(
                        'Place the barcode or QR code inside the frame',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              TextField(
                controller: manual,
                decoration: const InputDecoration(
                  labelText: 'Manual code / pairing link',
                ),
                onSubmitted: choose,
              ),
              FilledButton(
                onPressed: () => choose(manual.text),
                child: const Text('Use entered code'),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
