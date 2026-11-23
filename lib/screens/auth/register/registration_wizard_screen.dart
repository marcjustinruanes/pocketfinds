import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../services/backend_service.dart';
import '../../../models/logistics_center.dart';
import '../../../services/auth_service.dart';
import '../../../services/id_ocr_service.dart';
import '../../../services/logistics_center_service.dart';
import '../../../services/registration_documents_service.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_shadows.dart';
import '../../../theme/app_spacing.dart';
import '../../../widgets/primary_button.dart';
import '../../../widgets/step_progress_header.dart';
import 'application_submitted_screen.dart';
import 'steps/account_step.dart';
import 'steps/address_step.dart';
import 'steps/contact_step.dart';
import 'steps/id_selfie_step.dart';
import 'steps/license_step.dart';
import 'steps/logistics_center_step.dart';
import 'steps/personal_info_step.dart';
import 'steps/review_application_step.dart';
import 'steps/vehicle_step.dart';

/// Replaces the old single-page `RegisterScreen` with the multi-step
/// wizard from the reference design: ID & Selfie -> Personal -> Contact
/// (email OTP) -> Address -> [Vehicle -> License, rider only] -> Account.
class RegistrationWizardScreen extends StatefulWidget {
  final String accountType; // 'buyer' or 'rider'

  const RegistrationWizardScreen({super.key, required this.accountType});

  @override
  State<RegistrationWizardScreen> createState() =>
      _RegistrationWizardScreenState();
}

class _RegistrationWizardScreenState extends State<RegistrationWizardScreen> {
  final _authService = AuthService();
  final _ocrService = IdOcrService();
  final _documentsService = RegistrationDocumentsService();
  final _imagePicker = ImagePicker();

  bool get _isRider => widget.accountType == 'rider';

  late final List<String> _stepKeys = [
    'id_selfie',
    if (_isRider) 'logistics_center',
    'personal',
    'contact',
    'address',
    if (_isRider) 'vehicle',
    if (_isRider) 'license',
    if (_isRider) 'review',
    'account',
  ];
  late final List<String> _stepLabels = [
    'ID & Selfie',
    if (_isRider) 'Center',
    'Personal',
    'Contact',
    'Address',
    if (_isRider) 'Vehicle',
    if (_isRider) 'License',
    if (_isRider) 'Review',
    'Account',
  ];

  int _currentStep = 0;

  // Per-step form keys, one per entry in _stepKeys, in the same order.
  late final List<GlobalKey<FormState>> _formKeys = List.generate(
    _stepKeys.length,
    (_) => GlobalKey<FormState>(),
  );

  // --- ID & Selfie ---
  String? _idType;
  File? _idPhoto;
  File? _selfie;
  bool _ocrRunning = false;
  bool _ocrConfident = true; // true until a real OCR pass says otherwise

  // --- Personal ---
  final _lastNameController = TextEditingController();
  final _givenNamesController = TextEditingController();
  final _middleNameController = TextEditingController();
  String? _sex;
  DateTime? _birthday;

  // --- Contact ---
  final _emailController = TextEditingController();
  final _otpController = TextEditingController();
  final _contactNoController = TextEditingController();
  bool _otpSent = false;
  bool _otpVerified = false;
  bool _sendingCode = false;
  bool _verifyingCode = false;
  int _resendSecondsLeft = 0;
  Timer? _resendTimer;

  // --- Address ---
  final _provinceController = TextEditingController();
  final _municipalityController = TextEditingController();
  final _barangayController = TextEditingController();
  final _houseNoController = TextEditingController();
  final _streetController = TextEditingController();

  // --- Logistics center (rider only, real — public.logistics_centers) ---
  final _centerService = LogisticsCenterService();
  List<LogisticsCenter> _centers = [];
  bool _centersLoading = false;
  String? _centersError;
  int? _selectedCenterIndex;

  // --- Vehicle / License (rider only) ---
  String _vehicleType = 'motorcycle';
  final _vehicleBrandController = TextEditingController();
  final _vehicleModelController = TextEditingController();
  final _plateNumberController = TextEditingController();
  final _licenseNumberController = TextEditingController();
  DateTime? _licenseExpiry;
  File? _orCrPhoto;
  File? _licensePhoto;
  bool get _vehicleNeedsDocuments => _vehicleType != 'bicycle';

  // --- Account ---
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _submitting = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    // This whole wizard is its own orchestrated multi-step auth flow —
    // suppress the app-wide auth listener for its entire lifetime, not just
    // individual calls. verifyEmailOtp() alone establishes a live session
    // partway through (steps 4-7 still unfilled), and without this the
    // global listener in main.dart would race in with a premature
    // resolveSession()/navigation the moment that happens.
    AuthService.suppressGlobalNavigation = true;
    if (_isRider) _loadCenters();
  }

  Future<void> _loadCenters() async {
    setState(() {
      _centersLoading = true;
      _centersError = null;
    });
    try {
      final centers = await _centerService.fetchCenters();
      if (!mounted) return;
      setState(() => _centers = centers);
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _centersError =
            'Could not load logistics centers. Please try again.',
      );
    } finally {
      if (mounted) setState(() => _centersLoading = false);
    }
  }

  @override
  void dispose() {
    AuthService.suppressGlobalNavigation = false;
    _resendTimer?.cancel();
    _ocrService.dispose();
    _lastNameController.dispose();
    _givenNamesController.dispose();
    _middleNameController.dispose();
    _emailController.dispose();
    _otpController.dispose();
    _contactNoController.dispose();
    _provinceController.dispose();
    _municipalityController.dispose();
    _barangayController.dispose();
    _houseNoController.dispose();
    _streetController.dispose();
    _vehicleBrandController.dispose();
    _vehicleModelController.dispose();
    _plateNumberController.dispose();
    _licenseNumberController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  String _isoDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  // ---------------------------------------------------------------------
  // ID & Selfie
  // ---------------------------------------------------------------------

  Future<void> _pickIdPhoto() async {
    final source = await _chooseImageSource();
    if (source == null) return;
    final picked = await _imagePicker.pickImage(
      source: source,
      imageQuality: 85,
    );
    if (picked == null) return;

    final file = File(picked.path);
    setState(() {
      _idPhoto = file;
      _ocrRunning = true;
      _errorMessage = null;
    });

    final result = await _ocrService.recognize(file);
    if (!mounted) return;
    setState(() {
      _ocrRunning = false;
      _ocrConfident = result.isConfident;
      if (result.lastName != null) _lastNameController.text = result.lastName!;
      if (result.givenNames != null) {
        _givenNamesController.text = result.givenNames!;
      }
      if (result.middleName != null) {
        _middleNameController.text = result.middleName!;
      }
      if (result.sex != null) _sex = result.sex;
      if (result.birthday != null) _birthday = result.birthday;
    });
  }

  Future<void> _pickSelfie() async {
    final picked = await _imagePicker.pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      imageQuality: 85,
    );
    if (picked == null) return;
    setState(() => _selfie = File(picked.path));
  }

  Future<void> _pickOrCrPhoto() async {
    final source = await _chooseImageSource();
    if (source == null) return;
    final picked = await _imagePicker.pickImage(
      source: source,
      imageQuality: 85,
    );
    if (picked == null) return;
    setState(() => _orCrPhoto = File(picked.path));
  }

  Future<void> _pickLicensePhoto() async {
    final source = await _chooseImageSource();
    if (source == null) return;
    final picked = await _imagePicker.pickImage(
      source: source,
      imageQuality: 85,
    );
    if (picked == null) return;
    setState(() => _licensePhoto = File(picked.path));
  }

  Future<ImageSource?> _chooseImageSource() {
    return showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------
  // Contact / OTP
  // ---------------------------------------------------------------------

  Future<void> _sendCode() async {
    final email = _emailController.text.trim();
    if (email.isEmpty ||
        !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      setState(() => _errorMessage = 'Enter a valid email address first.');
      return;
    }

    setState(() {
      _sendingCode = true;
      _errorMessage = null;
    });
    try {
      await _authService.sendEmailOtp(email);
      if (!mounted) return;
      setState(() {
        _otpSent = true;
        _resendSecondsLeft = 60;
      });
      _resendTimer?.cancel();
      _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) return;
        setState(() {
          if (_resendSecondsLeft <= 1) {
            timer.cancel();
            _resendSecondsLeft = 0;
          } else {
            _resendSecondsLeft--;
          }
        });
      });
    } on AuthException catch (e) {
      setState(() => _errorMessage = e.message);
    } catch (_) {
      setState(
        () => _errorMessage = 'Could not send the code. Please try again.',
      );
    } finally {
      if (mounted) setState(() => _sendingCode = false);
    }
  }

  Future<void> _verifyCode() async {
    final code = _otpController.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() => _errorMessage = 'Enter the 6-digit code from your email.');
      return;
    }

    setState(() {
      _verifyingCode = true;
      _errorMessage = null;
    });
    try {
      await _authService.verifyEmailOtp(
        email: _emailController.text,
        code: code,
      );
      if (!mounted) return;
      setState(() => _otpVerified = true);
      _resendTimer?.cancel();
    } on AuthException catch (e) {
      setState(() => _errorMessage = e.message);
    } catch (_) {
      setState(
        () => _errorMessage = 'Could not verify that code. Please try again.',
      );
    } finally {
      if (mounted) setState(() => _verifyingCode = false);
    }
  }

  // ---------------------------------------------------------------------
  // Navigation between steps
  // ---------------------------------------------------------------------

  Future<void> _goNext() async {
    setState(() => _errorMessage = null);
    final stepKey = _stepKeys[_currentStep];

    switch (stepKey) {
      case 'id_selfie':
        if (_idType == null) {
          setState(() => _errorMessage = 'Please select an ID type.');
          return;
        }
        if (_idPhoto == null) {
          setState(() => _errorMessage = 'Please upload your ID photo.');
          return;
        }
        if (_selfie == null) {
          setState(() => _errorMessage = 'Please take a selfie.');
          return;
        }
      case 'logistics_center':
        if (_selectedCenterIndex == null) {
          setState(
            () =>
                _errorMessage = 'Please choose a logistics center to apply to.',
          );
          return;
        }
      case 'review':
        break; // read-only summary — nothing to validate, just advance
      case 'personal':
        if (!_formKeys[_currentStep].currentState!.validate()) return;
        if (_sex == null) {
          setState(() => _errorMessage = 'Please select your sex.');
          return;
        }
        if (_birthday == null) {
          setState(() => _errorMessage = 'Please select your birthday.');
          return;
        }
      case 'contact':
        if (!_otpVerified) {
          setState(() => _errorMessage = 'Please verify your email first.');
          return;
        }
        if (!_formKeys[_currentStep].currentState!.validate()) return;
      case 'address':
      case 'vehicle':
        if (!_formKeys[_currentStep].currentState!.validate()) return;
      case 'license':
        if (!_formKeys[_currentStep].currentState!.validate()) return;
        if (_vehicleNeedsDocuments && _orCrPhoto == null) {
          setState(
            () => _errorMessage =
                "Please upload a photo of your vehicle's OR/CR.",
          );
          return;
        }
        if (_vehicleNeedsDocuments && _licensePhoto == null) {
          setState(
            () => _errorMessage =
                "Please upload a photo of your driver's license.",
          );
          return;
        }
      case 'account':
        if (!_formKeys[_currentStep].currentState!.validate()) return;
        await _submit();
        return;
    }

    setState(() => _currentStep++);
  }

  void _goBack() {
    setState(() {
      _errorMessage = null;
      _currentStep--;
    });
  }

  // ---------------------------------------------------------------------
  // Final submit
  // ---------------------------------------------------------------------

  Future<void> _submit() async {
    if (_passwordController.text != _confirmPasswordController.text) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      final authUid = BackendService.client.auth.currentUser?.id;
      if (authUid == null) {
        throw StateError('No active session — please verify your email again.');
      }

      final idPhotoPath = await _documentsService.uploadIdPhoto(
        authUid,
        _idPhoto!,
      );
      final selfiePath = await _documentsService.uploadSelfie(
        authUid,
        _selfie!,
      );
      String? orCrPath;
      String? licensePhotoPath;
      if (_isRider && _vehicleNeedsDocuments) {
        orCrPath = await _documentsService.uploadVehicleOrCr(
          authUid,
          _orCrPhoto!,
        );
        licensePhotoPath = await _documentsService.uploadLicensePhoto(
          authUid,
          _licensePhoto!,
        );
      }

      final metadata = <String, dynamic>{
        'account_type': widget.accountType,
        'last_name': _lastNameController.text.trim(),
        'given_names': _givenNamesController.text.trim(),
        'middle_name': _middleNameController.text.trim(),
        'sex': _sex,
        'birthday': _isoDate(_birthday!),
        'contact_no': _contactNoController.text.trim(),
        'province': _provinceController.text.trim(),
        'municipality': _municipalityController.text.trim(),
        'barangay': _barangayController.text.trim(),
        'house_no': _houseNoController.text.trim(),
        'street': _streetController.text.trim(),
        'username': _usernameController.text.trim(),
        'id_type': _idType,
        'id_photo_path': idPhotoPath,
        'selfie_photo_path': selfiePath,
        if (_isRider) ...{
          'logistics_center_id': _selectedCenterIndex == null
              ? null
              : _centers[_selectedCenterIndex!].id.toString(),
          'vehicle_type': _vehicleType,
          'vehicle_brand': _vehicleBrandController.text.trim(),
          'vehicle_model': _vehicleModelController.text.trim(),
          if (_vehicleNeedsDocuments) ...{
            'plate_number': _plateNumberController.text.trim(),
            'license_number': _licenseNumberController.text.trim(),
            'license_expiry': _isoDate(_licenseExpiry!),
            'or_cr_path': orCrPath,
            'license_photo_path': licensePhotoPath,
          },
        },
      };

      await _authService.finalizeRegistration(
        password: _passwordController.text,
        metadata: metadata,
      );
      await _authService.signOut(); // status is 'pending' either way

      if (!mounted) return;
      if (_isRider) {
        // Riders go through the Logistics Center application flow — a
        // dedicated "Under Review" screen instead of a plain dialog,
        // matching the redesign brief's application flow.
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ApplicationSubmittedScreen(
              centerName: _selectedCenterIndex == null
                  ? 'the logistics center'
                  : _centers[_selectedCenterIndex!].name,
            ),
          ),
        );
      } else {
        await _showSuccessDialog(
          'Account created! Your account is now pending admin approval. '
          "You'll be able to sign in once it's approved.",
        );
      }
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
    } on AuthException catch (e) {
      setState(() => _errorMessage = e.message);
    } on DatabaseException catch (e) {
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

  // ---------------------------------------------------------------------
  // Build
  // ---------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final stepKey = _stepKeys[_currentStep];
    final isLastStep = _currentStep == _stepKeys.length - 1;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Container(
                padding: const EdgeInsets.all(AppSpacing.xl),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: AppShadows.md,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    StepProgressHeader(
                      stepLabels: _stepLabels,
                      currentStep: _currentStep,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _stepLabels[_currentStep],
                                style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _stepSubtitle(stepKey),
                                style: TextStyle(
                                  fontSize: 12.5,
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ],
                          ),
                        ),
                        _RoleBadge(isRider: _isRider),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    if (_errorMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.dangerSoft,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: AppColors.danger.withValues(alpha: 0.3),
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.error_outline,
                              color: AppColors.danger,
                              size: 18,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: TextStyle(
                                  color: AppColors.danger,
                                  fontSize: 13,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 200),
                      child: KeyedSubtree(
                        key: ValueKey(stepKey),
                        child: _buildStep(stepKey),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    Row(
                      children: [
                        if (_currentStep > 0) ...[
                          Expanded(
                            child: SecondaryButton(
                              label: '← Back',
                              onPressed: _submitting ? null : _goBack,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.md),
                        ],
                        Expanded(
                          flex: _currentStep > 0 ? 1 : 2,
                          child: PrimaryButton(
                            label: isLastStep ? 'Create account' : 'Continue →',
                            loading: _submitting,
                            onPressed: _goNext,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          "Already have an account? ",
                          style: TextStyle(
                            color: AppColors.textMuted,
                            fontSize: 13,
                          ),
                        ),
                        GestureDetector(
                          onTap: _submitting
                              ? null
                              : () =>
                                    Navigator.of(context)
                                        .popUntil((r) => r.isFirst),
                          child: Text(
                            'Sign in',
                            style: TextStyle(
                              color: AppColors.pink,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _stepSubtitle(String stepKey) {
    switch (stepKey) {
      case 'id_selfie':
        return "Upload your ID — we'll pre-fill your details automatically.";
      case 'logistics_center':
        return 'Choose the logistics center you want to apply to.';
      case 'review':
        return "Check everything before you submit — you'll wait for the center's approval next.";
      case 'personal':
        return 'Review and correct your details if needed.';
      case 'contact':
        return 'How can we reach you?';
      case 'address':
        return 'Where should we deliver to / dispatch you from?';
      case 'vehicle':
        return 'Tell us what you ride.';
      case 'license':
        return "We'll need this to verify you can legally drive.";
      case 'account':
        return 'Choose a username and password.';
      default:
        return '';
    }
  }

  Widget _buildStep(String stepKey) {
    switch (stepKey) {
      case 'id_selfie':
        return IdSelfieStep(
          idType: _idType,
          onIdTypeChanged: (v) => setState(() => _idType = v),
          idPhoto: _idPhoto,
          selfie: _selfie,
          ocrRunning: _ocrRunning,
          onPickIdPhoto: _pickIdPhoto,
          onPickSelfie: _pickSelfie,
        );
      case 'logistics_center':
        if (_centersLoading) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 40),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (_centersError != null) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Text(
                  _centersError!,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.danger),
                ),
              ),
              SecondaryButton(label: 'Retry', onPressed: _loadCenters),
            ],
          );
        }
        return LogisticsCenterStep(
          centers: _centers,
          selectedIndex: _selectedCenterIndex,
          onSelected: (i) => setState(() => _selectedCenterIndex = i),
        );
      case 'review':
        return ReviewApplicationStep(
          centerName: _selectedCenterIndex == null
              ? '—'
              : _centers[_selectedCenterIndex!].name,
          fullName:
              '${_givenNamesController.text.trim()} ${_lastNameController.text.trim()}'
                  .trim(),
          contactNo: _contactNoController.text.trim().isEmpty
              ? 'Not provided'
              : _contactNoController.text.trim(),
          address: [
            _houseNoController.text.trim(),
            _streetController.text.trim(),
            _barangayController.text.trim(),
            _municipalityController.text.trim(),
            _provinceController.text.trim(),
          ].where((v) => v.isNotEmpty).join(', '),
          vehicleSummary: [
            _vehicleType,
            _vehicleBrandController.text.trim(),
            _vehicleModelController.text.trim(),
          ].where((v) => v.isNotEmpty).join(' · '),
        );
      case 'personal':
        return PersonalInfoStep(
          formKey: _formKeys[_currentStep],
          showOcrFailedBanner: !_ocrConfident,
          lastNameController: _lastNameController,
          givenNamesController: _givenNamesController,
          middleNameController: _middleNameController,
          sex: _sex,
          onSexChanged: (v) => setState(() => _sex = v),
          birthday: _birthday,
          onPickBirthday: () => _pickDate(
            initial: _birthday,
            onPicked: (d) => setState(() => _birthday = d),
          ),
        );
      case 'contact':
        return ContactStep(
          formKey: _formKeys[_currentStep],
          emailController: _emailController,
          otpController: _otpController,
          contactNoController: _contactNoController,
          otpSent: _otpSent,
          otpVerified: _otpVerified,
          sendingCode: _sendingCode,
          verifyingCode: _verifyingCode,
          resendSecondsLeft: _resendSecondsLeft,
          onSendCode: (_otpSent && _resendSecondsLeft > 0) ? null : _sendCode,
          onVerifyCode: _verifyCode,
        );
      case 'address':
        return AddressStep(
          formKey: _formKeys[_currentStep],
          provinceController: _provinceController,
          municipalityController: _municipalityController,
          barangayController: _barangayController,
          houseNoController: _houseNoController,
          streetController: _streetController,
        );
      case 'vehicle':
        return VehicleStep(
          formKey: _formKeys[_currentStep],
          vehicleType: _vehicleType,
          onVehicleTypeChanged: (v) => setState(() => _vehicleType = v!),
          brandController: _vehicleBrandController,
          modelController: _vehicleModelController,
        );
      case 'license':
        return LicenseStep(
          formKey: _formKeys[_currentStep],
          required: _vehicleNeedsDocuments,
          plateNumberController: _plateNumberController,
          licenseNumberController: _licenseNumberController,
          licenseExpiry: _licenseExpiry,
          onPickLicenseExpiry: () => _pickDate(
            initial: _licenseExpiry ?? DateTime.now(),
            onPicked: (d) => setState(() => _licenseExpiry = d),
            firstDate: DateTime.now(),
            lastDate: DateTime(DateTime.now().year + 20),
          ),
          orCrPhoto: _orCrPhoto,
          licensePhoto: _licensePhoto,
          onPickOrCrPhoto: _pickOrCrPhoto,
          onPickLicensePhoto: _pickLicensePhoto,
        );
      case 'account':
        return AccountStep(
          formKey: _formKeys[_currentStep],
          usernameController: _usernameController,
          passwordController: _passwordController,
          confirmPasswordController: _confirmPasswordController,
        );
      default:
        return const SizedBox.shrink();
    }
  }

  Future<void> _pickDate({
    required DateTime? initial,
    required ValueChanged<DateTime> onPicked,
    DateTime? firstDate,
    DateTime? lastDate,
  }) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial ?? DateTime(now.year - 18, now.month, now.day),
      firstDate: firstDate ?? DateTime(now.year - 100),
      lastDate: lastDate ?? now,
    );
    if (picked != null) onPicked(picked);
  }
}

class _RoleBadge extends StatelessWidget {
  final bool isRider;

  const _RoleBadge({required this.isRider});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.pinkSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.pinkBorder),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isRider ? Icons.two_wheeler_outlined : Icons.shopping_bag_outlined,
            size: 13,
            color: AppColors.pinkDark,
          ),
          const SizedBox(width: 4),
          Text(
            isRider ? 'RIDER' : 'BUYER',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: AppColors.pinkDark,
              letterSpacing: 0.5,
            ),
          ),
        ],
      ),
    );
  }
}
