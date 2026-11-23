import 'dart:io';

import 'backend_service.dart';

class RegistrationDocumentsService {
  static const bucket = 'identity-documents';

  /// Uploads to `{authUid}/id_photo.<ext>`, returning the stored path.
  Future<String> uploadIdPhoto(String authUid, File file) =>
      _upload(authUid, file, 'id_photo');

  /// Uploads to `{authUid}/selfie.<ext>`, returning the stored path.
  Future<String> uploadSelfie(String authUid, File file) =>
      _upload(authUid, file, 'selfie');

  /// Uploads to `{authUid}/vehicle_or_cr.<ext>` (rider only, real document
  /// photo — previously only a license *number* text field existed).
  Future<String> uploadVehicleOrCr(String authUid, File file) =>
      _upload(authUid, file, 'vehicle_or_cr');

  /// Uploads to `{authUid}/license_photo.<ext>` (rider only).
  Future<String> uploadLicensePhoto(String authUid, File file) =>
      _upload(authUid, file, 'license_photo');

  Future<String> _upload(String authUid, File file, String baseName) async {
    final dot = file.path.lastIndexOf('.');
    final ext = dot == -1 ? '' : file.path.substring(dot + 1).toLowerCase();
    final path = '$authUid/$baseName.${ext.isEmpty ? 'jpg' : ext}';
    await BackendService.client.storage
        .from(bucket)
        .upload(path, file, fileOptions: const FileOptions(upsert: true));
    return path;
  }
}
