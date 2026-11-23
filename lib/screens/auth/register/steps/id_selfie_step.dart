import 'dart:io';

import 'package:flutter/material.dart';

import '../../../../theme/app_colors.dart';
import '../../../../theme/app_spacing.dart';
import '../../../../widgets/upload_tile.dart';

/// Philippine ID types the wizard offers — matches the reference design's
/// dropdown. Values are stored as-is in `users.id_type`.
const List<String> kIdTypes = [
  "Driver's License",
  'Other Government-Issued ID',
  'Philippine Passport',
  'PhilSys ID',
  'Postal ID',
  'PRC ID',
  'Senior Citizen ID',
  'UMID',
  "Voter's ID",
];

/// Step 1: pick an ID type, upload a photo of the ID (OCR pre-fills the next
/// step), and take a selfie. Deliberately image-only (JPG/PNG) — PDF upload
/// would need an extra file-picker package/pipeline beyond this pass's scope.
class IdSelfieStep extends StatelessWidget {
  final String? idType;
  final ValueChanged<String?> onIdTypeChanged;
  final File? idPhoto;
  final File? selfie;
  final bool ocrRunning;
  final VoidCallback onPickIdPhoto;
  final VoidCallback onPickSelfie;

  const IdSelfieStep({
    super.key,
    required this.idType,
    required this.onIdTypeChanged,
    required this.idPhoto,
    required this.selfie,
    required this.ocrRunning,
    required this.onPickIdPhoto,
    required this.onPickSelfie,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'ID Type *',
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: idType,
          isExpanded: true,
          hint: const Text('Select ID type'),
          items: kIdTypes
              .map((t) => DropdownMenuItem(value: t, child: Text(t)))
              .toList(),
          onChanged: onIdTypeChanged,
        ),
        const SizedBox(height: AppSpacing.lg),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _Labeled(
                label: 'ID Photo *',
                child: UploadTile(
                  icon: Icons.badge_outlined,
                  placeholder: 'Upload your ID document',
                  actionLabel: 'Upload',
                  hint: 'Clear photo or scan · JPG or PNG · max 5MB',
                  image: idPhoto,
                  loading: ocrRunning,
                  onTap: onPickIdPhoto,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: _Labeled(
                label: 'Selfie *',
                child: UploadTile(
                  icon: Icons.face_outlined,
                  placeholder: 'Take a selfie',
                  actionLabel: 'Open Camera',
                  image: selfie,
                  onTap: onPickSelfie,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _Labeled extends StatelessWidget {
  final String label;
  final Widget child;

  const _Labeled({required this.label, required this.child});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }
}
