import 'package:flutter/material.dart';

import '../../../../theme/app_spacing.dart';
import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';

/// Step 4: home address.
class AddressStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController provinceController;
  final TextEditingController municipalityController;
  final TextEditingController barangayController;
  final TextEditingController houseNoController;
  final TextEditingController streetController;

  const AddressStep({
    super.key,
    required this.formKey,
    required this.provinceController,
    required this.municipalityController,
    required this.barangayController,
    required this.houseNoController,
    required this.streetController,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AuthTextField(
            label: 'Province *',
            controller: provinceController,
            validator: (v) => Validators.required(v, label: 'Province'),
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Municipality/City *',
            controller: municipalityController,
            validator: (v) =>
                Validators.required(v, label: 'Municipality/City'),
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Barangay *',
            controller: barangayController,
            validator: (v) => Validators.required(v, label: 'Barangay'),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: AuthTextField(
                  label: 'House no.',
                  controller: houseNoController,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: AuthTextField(
                  label: 'Street',
                  controller: streetController,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
