import 'package:flutter/material.dart';
import '../models/app_user.dart';
import '../services/auth_service.dart';
import '../utils/friendly_error.dart';

/// The "Edit contact & address" bottom sheet used by both Buyer and Rider
/// profile screens (they edit the same `users` columns via
/// `AuthService.updateMyProfile`) — was duplicated verbatim between the two
/// before being pulled out here.
///
/// Shows the sheet, saves on confirm, and returns the updated [AppUser] (or
/// null if the user cancelled or the save failed after showing its own
/// error message).
Future<AppUser?> showEditAddressSheet(BuildContext context, AppUser user) async {
  final contactCtrl = TextEditingController(text: user.contactNo ?? '');
  final houseCtrl = TextEditingController(text: user.houseNo ?? '');
  final streetCtrl = TextEditingController(text: user.street ?? '');
  final barangayCtrl = TextEditingController(text: user.barangay ?? '');
  final municipalityCtrl = TextEditingController(text: user.municipality ?? '');
  final provinceCtrl = TextEditingController(text: user.province ?? '');

  final saved = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (sheetContext) {
      return Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Edit contact & address',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: contactCtrl,
                decoration: const InputDecoration(labelText: 'Contact number'),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 12),
              TextField(controller: houseCtrl, decoration: const InputDecoration(labelText: 'House no.')),
              const SizedBox(height: 12),
              TextField(controller: streetCtrl, decoration: const InputDecoration(labelText: 'Street')),
              const SizedBox(height: 12),
              TextField(controller: barangayCtrl, decoration: const InputDecoration(labelText: 'Barangay')),
              const SizedBox(height: 12),
              TextField(controller: municipalityCtrl, decoration: const InputDecoration(labelText: 'Municipality/City')),
              const SizedBox(height: 12),
              TextField(controller: provinceCtrl, decoration: const InputDecoration(labelText: 'Province')),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: () => Navigator.pop(sheetContext, true),
                child: const Text('Save changes'),
              ),
            ],
          ),
        ),
      );
    },
  );

  if (saved != true || !context.mounted) return null;

  try {
    final updated = await AuthService().updateMyProfile(
      contactNo: contactCtrl.text.trim(),
      houseNo: houseCtrl.text.trim(),
      street: streetCtrl.text.trim(),
      barangay: barangayCtrl.text.trim(),
      municipality: municipalityCtrl.text.trim(),
      province: provinceCtrl.text.trim(),
    );
    if (!context.mounted) return updated;
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profile updated.')));
    return updated;
  } catch (e) {
    if (!context.mounted) return null;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Could not update profile: ${friendlyError(e)}')));
    return null;
  }
}
