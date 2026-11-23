import 'dart:io';

import 'package:flutter/material.dart';

import '../../../../theme/app_colors.dart';
import '../../../../theme/app_spacing.dart';
import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';
import '../../../../widgets/upload_tile.dart';

/// Step 6 (rider only): plate number, driver's license, and — real photo
/// uploads, not just the license number — the vehicle's OR/CR and a photo
/// of the license itself. Bicycles don't collect any of this (matches the
/// original single-page form's `_vehicleNeedsDocuments` rule) — [required]
/// is false when the chosen vehicle is a bicycle, and the fields are shown
/// as optional in that case.
class LicenseStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final bool required;
  final TextEditingController plateNumberController;
  final TextEditingController licenseNumberController;
  final DateTime? licenseExpiry;
  final VoidCallback onPickLicenseExpiry;
  final File? orCrPhoto;
  final File? licensePhoto;
  final VoidCallback onPickOrCrPhoto;
  final VoidCallback onPickLicensePhoto;

  const LicenseStep({
    super.key,
    required this.formKey,
    required this.required,
    required this.plateNumberController,
    required this.licenseNumberController,
    required this.licenseExpiry,
    required this.onPickLicenseExpiry,
    required this.orCrPhoto,
    required this.licensePhoto,
    required this.onPickOrCrPhoto,
    required this.onPickLicensePhoto,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (!required) ...[
            Text(
              "Bicycles don't need a plate number or license — you can skip these.",
              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            const SizedBox(height: 12),
          ],
          AuthTextField(
            label: required ? 'Plate number *' : 'Plate number',
            controller: plateNumberController,
            validator: required
                ? (v) => Validators.required(v, label: 'Plate number')
                : null,
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: required
                ? "Driver's license number *"
                : "Driver's license number",
            controller: licenseNumberController,
            validator: required
                ? (v) => Validators.required(v, label: 'License number')
                : null,
          ),
          const SizedBox(height: 12),
          Text(
            required ? 'License expiry *' : 'License expiry',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          InkWell(
            onTap: onPickLicenseExpiry,
            child: InputDecorator(
              decoration: const InputDecoration(
                suffixIcon: Icon(Icons.calendar_today_outlined, size: 16),
              ),
              child: Text(
                licenseExpiry == null
                    ? 'mm/dd/yyyy'
                    : '${licenseExpiry!.month.toString().padLeft(2, '0')}/${licenseExpiry!.day.toString().padLeft(2, '0')}/${licenseExpiry!.year}',
                style: TextStyle(
                  color: licenseExpiry == null
                      ? AppColors.textMuted
                      : AppColors.textPrimary,
                ),
              ),
            ),
          ),
          if (required) ...[
            const SizedBox(height: AppSpacing.lg),
            const Text(
              'Vehicle OR/CR *',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            UploadTile(
              icon: Icons.description_outlined,
              placeholder: 'Upload a photo of your OR/CR',
              actionLabel: 'Upload',
              image: orCrPhoto,
              onTap: onPickOrCrPhoto,
            ),
            const SizedBox(height: AppSpacing.lg),
            const Text(
              "Driver's license photo *",
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            UploadTile(
              icon: Icons.badge_outlined,
              placeholder: "Upload a photo of your driver's license",
              actionLabel: 'Upload',
              image: licensePhoto,
              onTap: onPickLicensePhoto,
            ),
          ],
        ],
      ),
    );
  }
}
