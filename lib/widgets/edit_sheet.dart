import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import '../utils/friendly_error.dart';
import 'primary_button.dart';

/// Shared modal-sheet shape for every "edit one thing" flow — a title, a
/// list of fields, an error banner on failure, and a Save button that only
/// pops (with `true`) once [onSave] succeeds. Used by both Settings (each
/// Account field) and Profile's "Edit Profile" button, so the two entry
/// points share one real implementation instead of two copies drifting
/// apart.
class EditSheet extends StatefulWidget {
  final String title;
  final String? subtitle;
  final String saveLabel;
  final List<Widget> fields;
  final Future<void> Function() onSave;

  const EditSheet({
    super.key,
    required this.title,
    this.subtitle,
    this.saveLabel = 'Save',
    required this.fields,
    required this.onSave,
  });

  @override
  State<EditSheet> createState() => _EditSheetState();
}

class _EditSheetState extends State<EditSheet> {
  bool _saving = false;
  String? _error;

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave();
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.title, style: AppTypography.sectionTitle),
          if (widget.subtitle != null) ...[
            const SizedBox(height: 4),
            Text(widget.subtitle!, style: AppTypography.caption),
          ],
          const SizedBox(height: AppSpacing.lg),
          for (final field in widget.fields) ...[
            field,
            const SizedBox(height: AppSpacing.md),
          ],
          if (_error != null) ...[
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: AppColors.dangerSoft,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: AppColors.danger, size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _error!,
                      style: TextStyle(color: AppColors.danger, fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          PrimaryButton(
            label: widget.saveLabel,
            loading: _saving,
            onPressed: _save,
          ),
        ],
      ),
    );
  }
}
