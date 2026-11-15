import 'package:flutter/material.dart';

import '../../../../theme/app_colors.dart';
import '../../../../theme/app_spacing.dart';
import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';

/// Step 2: personal details, pre-filled from step 1's OCR pass when
/// confident enough — otherwise [showOcrFailedBanner] is true and the user
/// is told to fill everything in manually. Every field stays editable
/// either way.
class PersonalInfoStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final bool showOcrFailedBanner;
  final TextEditingController lastNameController;
  final TextEditingController givenNamesController;
  final TextEditingController middleNameController;
  final String? sex;
  final ValueChanged<String?> onSexChanged;
  final DateTime? birthday;
  final VoidCallback onPickBirthday;

  const PersonalInfoStep({
    super.key,
    required this.formKey,
    required this.showOcrFailedBanner,
    required this.lastNameController,
    required this.givenNamesController,
    required this.middleNameController,
    required this.sex,
    required this.onSexChanged,
    required this.birthday,
    required this.onPickBirthday,
  });

  int? get _age {
    if (birthday == null) return null;
    final now = DateTime.now();
    var age = now.year - birthday!.year;
    if (now.month < birthday!.month ||
        (now.month == birthday!.month && now.day < birthday!.day)) {
      age--;
    }
    return age;
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (showOcrFailedBanner) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.pinkSoft,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.pinkBorder),
              ),
              child: Text(
                'Could not read your ID — please fill in your details manually.',
                style: TextStyle(
                  color: AppColors.pinkDark,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          Row(
            children: [
              Expanded(
                child: AuthTextField(
                  label: 'Last name *',
                  controller: lastNameController,
                  validator: (v) => Validators.required(v, label: 'Last name'),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: AuthTextField(
                  label: 'Given names *',
                  controller: givenNamesController,
                  validator: (v) =>
                      Validators.required(v, label: 'Given names'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: AuthTextField(
                  label: 'Middle name',
                  controller: middleNameController,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(child: _sexDropdown()),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: _birthdayField()),
              const SizedBox(width: AppSpacing.md),
              Expanded(child: _ageField()),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sexDropdown() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Sex *',
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: sex,
          hint: const Text('Select sex'),
          items: const [
            DropdownMenuItem(value: 'male', child: Text('Male')),
            DropdownMenuItem(value: 'female', child: Text('Female')),
          ],
          onChanged: onSexChanged,
        ),
      ],
    );
  }

  Widget _birthdayField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Birthday *',
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        InkWell(
          onTap: onPickBirthday,
          child: InputDecorator(
            decoration: const InputDecoration(
              suffixIcon: Icon(Icons.calendar_today_outlined, size: 16),
            ),
            child: Text(
              birthday == null
                  ? 'mm/dd/yyyy'
                  : '${birthday!.month.toString().padLeft(2, '0')}/${birthday!.day.toString().padLeft(2, '0')}/${birthday!.year}',
              style: TextStyle(
                color: birthday == null
                    ? AppColors.textMuted
                    : AppColors.textPrimary,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _ageField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Age',
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        InputDecorator(
          decoration: InputDecoration(
            filled: true,
            fillColor: AppColors.pageBackground,
          ),
          child: Text(
            _age?.toString() ?? 'Auto-generated',
            style: TextStyle(
              color: _age == null ? AppColors.textMuted : AppColors.textPrimary,
            ),
          ),
        ),
      ],
    );
  }
}
