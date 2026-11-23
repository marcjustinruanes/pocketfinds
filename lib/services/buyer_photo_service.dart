import 'dart:io';
import 'dart:typed_data';

import 'package:image/image.dart' as img;
import 'package:image_picker/image_picker.dart';

import 'backend_service.dart';
import 'delivery_service.dart';

class BuyerPhoto {
  final Uint8List bytes;
  final String extension;
  String? uploadedPath;
  BuyerPhoto(this.bytes, this.extension);
}

class BuyerPhotoService {
  static Future<BuyerPhoto> prepare(XFile file, {bool camera = false}) async {
    var bytes = await file.readAsBytes();
    if (!camera && bytes.length > 5 * 1024 * 1024) {
      throw const DatabaseException('Each photo must be at most 5 MB.');
    }
    final jpeg =
        bytes.length > 3 &&
        bytes[0] == 255 &&
        bytes[1] == 216 &&
        bytes[2] == 255;
    final png =
        bytes.length > 8 &&
        bytes[0] == 137 &&
        bytes[1] == 80 &&
        bytes[2] == 78 &&
        bytes[3] == 71;
    if (!jpeg && !png) {
      throw const DatabaseException('Choose an actual JPG or PNG image.');
    }
    var decoded = img.decodeImage(bytes);
    if (decoded == null) {
      throw const DatabaseException(
        'This image could not be read. Choose another photo.',
      );
    }
    var extension = png ? 'png' : 'jpg';
    if (camera && bytes.length > 5 * 1024 * 1024) {
      for (var quality = 85; quality >= 35; quality -= 10) {
        if (decoded!.width > 2400) {
          decoded = img.copyResize(decoded, width: 2400);
        }
        extension = 'jpg';
        bytes = Uint8List.fromList(img.encodeJpg(decoded, quality: quality));
        if (bytes.length <= 5 * 1024 * 1024) break;
      }
    }
    if (bytes.length > 5 * 1024 * 1024) {
      throw const DatabaseException(
        'Photo remains over 5 MB. Retake at a lower resolution.',
      );
    }
    return BuyerPhoto(bytes, extension);
  }

  static Future<String> upload(BuyerPhoto photo, String bucket) async {
    if (photo.uploadedPath != null) return photo.uploadedPath!;
    final uid = BackendService.client.auth.currentUser?.id;
    if (uid == null) throw const AuthException('Sign in before uploading.');
    final name = '${DeliveryService.requestId()}.${photo.extension}';
    final dir = await Directory.systemTemp.createTemp('pocketfinds-photo-');
    final file = File('${dir.path}/$name');
    try {
      await file.writeAsBytes(photo.bytes);
      final path = '$uid/$name';
      await BackendService.client.storage.from(bucket).upload(path, file);
      photo.uploadedPath = path;
      return path;
    } finally {
      if (await file.exists()) await file.delete();
      await dir.delete();
    }
  }
}
