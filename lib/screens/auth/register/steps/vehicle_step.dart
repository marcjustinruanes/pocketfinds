import 'package:flutter/material.dart';

import '../../../../utils/validators.dart';
import '../../../../widgets/auth_text_field.dart';

/// Step 5 (rider only): vehicle details.
class VehicleStep extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final String vehicleType;
  final ValueChanged<String?> onVehicleTypeChanged;
  final TextEditingController brandController;
  final TextEditingController modelController;

  const VehicleStep({
    super.key,
    required this.formKey,
    required this.vehicleType,
    required this.onVehicleTypeChanged,
    required this.brandController,
    required this.modelController,
  });

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Vehicle type *',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 6),
          DropdownButtonFormField<String>(
            initialValue: vehicleType,
            items: const [
              DropdownMenuItem(value: 'two_wheels', child: Text('Motorcycle')),
              DropdownMenuItem(value: 'four_wheels', child: Text('Car / Van')),
            ],
            onChanged: onVehicleTypeChanged,
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Vehicle brand *',
            controller: brandController,
            validator: (v) => Validators.required(v, label: 'Vehicle brand'),
          ),
          const SizedBox(height: 12),
          AuthTextField(
            label: 'Vehicle model *',
            controller: modelController,
            validator: (v) => Validators.required(v, label: 'Vehicle model'),
          ),
        ],
      ),
    );
  }
}
