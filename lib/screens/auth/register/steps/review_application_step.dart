import 'package:flutter/material.dart';

import '../../../../theme/app_colors.dart';
import '../../../../theme/app_spacing.dart';

/// Step: "Review Application" — a read-only summary of everything entered
/// so far, shown right before the rider submits. Purely a display step
/// (no form of its own) — the wizard's Continue button just advances past
/// it once shown.
class ReviewApplicationStep extends StatelessWidget {
  final String centerName;
  final String fullName;
  final String contactNo;
  final String address;
  final String vehicleSummary;

  const ReviewApplicationStep({
    super.key,
    required this.centerName,
    required this.fullName,
    required this.contactNo,
    required this.address,
    required this.vehicleSummary,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _section('Logistics Center', [('Applying to', centerName)]),
        const SizedBox(height: AppSpacing.md),
        _section('Personal Information', [
          ('Full name', fullName),
          ('Contact number', contactNo),
        ]),
        const SizedBox(height: AppSpacing.md),
        _section('Address', [('Address', address)]),
        const SizedBox(height: AppSpacing.md),
        _section('Vehicle', [('Vehicle', vehicleSummary)]),
        const SizedBox(height: AppSpacing.md),
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: AppColors.infoSoft,
            borderRadius: BorderRadius.circular(AppRadius.card),
            border: Border.all(color: AppColors.infoLine),
          ),
          child: Row(
            children: [
              Icon(Icons.info_outline, size: 16, color: AppColors.info),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Submitting sends your application to the selected logistics center for review. '
                  "You'll be notified once it's approved.",
                  style: TextStyle(fontSize: 12, color: AppColors.info),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _section(String title, List<(String, String)> rows) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              color: AppColors.pinkDark,
            ),
          ),
          const SizedBox(height: 8),
          ...rows.map(
            (r) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 110,
                    child: Text(
                      r.$1,
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      r.$2,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
