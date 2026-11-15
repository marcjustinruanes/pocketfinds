import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../utils/validators.dart';
import '../../widgets/auth_scaffold.dart';
import '../../widgets/auth_text_field.dart';

/// Mobile translation of `resources/views/auth/register.blade.php` for
/// Buyer/Rider accounts, using Supabase Auth `signUp()` + the
/// `complete_registration()` RPC instead of Laravel's form post.
///
/// Known deviations from the website, both flagged in the project summary:
/// - ID photo / selfie / vehicle document uploads are skipped for now — no
///   Supabase Storage bucket exists yet, and those columns are nullable.
/// - Gmail-only OTP email verification is replaced by Supabase Auth's own
///   (optional, project-setting-controlled) email confirmation.
class RegisterScreen extends StatefulWidget {
  final String accountType; // 'buyer' or 'rider'

  const RegisterScreen({super.key, required this.accountType});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _authService = AuthService();

  final _lastNameController = TextEditingController();
  final _givenNamesController = TextEditingController();
  final _middleNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _contactNoController = TextEditingController();
  final _provinceController = TextEditingController();
  final _municipalityController = TextEditingController();
  final _barangayController = TextEditingController();
  final _houseNoController = TextEditingController();
  final _streetController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  final _vehicleBrandController = TextEditingController();
  final _vehicleModelController = TextEditingController();
  final _plateNumberController = TextEditingController();
  final _licenseNumberController = TextEditingController();

  String? _sex;
  DateTime? _birthday;
  String _vehicleType = 'motorcycle';
  DateTime? _licenseExpiry;

  bool _submitting = false;
  String? _errorMessage;

  bool get _isRider => widget.accountType == 'rider';
  bool get _vehicleNeedsDocuments => _vehicleType != 'bicycle';

  @override
  void dispose() {
    _lastNameController.dispose();
    _givenNamesController.dispose();
    _middleNameController.dispose();
    _emailController.dispose();
    _contactNoController.dispose();
    _provinceController.dispose();
    _municipalityController.dispose();
    _barangayController.dispose();
    _houseNoController.dispose();
    _streetController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _vehicleBrandController.dispose();
    _vehicleModelController.dispose();
    _plateNumberController.dispose();
    _licenseNumberController.dispose();
    super.dispose();
  }

  Future<void> _pickBirthday() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(now.year - 100),
      lastDate: now,
    );
    if (picked != null) setState(() => _birthday = picked);
  }

  Future<void> _pickLicenseExpiry() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: now,
      lastDate: DateTime(now.year + 20),
    );
    if (picked != null) setState(() => _licenseExpiry = picked);
  }

  int _calculateAge(DateTime birthday) {
    final now = DateTime.now();
    var age = now.year - birthday.year;
    if (now.month < birthday.month ||
        (now.month == birthday.month && now.day < birthday.day)) {
      age--;
    }
    return age;
  }

  String _isoDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_sex == null) {
      setState(() => _errorMessage = 'Please select your sex.');
      return;
    }
    if (_birthday == null) {
      setState(() => _errorMessage = 'Please select your birthday.');
      return;
    }
    if (_passwordController.text != _confirmPasswordController.text) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }
    if (_isRider && _vehicleNeedsDocuments) {
      if (_plateNumberController.text.trim().isEmpty ||
          _licenseNumberController.text.trim().isEmpty ||
          _licenseExpiry == null) {
        setState(() => _errorMessage = 'Please complete your vehicle and license details.');
        return;
      }
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      final metadata = <String, dynamic>{
        'account_type': widget.accountType,
        'last_name': _lastNameController.text.trim(),
        'given_names': _givenNamesController.text.trim(),
        'middle_name': _middleNameController.text.trim(),
        'sex': _sex,
        'birthday': _isoDate(_birthday!),
        'age': _calculateAge(_birthday!),
        'contact_no': _contactNoController.text.trim(),
        'province': _provinceController.text.trim(),
        'municipality': _municipalityController.text.trim(),
        'barangay': _barangayController.text.trim(),
        'house_no': _houseNoController.text.trim(),
        'street': _streetController.text.trim(),
        'username': _usernameController.text.trim(),
        if (_isRider) ...{
          'vehicle_type': _vehicleType,
          'vehicle_brand': _vehicleBrandController.text.trim(),
          'vehicle_model': _vehicleModelController.text.trim(),
          if (_vehicleNeedsDocuments) ...{
            'plate_number': _plateNumberController.text.trim(),
            'license_number': _licenseNumberController.text.trim(),
            'license_expiry': _isoDate(_licenseExpiry!),
          },
        },
      };

      // This whole sequence (signUp -> completeRegistration -> signOut) is
      // its own orchestrated flow — suppress the app-wide auth listener so
      // it doesn't also try to resolve/navigate mid-sequence and race with
      // the dialog + explicit pop below.
      AuthService.suppressGlobalNavigation = true;
      String successMessage;
      try {
        final response = await _authService.signUp(
          email: _emailController.text,
          password: _passwordController.text,
          metadata: metadata,
        );

        if (response.session != null) {
          // Confirmation disabled (or not required) — we already have a
          // session, so create the profile row right now.
          await _authService.completeRegistration();
          await _authService.signOut(); // status is 'pending' either way
          successMessage =
              'Account created! Your account is now pending admin approval. '
              "You'll be able to sign in once it's approved.";
        } else {
          // Email confirmation required — the profile gets created on
          // their first successful login instead (AuthService.resolveSession
          // falls back to completeRegistration() when no profile exists yet).
          successMessage =
              'Almost there! Check your email to confirm your address. '
              "Once confirmed, you'll be able to sign in — your account "
              'will still need admin approval after that.';
        }
      } finally {
        AuthService.suppressGlobalNavigation = false;
      }

      if (!mounted) return;
      await _showSuccessDialog(successMessage);
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
    } on AuthException catch (e) {
      setState(() => _errorMessage = e.message);
    } on PostgrestException catch (e) {
      setState(() => _errorMessage = e.message);
    } catch (e) {
      setState(() => _errorMessage = 'Something went wrong. Please try again.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _showSuccessDialog(String message) {
    return showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Registration submitted'),
        content: Text(message),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      eyebrow: _isRider ? 'Rider Registration' : 'Buyer Registration',
      title: _isRider ? 'Register as a Rider' : 'Register as a Buyer',
      subtitle: 'Fill in your details to create your PocketFinds account.',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_errorMessage != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.dangerSoft,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, color: AppColors.danger, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: const TextStyle(color: AppColors.danger, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
            _sectionCard(
              title: 'Personal information',
              children: [
                AuthTextField(
                  label: 'Last name',
                  controller: _lastNameController,
                  validator: (v) => Validators.required(v, label: 'Last name'),
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Given names',
                  controller: _givenNamesController,
                  validator: (v) => Validators.required(v, label: 'Given names'),
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Middle name (optional)',
                  controller: _middleNameController,
                ),
                const SizedBox(height: 12),
                _sexDropdown(),
                const SizedBox(height: 12),
                _datePickerField(
                  label: 'Birthday',
                  value: _birthday,
                  onTap: _pickBirthday,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            _sectionCard(
              title: 'Contact & address',
              children: [
                AuthTextField(
                  label: 'Email',
                  controller: _emailController,
                  hint: 'you@example.com',
                  keyboardType: TextInputType.emailAddress,
                  validator: Validators.email,
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Contact number',
                  controller: _contactNoController,
                  hint: '09171234567',
                  keyboardType: TextInputType.phone,
                  validator: Validators.contactNo,
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Province',
                  controller: _provinceController,
                  validator: (v) => Validators.required(v, label: 'Province'),
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Municipality/City',
                  controller: _municipalityController,
                  validator: (v) => Validators.required(v, label: 'Municipality/City'),
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Barangay',
                  controller: _barangayController,
                  validator: (v) => Validators.required(v, label: 'Barangay'),
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'House no. (optional)',
                  controller: _houseNoController,
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Street (optional)',
                  controller: _streetController,
                ),
              ],
            ),
            if (_isRider) ...[
              const SizedBox(height: AppSpacing.lg),
              _sectionCard(
                title: 'Vehicle information',
                children: [
                  _vehicleTypeDropdown(),
                  const SizedBox(height: 12),
                  AuthTextField(
                    label: 'Vehicle brand',
                    controller: _vehicleBrandController,
                    validator: (v) => Validators.required(v, label: 'Vehicle brand'),
                  ),
                  const SizedBox(height: 12),
                  AuthTextField(
                    label: 'Vehicle model',
                    controller: _vehicleModelController,
                    validator: (v) => Validators.required(v, label: 'Vehicle model'),
                  ),
                  if (_vehicleNeedsDocuments) ...[
                    const SizedBox(height: 12),
                    AuthTextField(
                      label: 'Plate number',
                      controller: _plateNumberController,
                      validator: (v) => Validators.required(v, label: 'Plate number'),
                    ),
                    const SizedBox(height: 12),
                    AuthTextField(
                      label: "Driver's license number",
                      controller: _licenseNumberController,
                      validator: (v) => Validators.required(v, label: 'License number'),
                    ),
                    const SizedBox(height: 12),
                    _datePickerField(
                      label: 'License expiry',
                      value: _licenseExpiry,
                      onTap: _pickLicenseExpiry,
                    ),
                  ],
                ],
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
            _sectionCard(
              title: 'Account',
              children: [
                AuthTextField(
                  label: 'Username',
                  controller: _usernameController,
                  hint: 'At least 8 characters',
                  validator: Validators.username,
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Password',
                  controller: _passwordController,
                  obscureText: true,
                  validator: Validators.password,
                ),
                const SizedBox(height: 12),
                AuthTextField(
                  label: 'Confirm password',
                  controller: _confirmPasswordController,
                  obscureText: true,
                  validator: (v) =>
                      Validators.confirmPassword(v, _passwordController.text),
                ),
                const SizedBox(height: 12),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline, size: 14, color: AppColors.textMuted.withValues(alpha: 0.8)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'ID/selfie photo verification isn\'t available in the app '
                        'yet — your account will still be reviewed by an admin '
                        "before you can sign in.",
                        style: TextStyle(fontSize: 12, color: AppColors.textMuted.withValues(alpha: 0.9), height: 1.3),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.xl),
            ElevatedButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation(Colors.white),
                      ),
                    )
                  : const Text('Create account'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionCard({required String title, required List<Widget> children}) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
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
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: AppColors.pinkDark),
          ),
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    );
  }

  Widget _sexDropdown() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Sex', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: _sex,
          items: const [
            DropdownMenuItem(value: 'male', child: Text('Male')),
            DropdownMenuItem(value: 'female', child: Text('Female')),
          ],
          onChanged: (value) => setState(() => _sex = value),
          decoration: const InputDecoration(hintText: 'Select'),
        ),
      ],
    );
  }

  Widget _vehicleTypeDropdown() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Vehicle type', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: _vehicleType,
          items: const [
            DropdownMenuItem(value: 'motorcycle', child: Text('Motorcycle')),
            DropdownMenuItem(value: 'bicycle', child: Text('Bicycle')),
            DropdownMenuItem(value: 'car_van', child: Text('Car / Van')),
          ],
          onChanged: (value) => setState(() => _vehicleType = value!),
        ),
      ],
    );
  }

  Widget _datePickerField({
    required String label,
    required DateTime? value,
    required VoidCallback onTap,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        InkWell(
          onTap: onTap,
          child: InputDecorator(
            decoration: const InputDecoration(),
            child: Text(
              value == null ? 'Select a date' : _isoDate(value),
              style: TextStyle(
                color: value == null ? AppColors.textMuted : AppColors.textPrimary,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
