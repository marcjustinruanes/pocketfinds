import 'package:flutter/material.dart';

import '../../../theme/app_colors.dart';
import '../../../theme/app_spacing.dart';
import '../../../widgets/primary_button.dart';

/// Shown right after a rider's application is submitted (real signup +
/// `complete_registration()` already ran — `status` is the real `pending`
/// your backend already gates login on). "Done" just returns to the sign-in
/// screen; the existing pending/blocked flow takes over from there once
/// they try to log in, same as it already does today.
class ApplicationSubmittedScreen extends StatelessWidget {
  final String centerName;

  const ApplicationSubmittedScreen({super.key, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    color: AppColors.pinkSoft,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.hourglass_top_rounded,
                    color: AppColors.pink,
                    size: 44,
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                Text(
                  'Application Under Review',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  "Your Courier/Rider application has been sent to $centerName. "
                  "We'll let you know once it's reviewed — you'll be able to sign in and start accepting tasks as soon as it's approved.",
                  style: TextStyle(
                    fontSize: 13.5,
                    color: AppColors.textMuted,
                    height: 1.5,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: AppSpacing.xxl),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(AppRadius.card),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    children: const [
                      _ChecklistRow(
                        label: 'Personal & vehicle information submitted',
                        done: true,
                      ),
                      _ChecklistRow(label: 'Requirements uploaded', done: true),
                      _ChecklistRow(
                        label: 'Logistics center review',
                        done: false,
                      ),
                      _ChecklistRow(label: 'Account approved', done: false),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xxl),
                PrimaryButton(
                  label: 'Done',
                  onPressed: () =>
                      Navigator.of(context).popUntil((route) => route.isFirst),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  final String label;
  final bool done;

  const _ChecklistRow({required this.label, required this.done});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(
            done ? Icons.check_circle : Icons.radio_button_unchecked,
            size: 18,
            color: done ? AppColors.success : AppColors.textMuted,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                color: done ? AppColors.textPrimary : AppColors.textMuted,
                fontWeight: done ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
