import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../services/auth_service.dart';
import '../../services/psgc_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../utils/validators.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/auth_text_field.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/searchable_picker_sheet.dart';

/// A real, dedicated delivery-information page — recipient phone, house/
/// street, and a cascading Region → Province → City/Municipality →
/// Barangay picker backed by the real PSGC registry (`PsgcService`), not
/// free-text-only fields. Replaces the old `edit_address_sheet.dart` bottom
/// sheet at every call site; same return contract (pushes, returns the
/// updated [AppUser] on save or null on cancel) so callers barely change.
class AddressFormScreen extends StatefulWidget {
  final AppUser user;
  final Map<String, dynamic>? savedAddress;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>)?
  onSaveAddress;

  const AddressFormScreen({
    super.key,
    required this.user,
    this.savedAddress,
    this.onSaveAddress,
  });

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  late final _recipientController = TextEditingController(
    text:
        widget.savedAddress?['recipient_name']?.toString() ??
        widget.user.displayName,
  );
  late final _labelController = TextEditingController(
    text: widget.savedAddress?['label']?.toString() ?? 'Home',
  );
  late final _postalController = TextEditingController(
    text: widget.savedAddress?['postal_code']?.toString() ?? '',
  );
  final _formKey = GlobalKey<FormState>();
  final _psgc = PsgcService();
  late final _phoneController = TextEditingController(
    text: widget.user.contactNo ?? '',
  );
  late final _houseNoController = TextEditingController(
    text: widget.user.houseNo ?? '',
  );
  late final _streetController = TextEditingController(
    text: widget.user.street ?? '',
  );

  String? _regionCode;
  late String? _regionName = widget.user.region;
  String? _provinceCode;
  late String? _provinceName = widget.user.province;
  String? _cityCode;
  late String? _cityName = widget.user.municipality;
  late String? _barangayName = widget.user.barangay;

  bool _saving = false;
  String? _locationError;

  @override
  void initState() {
    super.initState();
    final saved = widget.savedAddress;
    if (saved != null) {
      _phoneController.text = '${saved['contact_no'] ?? ''}';
      _houseNoController.text = '${saved['house_no'] ?? ''}';
      _streetController.text = '${saved['street'] ?? ''}';
      _regionName = saved['region'] as String?;
      _provinceName = saved['province'] as String?;
      _cityName = saved['municipality'] as String?;
      _barangayName = saved['barangay'] as String?;
    }
  }

  @override
  void dispose() {
    _postalController.dispose();
    _recipientController.dispose();
    _labelController.dispose();
    _phoneController.dispose();
    _houseNoController.dispose();
    _streetController.dispose();
    super.dispose();
  }

  Future<void> _pickRegion() async {
    final regions = await _psgc.fetchRegions();
    if (!mounted) return;
    final selected = await showSearchablePicker(
      context: context,
      title: 'Select region',
      options: regions.map((r) => PickerOption(r.code, r.name)).toList(),
      selectedValue: _regionCode,
      emptyMessage:
          "Couldn't load regions — check your connection and try again.",
    );
    if (selected == null) return;
    final region = regions.firstWhere((r) => r.code == selected);
    setState(() {
      _regionCode = region.code;
      _regionName = region.name;
      // Downstream selections no longer apply once the region changes.
      _provinceCode = null;
      _provinceName = null;
      _cityCode = null;
      _cityName = null;
      _barangayName = null;
      _locationError = null;
    });
  }

  Future<void> _pickProvince() async {
    if (_regionCode == null) {
      setState(() => _locationError = 'Select a region first.');
      return;
    }
    var provinces = await _psgc.fetchProvinces(_regionCode!);
    // Regions like NCR have no provinces — cities sit directly under them.
    if (provinces.isEmpty) {
      await _pickCityDirect();
      return;
    }
    if (!mounted) return;
    final selected = await showSearchablePicker(
      context: context,
      title: 'Select province',
      options: provinces.map((p) => PickerOption(p.code, p.name)).toList(),
      selectedValue: _provinceCode,
    );
    if (selected == null) return;
    final province = provinces.firstWhere((p) => p.code == selected);
    setState(() {
      _provinceCode = province.code;
      _provinceName = province.name;
      _cityCode = null;
      _cityName = null;
      _barangayName = null;
      _locationError = null;
    });
  }

  Future<void> _pickCityDirect() async {
    final cities = await _psgc.fetchCitiesForRegion(_regionCode!);
    if (!mounted) return;
    final selected = await showSearchablePicker(
      context: context,
      title: 'Select city/municipality',
      options: cities.map((c) => PickerOption(c.code, c.name)).toList(),
      selectedValue: _cityCode,
      emptyMessage:
          "Couldn't load cities — check your connection and try again.",
    );
    if (selected == null) return;
    final city = cities.firstWhere((c) => c.code == selected);
    setState(() {
      _provinceCode = null;
      _provinceName = null; // no province tier for this region
      _cityCode = city.code;
      _cityName = city.name;
      _barangayName = null;
      _locationError = null;
    });
  }

  Future<void> _pickCity() async {
    if (_provinceCode == null && _regionCode != null) {
      // This region has no provinces (e.g. NCR) — city picking IS the
      // province step here; just re-run it.
      await _pickProvince();
      return;
    }
    if (_provinceCode == null) {
      setState(() => _locationError = 'Select a region and province first.');
      return;
    }
    final cities = await _psgc.fetchCities(_provinceCode!);
    if (!mounted) return;
    final selected = await showSearchablePicker(
      context: context,
      title: 'Select city/municipality',
      options: cities.map((c) => PickerOption(c.code, c.name)).toList(),
      selectedValue: _cityCode,
      emptyMessage:
          "Couldn't load cities — check your connection and try again.",
    );
    if (selected == null) return;
    final city = cities.firstWhere((c) => c.code == selected);
    setState(() {
      _cityCode = city.code;
      _cityName = city.name;
      _barangayName = null;
      _locationError = null;
    });
  }

  Future<void> _pickBarangay() async {
    if (_cityCode == null) {
      setState(() => _locationError = 'Select a city/municipality first.');
      return;
    }
    final barangays = await _psgc.fetchBarangays(_cityCode!);
    if (!mounted) return;
    final selected = await showSearchablePicker(
      context: context,
      title: 'Select barangay',
      options: barangays.map((b) => PickerOption(b.code, b.name)).toList(),
      selectedValue: null,
      emptyMessage:
          "Couldn't load barangays — check your connection and try again.",
    );
    if (selected == null) return;
    final barangay = barangays.firstWhere((b) => b.code == selected);
    setState(() {
      _barangayName = barangay.name;
      _locationError = null;
    });
  }

  String _placeKey(String value) => value.toLowerCase().replaceAll(
    RegExp(r'city of |municipality of |city|province of |[^a-z0-9]'),
    '',
  );
  Future<void> _useMyLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw Exception(
          'Location is off. Turn it on in Settings or enter your address manually.',
        );
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (!mounted) return;
        final settings = await showDialog<bool>(
          context: context,
          builder: (c) => AlertDialog(
            title: const Text('Location access is off'),
            content: const Text(
              'Enable location access in Settings, or keep entering the address manually.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(c, false),
                child: const Text('Enter manually'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(c, true),
                child: const Text('Open Settings'),
              ),
            ],
          ),
        );
        if (settings == true) await Geolocator.openAppSettings();
        return;
      }
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      final places = await Geocoding().placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      );
      if (places.isEmpty) {
        throw Exception(
          'No address was found here. Enter the address manually.',
        );
      }
      final place = places.first;
      final regions = await _psgc.fetchRegions();
      PsgcEntry? region, province, city, barangay;
      final admin = [
        place.administrativeArea,
        place.subAdministrativeArea,
      ].whereType<String>().map(_placeKey).toSet();
      final local = [
        place.locality,
        place.subAdministrativeArea,
      ].whereType<String>().map(_placeKey).toSet();
      for (final r in regions) {
        final provinces = await _psgc.fetchProvinces(r.code);
        for (final p in provinces) {
          if (admin.contains(_placeKey(p.name))) {
            region = r;
            province = p;
            break;
          }
        }
        if (province != null) break;
        if (provinces.isEmpty) {
          final cities = await _psgc.fetchCitiesForRegion(r.code);
          for (final c in cities) {
            if (local.contains(_placeKey(c.name))) {
              region = r;
              city = c;
              break;
            }
          }
          if (city != null) break;
        }
      }
      if (province != null) {
        for (final c in await _psgc.fetchCities(province.code)) {
          if (local.contains(_placeKey(c.name))) {
            city = c;
            break;
          }
        }
      }
      if (city != null) {
        for (final b in await _psgc.fetchBarangays(city.code)) {
          if (_placeKey(b.name) == _placeKey(place.subLocality ?? '')) {
            barangay = b;
            break;
          }
        }
      }
      if (!mounted) return;
      setState(() {
        if (region != null) {
          _regionCode = region.code;
          _regionName = region.name;
        }
        _provinceCode = province?.code;
        _provinceName =
            province?.name ??
            (region?.code.startsWith('13') == true ? 'Metro Manila' : null);
        _cityCode = city?.code;
        _cityName = city?.name;
        _barangayName = barangay?.name;
        if ((place.postalCode ?? '').length == 4) {
          _postalController.text = place.postalCode!;
        }
        if ((place.street ?? '').isNotEmpty) {
          _streetController.text = place.street!;
        }
        _locationError = 'Location suggested. Check the details and select any missing city or barangay before saving.';
      });
    } catch (e) {
      if (mounted) {
        setState(
          () => _locationError = e is Exception
              ? e.toString().replaceFirst('Exception: ', '')
              : friendlyError(e),
        );
      }
    }
  }

  bool get _locationComplete => _cityName != null && _barangayName != null;

  Future<void> _save() async {
    final formValid = _formKey.currentState!.validate();
    if (!formValid || !_locationComplete) {
      setState(
        () => _locationError ??= 'Complete your region, city, and barangay.',
      );
      return;
    }

    setState(() => _saving = true);
    try {
      if (widget.onSaveAddress != null) {
        final saved = await widget.onSaveAddress!({
          'postal_code': _postalController.text.trim(),
          'label': _labelController.text.trim(),
          'recipient_name': _recipientController.text.trim(),
          'contact_no': _phoneController.text.trim(),
          'house_no': _houseNoController.text.trim(),
          'street': _streetController.text.trim(),
          'region': _regionName,
          'province':
              _provinceName ??
              (_regionCode?.startsWith('13') == true
                  ? 'Metro Manila'
                  : _regionName),
          'municipality': _cityName,
          'barangay': _barangayName,
        });
        if (mounted) Navigator.pop(context, saved);
        return;
      }
      final updated = await AuthService().updateMyProfile(
        contactNo: _phoneController.text.trim(),
        houseNo: _houseNoController.text.trim(),
        street: _streetController.text.trim(),
        barangay: _barangayName ?? '',
        municipality: _cityName ?? '',
        province: _provinceName ?? '',
        region: _regionName,
      );
      if (!mounted) return;
      AppSnackBar.success(context, 'Delivery information saved.');
      Navigator.of(context).pop(updated);
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not save address: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('Delivery Information', style: AppTypography.pageTitle),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            if (widget.onSaveAddress != null) ...[
              AuthTextField(
                label: 'Address label',
                controller: _labelController,
              ),
              const SizedBox(height: AppSpacing.md),
              AuthTextField(
                label: 'Recipient name *',
                controller: _recipientController,
                validator: Validators.required,
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            AuthTextField(
              label: 'Phone number *',
              controller: _phoneController,
              hint: '09171234567',
              keyboardType: TextInputType.phone,
              validator: Validators.contactNo,
              prefixIcon: Icons.call_outlined,
            ),
            const SizedBox(height: 4),
            Text(
              'Required — the rider may need to contact you if your address can\'t be located.',
              style: AppTypography.caption,
            ),
            const SizedBox(height: AppSpacing.lg),
            AuthTextField(
              label: 'House / unit / building no.',
              controller: _houseNoController,
              hint: 'e.g. Blk 4 Lot 12',
            ),
            const SizedBox(height: AppSpacing.md),
            AuthTextField(
              label: 'Street',
              controller: _streetController,
              validator: Validators.required,
              hint: 'e.g. Rizal St.',
            ),
            const SizedBox(height: AppSpacing.lg),
            AuthTextField(
              label: 'Postal code *',
              controller: _postalController,
              keyboardType: TextInputType.number,
              validator: (v) => RegExp(r'^\d{4}$').hasMatch(v ?? '')
                  ? null
                  : 'Enter a four-digit postal code.',
            ),
            OutlinedButton.icon(
              onPressed: _saving ? null : _useMyLocation,
              icon: const Icon(Icons.my_location),
              label: const Text('Use My Location'),
            ),
            Text('Location *', style: AppTypography.label),
            const SizedBox(height: 6),
            _LocationRow(
              label: 'Region',
              value: _regionName,
              onTap: _pickRegion,
            ),
            const SizedBox(height: 8),
            _LocationRow(
              label: 'Province',
              value: _provinceName,
              onTap: _pickProvince,
              optional: true,
            ),
            const SizedBox(height: 8),
            _LocationRow(
              label: 'City / Municipality',
              value: _cityName,
              onTap: _pickCity,
            ),
            const SizedBox(height: 8),
            _LocationRow(
              label: 'Barangay',
              value: _barangayName,
              onTap: _pickBarangay,
            ),
            if (_locationError != null) ...[
              const SizedBox(height: 8),
              Text(
                _locationError!,
                style: TextStyle(color: AppColors.danger, fontSize: 12.5),
              ),
            ],
            const SizedBox(height: AppSpacing.xxl),
            PrimaryButton(
              label: 'Save Delivery Information',
              loading: _saving,
              onPressed: _save,
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationRow extends StatelessWidget {
  final String label;
  final String? value;
  final VoidCallback onTap;
  final bool optional;

  const _LocationRow({
    required this.label,
    required this.value,
    required this.onTap,
    this.optional = false,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: 14,
        ),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: value == null && !optional
                ? AppColors.danger.withValues(alpha: 0.4)
                : AppColors.pinkBorder,
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label + (optional ? '' : ' *'),
                    style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value ??
                        (optional
                            ? 'Not applicable / tap to select'
                            : 'Tap to select'),
                    style: AppTypography.body.copyWith(
                      color: value == null
                          ? AppColors.textMuted
                          : AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}
