import 'dart:io';

import 'package:image/image.dart' as img;

/// Computes the same coarse 48-number color signature used to backfill
/// `products.image_signature` (see `scripts/backfill_image_signatures.js`)
/// — a real, working visual-similarity technique (a 4×4 grid of average
/// RGB), not a deep semantic embedding. Two products that are mostly the
/// same colors/shapes in the same layout score as similar; two products
/// that are the same *item* but photographed very differently (angle,
/// background, lighting) may not.
class ImageSignatureService {
  static const _gridSize = 4;

  /// Returns 48 floats (4×4 cells × R,G,B, each 0.0–1.0), or null if the
  /// file can't be decoded as an image.
  List<double>? computeSignature(File file) {
    final bytes = file.readAsBytesSync();
    final decoded = img.decodeImage(bytes);
    if (decoded == null) return null;

    final resized = img.copyResize(
      decoded,
      width: _gridSize,
      height: _gridSize,
    );
    final values = <double>[];
    for (var y = 0; y < _gridSize; y++) {
      for (var x = 0; x < _gridSize; x++) {
        final pixel = resized.getPixel(x, y);
        values.add(pixel.r / 255.0);
        values.add(pixel.g / 255.0);
        values.add(pixel.b / 255.0);
      }
    }
    return values;
  }
}
