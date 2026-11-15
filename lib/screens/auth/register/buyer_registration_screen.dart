import '../../../services/delivery_service.dart';
import '../../guest/guest_home_screen.dart';

import 'dart:convert';
import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';

import 'rider_activation_screen.dart';

import '../../../services/psgc_service.dart';
import '../../../services/id_ocr_service.dart';
import '../login_screen.dart';
import '../../../widgets/registration_address_fields.dart';

import 'registration_terms_screen.dart';

import '../../../services/auth_service.dart';
import '../../../services/backend_service.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_typography.dart';
import '../../../utils/friendly_error.dart';
import '../../../utils/validators.dart';
import '../../../utils/license_number_formatter.dart';
import '../../../widgets/auth_text_field.dart';
import '../../../widgets/google_sign_in_button.dart';
import '../../../widgets/hero_auth_scaffold.dart';
import '../../../widgets/adaptive_card_grid.dart';
import '../../../widgets/primary_button.dart';
import '../../../widgets/step_progress_header.dart';
import 'verify_registration_screen.dart';

/// Buyer and rider applications, using the website's fields and review process.
class BuyerRegistrationScreen extends StatefulWidget {
  final String accountType;
  final PsgcService? addressService;
  final BackendClient? client;
  final Future<XFile?> Function(String kind)? documentPicker;
  const BuyerRegistrationScreen({
    super.key,
    this.accountType = 'buyer',
    this.addressService,
    this.client,
    this.documentPicker,
  });
  @override
  State<BuyerRegistrationScreen> createState() =>
      _BuyerRegistrationScreenState();
}

class _BuyerRegistrationScreenState extends State<BuyerRegistrationScreen> {
  final _form = GlobalKey<FormState>();
  final _scroll = ScrollController();
  final _auth = AuthService();
  late final _client = widget.client ?? BackendService.client;
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  final _username = TextEditingController();
  Timer? _usernameTimer;
  int _usernameGeneration = 0;
  String? _usernameStatus;
  bool? _usernameAvailable;

  void _usernameChanged(String value) {
    _usernameTimer?.cancel();
    final generation = ++_usernameGeneration;
    final name = value.trim();
    setState(() {
      _usernameAvailable = null;
      _usernameStatus = null;
    });
    if (!RegExp(r'^[A-Za-z0-9_-]{8,30}$').hasMatch(name)) return;
    setState(() => _usernameStatus = 'Checking username?');
    _usernameTimer = Timer(const Duration(milliseconds: 500), () async {
      try {
        final result = await _client.request('auth/registration-username', {
          'username': name,
        });
        if (!mounted || generation != _usernameGeneration) return;
        setState(() {
          _usernameAvailable = result['available'] == true;
          _usernameStatus = _usernameAvailable!
              ? 'Username is available.'
              : 'This username is already taken.';
        });
      } catch (_) {
        if (!mounted || generation != _usernameGeneration) return;
        setState(
          () => _usernameStatus =
              'Could not check availability. It will be checked on submission.',
        );
      }
    });
  }

  final _otp = TextEditingController();
  String? _emailToken, _suffix;
  bool _codeBusy = false;
  int _resendSeconds = 0;
  DateTime? _resendAt, _codeExpires;
  final _requestId = DeliveryService.requestId();
  Timer? _codeTimer;
  final _middle = TextEditingController();
  final _plate = TextEditingController();
  final _license = TextEditingController();
  final _licenseExpiry = TextEditingController();
  final _brand = TextEditingController();
  final _model = TextEditingController();
  int? _idType;
  late final Future<List<Map<String, dynamic>>> _idTypes = _client
      .from('id_types')
      .select('id,name')
      .order('name');
  String? _sex, _vehicle;
  int? _hub;
  String? _company;
  String _ownership = 'own';
  bool _noMiddleName = false, _companyTerms = false, _companyTermsRead = false;
  late Future<Map<String, dynamic>> _options = _loadRegistrationOptions();

  Future<Map<String, dynamic>> _loadRegistrationOptions() async {
    for (var attempt = 0; ; attempt++) {
      try {
        final value = await _client.request('auth/registration-options');
        return Map<String, dynamic>.from(value as Map);
      } on TimeoutException {
        if (attempt >= 1) rethrow;
      } on SocketException {
        if (attempt >= 1) rethrow;
      } on DatabaseException catch (error) {
        if (attempt >= 1 || (error.statusCode ?? 0) < 500) rethrow;
      } on AuthException catch (error) {
        if (attempt >= 1 || (error.statusCode ?? 0) < 500) rethrow;
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
  }

  Map<String, String> _address = {};
  final _documents = <String, ({String name, String content})>{};
  bool _pickingDocument = false;
  bool get _isRider => widget.accountType == 'rider';
  int get _minimumAge => _isRider ? 18 : 16;
  DateTime? _birthday;
  String? _googleToken;
  String? _error;
  bool _terms = false;
  bool _termsRead = false;
  bool _saving = false;
  bool _googleBusy = false;
  int _step = 0;
  List<String> get _steps => _isRider
      ? [
          'Address',
          'Company',
          'License',
          'Vehicle',
          'Personal',
          'Contact',
          'Account',
        ]
      : ['ID & Selfie', 'Personal', 'Contact', 'Address', 'Account'];
  List<String> get _pages => _isRider
      ? [
          'address',
          'company',
          'license',
          'vehicle',
          'personal',
          'contact',
          'account',
        ]
      : ['documents', 'personal', 'contact', 'address', 'account'];
  String get _page => _pages[_step];
  bool get _lastStep => _step == _steps.length - 1;

  Future<void> _pickDocument(String kind) async {
    setState(() {
      _pickingDocument = true;
      _error = null;
    });
    try {
      final file = widget.documentPicker != null
          ? await widget.documentPicker!(kind)
          : kind == 'selfie'
          ? await ImagePicker().pickImage(
              source: ImageSource.camera,
              preferredCameraDevice: CameraDevice.front,
              imageQuality: 85,
            )
          : await _pickFile(kind);
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (bytes.length > 5 * 1024 * 1024) {
        throw const AuthException('Choose an image smaller than 5 MB.');
      }
      if (mounted) {
        setState(
          () => _documents[kind] = (
            name: file.name,
            content: base64Encode(bytes),
          ),
        );
      }
      if (kind == 'id' &&
          !kIsWeb &&
          widget.documentPicker == null &&
          file.path.isNotEmpty &&
          !file.name.toLowerCase().endsWith('.pdf')) {
        final ocr = IdOcrService();
        final result = await ocr.recognize(File(file.path));
        ocr.dispose();
        if (mounted) {
          setState(() {
            if (result.givenNames != null) _first.text = result.givenNames!;
            if (result.lastName != null) _last.text = result.lastName!;
            if (result.middleName != null) _middle.text = result.middleName!;
            _sex = result.sex ?? _sex;
            _birthday = result.birthday ?? _birthday;
          });
        }
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _pickingDocument = false);
    }
  }

  Future<XFile?> _pickFile(String kind) async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: kind == 'resume'
          ? ['pdf']
          : ['jpg', 'jpeg', 'png', 'pdf'],
    );
    return file?.xFile;
  }

  void _previous() {
    if (_saving || _googleBusy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _step--;
      _error = null;
    });
    _scrollToTop();
  }

  void _scrollToTop() => WidgetsBinding.instance.addPostFrameCallback((_) {
    if (mounted && _scroll.hasClients) _scroll.jumpTo(0);
  });

  Future<void> _emailCode(bool verify) async {
    if (_codeBusy) return;
    final email = _email.text.trim();
    if (Validators.email(email) != null) {
      setState(() => _error = 'Enter a valid email address.');
      return;
    }
    setState(() => _codeBusy = true);
    try {
      final result = await _client.request(
        verify
            ? 'auth/registration-verify-code'
            : 'auth/registration-send-code',
        {'email': email, if (verify) 'code': _otp.text.trim()},
      );
      if (!mounted || _email.text.trim() != email) return;
      if (verify) {
        setState(() {
          _emailToken = '${result['email_token']}';
          _error = null;
        });
      } else {
        setState(() {
          _resendSeconds = 60;
          _resendAt = DateTime.now().add(const Duration(seconds: 60));
          _codeExpires =
              DateTime.tryParse('${result['expires_at']}') ??
              DateTime.now().add(const Duration(minutes: 5));
          _error = null;
        });
        _codeTimer?.cancel();
        _codeTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
          if (!mounted) {
            timer.cancel();
            return;
          }
          setState(
            () => _resendSeconds =
                (_resendAt!.difference(DateTime.now()).inSeconds).clamp(0, 60),
          );
          if (_codeExpires != null && DateTime.now().isAfter(_codeExpires!)) {
            timer.cancel();
          }
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _codeBusy = false);
    }
  }

  Widget _birthdayFields() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const Text(
        'Birthday',
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
      ),
      const SizedBox(height: 6),
      OutlinedButton.icon(
        style: OutlinedButton.styleFrom(
          alignment: Alignment.centerLeft,
          minimumSize: const Size.fromHeight(52),
          backgroundColor: AppColors.white,
        ),
        onPressed: _saving ? null : _pickBirthday,
        icon: const Icon(Icons.cake_outlined),
        label: Text(
          _birthday == null
              ? 'Date of birth ($_minimumAge or older)'
              : '${_birthday!.month}/${_birthday!.day}/${_birthday!.year}',
        ),
      ),
      if (_birthday != null)
        Text(
          'Age: ${DateTime.now().year - _birthday!.year - ((DateTime.now().month < _birthday!.month || (DateTime.now().month == _birthday!.month && DateTime.now().day < _birthday!.day)) ? 1 : 0)} (automatically calculated)',
        ),
    ],
  );

  void _next() {
    if (_page == 'company' && _hub == null) {
      setState(
        () => _error = 'Choose a company and a hub accepting riders. Retry loading if needed.',
      );
      return;
    }
    if (!_form.currentState!.validate()) return;
    if (_page == 'personal' &&
        (_birthday == null ||
            _birthday!.isAfter(
              DateTime(
                DateTime.now().year - _minimumAge,
                DateTime.now().month,
                DateTime.now().day,
              ),
            ))) {
      setState(
        () => _error =
            'Select your date of birth. You must be at least $_minimumAge.',
      );
      return;
    }
    if (_page == 'contact' && _emailToken == null && _googleToken == null) {
      setState(() => _error = 'Verify your email before continuing.');
      return;
    }
    final requiredDocuments = switch (_page) {
      'documents' => ['id', 'selfie'],
      'license' => ['license', 'selfie', 'nbi'],
      'vehicle' when _ownership == 'own' => ['or', 'cr'],
      _ => <String>[],
    };
    if (requiredDocuments.any((kind) => !_documents.containsKey(kind))) {
      setState(
        () => _error = 'Upload all required documents before continuing.',
      );
      return;
    }
    if (_page == 'license' && _licenseExpiry.text.isEmpty) {
      setState(() => _error = 'Select your license expiry date.');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _step++;
      _error = null;
    });
    _scrollToTop();
  }

  @override
  void dispose() {
    _usernameTimer?.cancel();
    _usernameGeneration++;
    _scroll.dispose();
    _codeTimer?.cancel();
    for (final controller in [
      _first,
      _last,
      _email,
      _phone,
      _password,
      _confirmation,
      _username,
      _otp,
      _middle,
      _plate,
      _license,
      _licenseExpiry,
      _brand,
      _model,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _google() async {
    setState(() {
      _googleBusy = true;
      _error = null;
    });
    try {
      final result = await _auth.signInWithGoogle(register: true);
      if (result == null || !mounted) return;
      setState(() {
        _googleToken = result.googleRegistrationToken;
        _email.text = result.email ?? '';
        _first.text = result.givenNames ?? '';
        _last.text = result.lastName ?? '';
      });
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _googleBusy = false);
    }
  }

  Future<void> _pickBirthday() async {
    final now = DateTime.now();
    final adult = DateTime(now.year - _minimumAge, now.month, now.day);
    final date = await showDatePicker(
      context: context,
      initialDate: _birthday != null && !_birthday!.isAfter(adult)
          ? _birthday
          : adult,
      firstDate: DateTime(now.year - 120),
      lastDate: adult,
    );
    if (date != null && mounted) {
      setState(() {
        _birthday = date;
        _error = null;
      });
    }
  }

  String? _name(String? value) {
    if (value == null || value.trim().length < 2) {
      return 'Enter at least 2 characters.';
    }
    if (value.trim().length > 50) return 'Use no more than 50 characters.';
    return null; // The website enforces its Unicode name rules on the server.
  }

  String? _passwordRule(String? value) {
    final required = Validators.password(value);
    if (required != null) return required;
    if (!RegExp(r'[A-Z]').hasMatch(value!) ||
        !RegExp(r'[a-z]').hasMatch(value) ||
        !RegExp(r'[0-9]').hasMatch(value) ||
        !RegExp(r'[^a-zA-Z0-9\s]').hasMatch(value)) {
      return 'Include uppercase, lowercase, a number and a symbol.';
    }
    return null;
  }

  Future<void> _submit() async {
    if (_saving) return;
    if (!_form.currentState!.validate()) return;
    if (_birthday == null || !_terms || (_isRider && !_companyTerms)) {
      setState(
        () => _error = _birthday == null
            ? 'Select your date of birth. You must be at least $_minimumAge.'
            : 'Accept the terms before continuing.',
      );
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final date = _birthday!;
      final result = await _client.auth.registerBuyer({
        'request_id': _requestId,
        'account_type': widget.accountType,
        ...{
          'flow': 'website',
          'username': _username.text.trim(),
          'suffix': _suffix,
          'id_type_id': _idType,
          'email_token': _emailToken,
        },
        'middle_name': _middle.text.trim(),
        'no_middle_name': _noMiddleName,
        'sex': _sex,
        ..._address,
        'documents': {
          for (final entry in _documents.entries)
            entry.key: entry.value.content,
        },
        if (_isRider) ...{
          'logistics_hub_id': _hub,
          'vehicle_ownership': _ownership,
          'company_terms': _companyTerms,
          'id_type_id': _idType,
          'vehicle_brand': _brand.text.trim(),
          'vehicle_model': _model.text.trim(),
          'vehicle_type': _vehicle,
          'plate_number': _plate.text.trim().toUpperCase(),
          'license_number': _license.text.trim().toUpperCase(),
          'license_expiry': _licenseExpiry.text.trim(),
        },
        'given_names': _first.text.trim(),
        'last_name': _last.text.trim(),
        'email': _email.text.trim(),
        'contact_no': _phone.text.trim(),
        'birthday':
            '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
        'password': _password.text,
        'password_confirmation': _confirmation.text,
        'terms': true,
        'google_registration_token': ?_googleToken,
      });
      if (!mounted) return;
      if (result.applicationSubmitted) {
        setState(() => _saving = false);
        await showDialog<void>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Registration submitted'),
            content: const Text(
              'Your application is awaiting review. Follow the approval or activation instructions sent to your email before signing in.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Continue to sign in'),
              ),
            ],
          ),
        );
        if (mounted) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (_) => const LoginScreen()),
          );
        }
        return;
      }
      if (result.registrationToken == null) {
        throw const AuthException(
          'Registration could not be completed. Please try again.',
        );
      }
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => VerifyRegistrationScreen(
            token: result.registrationToken!,
            email: result.email ?? _email.text.trim(),
          ),
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _termsPage({bool company = false}) async {
    try {
      final terms = await _client.request('auth/registration-terms', {
        'account_type': widget.accountType,
        if (company) 'company': _company,
      });
      if (!mounted) return;
      final read = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => RegistrationTermsScreen(
            title: '${terms['title']}',
            content: '${terms['content']}',
          ),
        ),
      );
      if (read == true && mounted) {
        setState(() {
          if (company) {
            _companyTermsRead = true;
          } else {
            _termsRead = true;
          }
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  bool get _dirty =>
      _documents.isNotEmpty ||
      _first.text.isNotEmpty ||
      _email.text.isNotEmpty ||
      _address.isNotEmpty ||
      _hub != null;
  Future<void> _handleBack() async {
    if (_saving || _googleBusy) return;
    if (_step > 0) {
      _previous();
      return;
    }
    if (_dirty) {
      final leave = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Discard registration?'),
          content: const Text('Your entered details will be lost.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Keep editing'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Discard'),
            ),
          ],
        ),
      );
      if (leave != true || !mounted) return;
    }
    if (!mounted) return;
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    } else {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const GuestHomeScreen()),
      );
    }
  }

  Widget _uploadCard(String kind, String title, String hint) {
    final document = _documents[kind];
    return Material(
      color: document == null
          ? const Color(0xFFFAF8FA)
          : const Color(0xFFF0FAF4),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: document == null ? AppColors.border : const Color(0xFFB7DEC5),
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: _pickingDocument ? null : () => _pickDocument(kind),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                document == null
                    ? (kind == 'selfie'
                          ? Icons.add_a_photo_outlined
                          : Icons.upload_file_outlined)
                    : Icons.check_circle_outline,
                color: document == null ? AppColors.pink : Colors.green,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text(document?.name ?? hint, style: AppTypography.caption),
                    if (document != null &&
                        !document.name.toLowerCase().endsWith('.pdf')) ...[
                      const SizedBox(height: 10),
                      GestureDetector(
                        onTap: () => showDialog<void>(
                          context: context,
                          builder: (context) => Dialog(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Flexible(
                                  child: InteractiveViewer(
                                    child: Image.memory(
                                      base64Decode(document.content),
                                      errorBuilder: (_, _, _) =>
                                          const Text('Preview unavailable.'),
                                    ),
                                  ),
                                ),
                                TextButton(
                                  onPressed: () => Navigator.pop(context),
                                  child: const Text('Close'),
                                ),
                              ],
                            ),
                          ),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.memory(
                            base64Decode(document.content),
                            height: 100,
                            width: double.infinity,
                            fit: BoxFit.contain,
                            errorBuilder: (_, _, _) => const SizedBox.shrink(),
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Text(
                      document == null ? 'Tap to upload' : 'Tap to replace',
                      style: TextStyle(
                        color: AppColors.pink,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickLicenseExpiry() async {
    final now = DateTime.now();
    final lastDay = DateTime(now.year, now.month + 2, 0).day;
    final minimum = DateTime(
      now.year,
      now.month + 1,
      now.day.clamp(1, lastDay),
    );
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(_licenseExpiry.text) ?? minimum,
      firstDate: minimum,
      lastDate: DateTime(now.year + 20),
    );
    if (date != null && mounted) {
      setState(
        () => _licenseExpiry.text = date.toIso8601String().split('T').first,
      );
    }
  }

  Widget _companyFields() => FutureBuilder<Map<String, dynamic>>(
    future: _options,
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return Column(
          children: [
            const Text('Could not load companies and hubs.'),
            OutlinedButton.icon(
              icon: const Icon(Icons.refresh),
              label: const Text('Retry companies'),
              onPressed: () => setState(() {
                _error = null;
                _options = _loadRegistrationOptions();
              }),
            ),
          ],
        );
      }
      if (!snapshot.hasData) return const LinearProgressIndicator();
      final hubs = List<Map<String, dynamic>>.from(
        (snapshot.data!['hubs'] as List).map(
          (e) => Map<String, dynamic>.from(e as Map),
        ),
      );
      final province = (_address['province'] ?? '').trim().toLowerCase();
      final localHubs = hubs
          .where(
            (h) =>
                (h['province'] ?? '').toString().trim().toLowerCase() ==
                province,
          )
          .toList();
      final companies = hubs
          .map((h) => h['company'] as String)
          .toSet()
          .toList();
      final selected = localHubs
          .where((h) => h['company'] == _company)
          .toList();
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Which company will you deliver for?',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 10),
          for (final company in companies) ...[
            Builder(
              builder: (context) {
                final nearby = localHubs
                    .where((h) => h['company'] == company)
                    .toList();
                final hiring = nearby
                    .where((h) => h['eligible'] == true)
                    .length;
                final enabled = hiring > 0;
                return Card(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(
                      color: _company == company
                          ? AppColors.pink
                          : AppColors.border,
                    ),
                  ),
                  child: ListTile(
                    enabled: enabled,
                    leading: Icon(
                      Icons.local_shipping_outlined,
                      color: enabled ? AppColors.pink : AppColors.textMuted,
                    ),
                    title: Text(company),
                    subtitle: Text(
                      nearby.isEmpty
                          ? 'No hubs in ${_address['province'] ?? 'your province'}'
                          : enabled
                          ? '$hiring hiring hub${hiring == 1 ? '' : 's'}'
                          : 'Not currently hiring',
                    ),
                    trailing: Icon(
                      _company == company
                          ? Icons.radio_button_checked
                          : Icons.radio_button_off,
                    ),
                    onTap: !enabled
                        ? null
                        : () => setState(() {
                            _company = company;
                            _hub = null;
                            _vehicle = null;
                            _companyTerms = false;
                            _companyTermsRead = false;
                          }),
                  ),
                );
              },
            ),
            const SizedBox(height: 8),
          ],
          FormField<int>(
            key: ValueKey('hub-$_company-$_hub'),
            initialValue: _hub,
            validator: (v) => v == null
                ? 'Choose a company and a hub accepting riders.'
                : null,
            builder: (field) => Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_company != null) ...[
                  const SizedBox(height: 12),
                  const Text(
                    "Hub You'll Be Dispatched From",
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  for (final hub in selected)
                    Card(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                        side: BorderSide(
                          color: _hub == hub['id']
                              ? AppColors.pink
                              : AppColors.border,
                        ),
                      ),
                      child: ListTile(
                        enabled: hub['eligible'] == true,
                        title: Text('${hub['location']}'),
                        subtitle: Text(
                          hub['eligible'] == true
                              ? 'Accepting riders'
                              : 'Not hiring',
                        ),
                        trailing: Icon(
                          _hub == hub['id']
                              ? Icons.check_circle
                              : Icons.circle_outlined,
                        ),
                        onTap: hub['eligible'] != true
                            ? null
                            : () => setState(() {
                                _hub = (hub['id'] as num).toInt();
                                _vehicle = null;
                              }),
                      ),
                    ),
                ],
                if (field.hasError)
                  Text(
                    field.errorText!,
                    style: TextStyle(color: AppColors.danger),
                  ),
              ],
            ),
          ),
          if (companies.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 16),
              child: Text(
                'No approved logistics companies have operational hubs yet. Please try again later.',
              ),
            ),
          const SizedBox(height: 24),
        ],
      );
    },
  );

  Widget _vehicleTypes() => FutureBuilder<Map<String, dynamic>>(
    future: _options,
    builder: (context, snapshot) {
      if (!snapshot.hasData) {
        return Text(
          snapshot.hasError
              ? 'Vehicle types could not be loaded.'
              : 'Loading vehicle types...',
        );
      }
      final options = snapshot.data!;
      final hubs = (options['hubs'] as List).cast<Map>();
      final selected = hubs.where((h) => h['id'] == _hub).firstOrNull;
      final available = selected?['available_vehicle_types'] as List? ?? [];
      final types = (options['vehicle_types'] as List)
          .cast<Map>()
          .where((t) => _ownership == 'own' || available.contains(t['slug']))
          .toList();
      return FormField<String>(
        key: ValueKey('$_hub-$_ownership-$_vehicle'),
        initialValue: _vehicle,
        validator: (v) =>
            v == null ? 'Choose an available vehicle type.' : null,
        builder: (field) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Vehicle type',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (final type in types)
                  SizedBox(
                    width: 140,
                    child: Card(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                        side: BorderSide(
                          color: _vehicle == type['slug']
                              ? AppColors.pink
                              : AppColors.border,
                        ),
                      ),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () =>
                            setState(() => _vehicle = '${type['slug']}'),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            children: [
                              Icon(
                                type['slug'] == 'two_wheels'
                                    ? Icons.two_wheeler
                                    : Icons.local_shipping_outlined,
                                color: AppColors.pink,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                '${type['name']}',
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            if (types.isEmpty)
              const Text(
                'No company vehicles are currently available at this hub.',
              ),
            if (field.hasError)
              Text(field.errorText!, style: TextStyle(color: AppColors.danger)),
          ],
        ),
      );
    },
  );

  Widget _buildNavigation() => Theme(
    data: Theme.of(context).copyWith(
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(48, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
          foregroundColor: AppColors.pinkDark,
          side: BorderSide(color: AppColors.pinkBorder),
        ),
      ),
    ),
    child: IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_step > 0) ...[
            Expanded(
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 48),
                child: OutlinedButton(
                  onPressed: _saving ? null : _previous,
                  child: const Text('Previous step'),
                ),
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48),
              child: PrimaryButton(
                label: !_lastStep ? 'Continue' : 'Submit application',
                loading: _saving,
                onPressed: _saving || _googleBusy || _pickingDocument
                    ? null
                    : !_lastStep
                    ? _next
                    : _submit,
              ),
            ),
          ),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, result) {
      if (!didPop) _handleBack();
    },
    child: HeroAuthScaffold(
      registration: true,
      bottomActions: _buildNavigation(),
      scrollController: _scroll,
      brandTitle: 'Create your account.',
      brandPoints: const [
        'Secure & verified registration',
        'Admin-approved accounts',
        'Confirmation sent to your email',
      ],
      brandDescription: 'Fill in your details across a few quick steps. Your account will be reviewed and activated by our admin team.',
      onBack: _handleBack,
      child: Theme(
        data: Theme.of(context).copyWith(
          outlinedButtonTheme: OutlinedButtonThemeData(
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
              foregroundColor: AppColors.pinkDark,
              backgroundColor: AppColors.white,
              side: BorderSide(color: AppColors.pinkBorder),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
          elevatedButtonTheme: ElevatedButtonThemeData(
            style: ElevatedButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
              backgroundColor: AppColors.pinkDark,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
          inputDecorationTheme: Theme.of(context).inputDecorationTheme.copyWith(
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 15,
            ),
          ),
        ),
        child: Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Create your ${widget.accountType} account',
                style: AppTypography.pageTitle,
              ),
              const SizedBox(height: 8),
              Text(
                _isRider
                    ? 'Join a logistics company. Complete your application for review.'
                    : 'Complete your details to submit your account for review.',
                style: AppTypography.caption,
              ),
              if (_isRider)
                TextButton.icon(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const RiderActivationScreen(),
                    ),
                  ),
                  icon: const Icon(Icons.link),
                  label: const Text('Already invited? Activate your account'),
                ),
              const SizedBox(height: 22),
              Center(
                child: StepProgressHeader(
                  stepLabels: _steps,
                  currentStep: _step,
                  numbered: true,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Step ${_step + 1} of ${_steps.length} · ${_steps[_step]}',
                style: AppTypography.sectionTitle,
              ),
              const SizedBox(height: 6),
              Text(
                {
                  'personal':
                      'Check the details from your ID. You must be $_minimumAge or older.',
                  'contact': 'Verify your email and add your contact number.',
                  'address': 'Where are you located?',
                  'documents': 'Upload your ID and take a clear selfie. Your information stays editable.',
                  'company':
                      'Choose an approved company and a hub accepting riders.',
                  'license': 'Upload your driver?s license, take a selfie holding it, and attach your clearance and resume.',
                  'vehicle': 'Bring your own vehicle or request an available company vehicle.',
                  'account': 'Choose your username and password. Your account will be reviewed before activation.',
                }[_page]!,
                style: AppTypography.caption,
              ),
              const SizedBox(height: 20),
              if (_page == 'contact' && _googleToken == null) ...[
                GoogleSignInButton(
                  onTap: _saving || _googleBusy ? null : _google,
                  loading: _googleBusy,
                ),
                const SizedBox(height: 18),
              ],
              if (_googleToken != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: Text(
                    'Email verified via Google. Complete the remaining application steps.',
                    style: AppTypography.caption,
                  ),
                ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.dangerSoft,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      _error!,
                      style: TextStyle(color: AppColors.danger, fontSize: 12),
                    ),
                  ),
                ),
              if (_page == 'personal') ...[
                AdaptiveCardGrid(
                  maxColumns: 2,
                  minimumWidth: 132,
                  children: [
                    AuthTextField(
                      key: const ValueKey('buyer-last'),
                      label: 'Last name',
                      controller: _last,
                      validator: _name,
                    ),
                    AuthTextField(
                      key: const ValueKey('buyer-first'),
                      label: _isRider ? 'First name' : 'Given names',
                      controller: _first,
                      validator: _name,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                AuthTextField(
                  label: 'Middle name',
                  validator: (value) => _noMiddleName ? null : _name(value),
                  controller: _middle,
                ),
                CheckboxListTile(
                  value: _noMiddleName,
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: const Text('I have no middle name'),
                  onChanged: (v) => setState(() {
                    _noMiddleName = v ?? false;
                    if (_noMiddleName) _middle.clear();
                  }),
                ),
                ...[
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: _suffix,
                    decoration: const InputDecoration(
                      labelText: 'Suffix (optional)',
                    ),
                    items: ['', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(value.isEmpty ? 'None' : value),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setState(() => _suffix = value),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: _sex,
                    decoration: const InputDecoration(labelText: 'Sex'),
                    items: const [
                      DropdownMenuItem(value: 'male', child: Text('Male')),
                      DropdownMenuItem(value: 'female', child: Text('Female')),
                    ],
                    onChanged: (value) => setState(() => _sex = value),
                    validator: (value) =>
                        value == null ? 'Choose your sex.' : null,
                  ),
                  const SizedBox(height: 14),
                  _birthdayFields(),
                ],
              ],
              if (_page == 'contact') ...[
                TextFormField(
                  key: const ValueKey('buyer-email'),
                  controller: _email,
                  readOnly: _googleToken != null,
                  onChanged: (_) => setState(() => _emailToken = null),
                  validator: (value) {
                    final error = Validators.email(value);
                    if (error != null) return error;
                    return (value ?? '').trim().toLowerCase().endsWith(
                          '@gmail.com',
                        )
                        ? null
                        : 'Use a Gmail address, as required on the website.';
                  },
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.mail_outline),
                  ),
                ),
                const SizedBox(height: 14),
                ...[
                  if (_googleToken != null || _emailToken != null) ...[
                    const Text(
                      'Email verified',
                      style: TextStyle(color: Colors.green),
                    ),
                    if (_googleToken == null)
                      TextButton(
                        onPressed: () => setState(() {
                          _emailToken = null;
                          _otp.clear();
                        }),
                        child: const Text('Verify again'),
                      ),
                  ] else ...[
                    OutlinedButton(
                      onPressed: _codeBusy || _resendSeconds > 0
                          ? null
                          : () => _emailCode(false),
                      child: Text(
                        _resendSeconds > 0
                            ? 'Resend in $_resendSeconds seconds'
                            : 'Send verification code',
                      ),
                    ),
                    if (_codeExpires != null)
                      Text(
                        DateTime.now().isAfter(_codeExpires!)
                            ? 'Code expired. Request another code.'
                            : 'Code expires in ${_codeExpires!.difference(DateTime.now()).inMinutes}:${(_codeExpires!.difference(DateTime.now()).inSeconds % 60).toString().padLeft(2, '0')}',
                      ),
                    AuthTextField(
                      label: '6-digit email code',
                      controller: _otp,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(6),
                      ],
                      keyboardType: TextInputType.number,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _codeBusy ? null : () => _emailCode(true),
                      child: const Text('Verify email'),
                    ),
                  ],
                ],
                const SizedBox(height: 20),
                AuthTextField(
                  key: const ValueKey('buyer-phone'),
                  label: 'Mobile number',
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9+]')),
                    LengthLimitingTextInputFormatter(13),
                  ],
                  textCapitalization: TextCapitalization.none,
                  hint: '09XXXXXXXXX or +639XXXXXXXXX',
                  validator: (value) =>
                      RegExp(r'^(09\d{9}|\+639\d{9})$')
                          .hasMatch(value?.trim() ?? '')
                      ? null
                      : 'Enter a valid Philippine mobile number.',
                ),
                const SizedBox(height: 14),
              ],
              if (_page == 'address')
                RegistrationAddressFields(
                  requirePostal: false,
                  service: widget.addressService,
                  postalLookup: (code, name) async {
                    final result = await _client.request(
                      'auth/registration-postal-code',
                      {'municipality_code': code, 'municipality': name},
                    );
                    return result['postal_code'] as String?;
                  },
                  value: _address,
                  onChanged: (value) => setState(() {
                    if (_address['province'] != value['province']) {
                      _company = null;
                      _hub = null;
                      _vehicle = null;
                      _companyTerms = false;
                      _companyTermsRead = false;
                    }
                    _address = value;
                  }),
                ),
              if (_page == 'documents') ...[
                FutureBuilder<List<Map<String, dynamic>>>(
                  future: _idTypes,
                  builder: (context, snapshot) => DropdownButtonFormField<int>(
                    initialValue: _idType,
                    decoration: const InputDecoration(labelText: 'ID type'),
                    items: (snapshot.data ?? [])
                        .map(
                          (row) => DropdownMenuItem(
                            value: (row['id'] as num).toInt(),
                            child: Text('${row['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setState(() => _idType = value),
                    validator: (value) =>
                        value == null ? 'Choose an ID type.' : null,
                  ),
                ),
                const SizedBox(height: 18),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final id = _uploadCard(
                      'id',
                      'Government-issued ID',
                      'Upload a clear photo of your valid ID.',
                    );
                    final selfie = _uploadCard(
                      'selfie',
                      'Identity selfie',
                      'Take a clear photo of your face.',
                    );
                    final lockedSelfie = Semantics(
                      enabled: _documents.containsKey('id'),
                      child: IgnorePointer(
                        ignoring: !_documents.containsKey('id'),
                        child: Opacity(
                          opacity: _documents.containsKey('id') ? 1 : 0.4,
                          child: selfie,
                        ),
                      ),
                    );
                    if (constraints.maxWidth >= 440) {
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(child: id),
                          const SizedBox(width: 12),
                          Expanded(child: lockedSelfie),
                        ],
                      );
                    }
                    return Column(
                      children: [id, const SizedBox(height: 14), lockedSelfie],
                    );
                  },
                ),
                if (!_documents.containsKey('id'))
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text('Upload your ID to enable the selfie.'),
                  ),
                const SizedBox(height: 16),
                const Text(
                  'ID: JPEG, PNG or PDF. Selfie: JPEG or PNG. Up to 5 MB per file.',
                ),
                const SizedBox(height: 24),
              ],
              if (_page == 'company') _companyFields(),
              if (_page == 'license') ...[
                AuthTextField(
                  label: 'Driver’s license number',
                  controller: _license,
                  inputFormatters: [LicenseNumberFormatter()],
                  textCapitalization: TextCapitalization.characters,
                  hint: 'A12-34-123456',
                  validator: (v) =>
                      RegExp(r'^[A-Z]\d{2}-\d{2}-\d{6}$')
                          .hasMatch((v ?? '').toUpperCase())
                      ? null
                      : 'Use A12-34-123456.',
                ),
                const SizedBox(height: 14),
                OutlinedButton.icon(
                  onPressed: _pickLicenseExpiry,
                  icon: const Icon(Icons.event_outlined),
                  label: Text(
                    _licenseExpiry.text.isEmpty
                        ? 'Select license expiry'
                        : _licenseExpiry.text,
                  ),
                ),
                const Text(
                  'Your license must remain valid for at least one month.',
                ),
                const SizedBox(height: 18),
                _uploadCard(
                  'license',
                  'Driver’s license',
                  'Your license also serves as your identity document.',
                ),
                const SizedBox(height: 14),
                _uploadCard(
                  'selfie',
                  'Selfie with License',
                  'Take a selfie holding your driver?s license.',
                ),
                const SizedBox(height: 14),
                _uploadCard(
                  'nbi',
                  'NBI clearance',
                  'Upload your clearance for the logistics review.',
                ),
                const SizedBox(height: 14),
                _uploadCard(
                  'resume',
                  'Resume (optional)',
                  'Add a resume if requested by your logistics company.',
                ),
                const SizedBox(height: 24),
              ],
              if (_page == 'vehicle') ...[
                const Text(
                  'Vehicle ownership',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    for (final own in [true, false])
                      Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(right: own ? 8 : 0),
                          child: Card(
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(
                                color: (_ownership == 'own') == own
                                    ? AppColors.pink
                                    : AppColors.border,
                              ),
                            ),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: () => setState(() {
                                _ownership = own ? 'own' : 'company';
                                _vehicle = null;
                              }),
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                  children: [
                                    Icon(
                                      own
                                          ? Icons.two_wheeler
                                          : Icons.business_center_outlined,
                                      color: AppColors.pink,
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      own
                                          ? 'My Own Vehicle'
                                          : 'Company Vehicle',
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      own
                                          ? 'I own the vehicle'
                                          : 'Provided by logistics',
                                      textAlign: TextAlign.center,
                                      style: AppTypography.caption,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                _vehicleTypes(),
                if (_ownership == 'own') ...[
                  const SizedBox(height: 16),
                  AuthTextField(
                    label: 'Vehicle brand',
                    controller: _brand,
                    validator: (v) =>
                        Validators.required(v, label: 'Vehicle brand'),
                  ),
                  const SizedBox(height: 14),
                  AuthTextField(
                    label: 'Vehicle model',
                    controller: _model,
                    validator: (v) =>
                        Validators.required(v, label: 'Vehicle model'),
                  ),
                  const SizedBox(height: 14),
                  AuthTextField(
                    label: 'Plate number',
                    controller: _plate,
                    validator: (v) =>
                        Validators.required(v, label: 'Plate number'),
                  ),
                  const SizedBox(height: 18),
                  _uploadCard(
                    'or',
                    'Official receipt (OR)',
                    'Upload your vehicle’s official receipt.',
                  ),
                  const SizedBox(height: 14),
                  _uploadCard(
                    'cr',
                    'Certificate of registration (CR)',
                    'Upload your vehicle’s registration certificate.',
                  ),
                ] else ...[
                  const SizedBox(height: 14),
                  const Text(
                    'Only vehicle types currently available at your selected hub can be chosen. Logistics assigns the vehicle after review.',
                  ),
                ],
                const SizedBox(height: 24),
              ],
              if (_lastStep) ...[
                ...[
                  AuthTextField(
                    label: 'Username',
                    controller: _username,
                    onChanged: _usernameChanged,
                    hint: 'At least 8 characters',
                    validator: (value) => _usernameAvailable == false
                        ? 'This username is already taken.'
                        : RegExp(r'^[A-Za-z0-9_-]{8,30}$').hasMatch(value ?? '')
                        ? null
                        : 'Use 8–30 letters, numbers, underscores or hyphens.',
                  ),
                  if (_usernameStatus != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6, bottom: 14),
                      child: Text(
                        _usernameStatus!,
                        style: TextStyle(
                          color: _usernameAvailable == true
                              ? Colors.green
                              : _usernameAvailable == false
                              ? AppColors.danger
                              : AppColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                    ),
                ],
                if (_isRider)
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.pageBackground,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Your details', style: AppTypography.sectionTitle),
                        const SizedBox(height: 8),
                        Text('${_first.text.trim()} ${_last.text.trim()}'),
                        Text(_email.text.trim()),
                        Text(_phone.text.trim()),
                        if (_birthday != null)
                          Text(
                            'Date of birth: ${_birthday!.month}/${_birthday!.day}/${_birthday!.year}',
                          ),
                        const SizedBox(height: 8),
                        Text(
                          [
                                _address['house_no'],
                                _address['street'],
                                _address['barangay'],
                                _address['municipality'],
                                _address['province'],
                                _address['region'],
                                _address['postal_code'],
                              ]
                              .whereType<String>()
                              .where((part) => part.isNotEmpty)
                              .join(', '),
                        ),
                        if (_isRider) ...[
                          const SizedBox(height: 8),
                          Text('Company: $_company'),
                          Text(
                            'License: ${_license.text.trim()} · expires ${_licenseExpiry.text}',
                          ),
                          Text(
                            'Vehicle: ${_ownership == 'own' ? 'Own vehicle' : 'Company vehicle'} · ${_vehicle?.replaceAll('_', ' ')}',
                          ),
                          if (_ownership == 'own')
                            Text(
                              '${_brand.text.trim()} ${_model.text.trim()} · ${_plate.text.trim().toUpperCase()}',
                            ),
                        ],
                        Text('${_documents.length} documents attached'),
                        TextButton(
                          onPressed: _saving
                              ? null
                              : () => setState(() {
                                  _step = 0;
                                  _error = null;
                                }),
                          child: const Text('Edit details'),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 18),
                AuthTextField(
                  key: const ValueKey('buyer-password'),
                  label: 'Password',
                  controller: _password,
                  obscureText: true,
                  autofillHints: const [AutofillHints.newPassword],
                  textInputAction: TextInputAction.next,
                  validator: _passwordRule,
                ),
                const SizedBox(height: 14),
                AuthTextField(
                  key: const ValueKey('buyer-confirmation'),
                  label: 'Confirm password',
                  controller: _confirmation,
                  obscureText: true,
                  autofillHints: const [AutofillHints.newPassword],
                  textInputAction: TextInputAction.done,
                  validator: (value) =>
                      Validators.confirmPassword(value, _password.text),
                ),
                const SizedBox(height: 12),
                CheckboxListTile(
                  value: _terms,
                  onChanged: _saving || !_termsRead
                      ? null
                      : (value) => setState(() => _terms = value ?? false),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: Text(
                    'I accept the ${widget.accountType} terms and conditions.',
                    style: AppTypography.caption,
                  ),
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton(
                    onPressed: _termsPage,
                    child: Text('Read ${widget.accountType} terms'),
                  ),
                ),
                if (_isRider) ...[
                  CheckboxListTile(
                    value: _companyTerms,
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: Text(
                      'I accept the terms of ${_company ?? 'my logistics company'}.',
                    ),
                    onChanged: _companyTermsRead
                        ? (v) => setState(() => _companyTerms = v ?? false)
                        : null,
                  ),
                  TextButton(
                    onPressed: () => _termsPage(company: true),
                    child: const Text('Read company terms'),
                  ),
                  const SizedBox(height: 14),
                ],
                const SizedBox(height: 24),
              ],
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    ),
  );
}
