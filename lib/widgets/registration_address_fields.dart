import 'package:flutter/material.dart';

import '../services/psgc_service.dart';
import 'auth_text_field.dart';

/// Cascading PSGC selectors, retained by the registration form between pages.
class RegistrationAddressFields extends StatefulWidget {
  final Map<String, String> value;
  final ValueChanged<Map<String, String>> onChanged;
  final PsgcService? service;
  final bool requirePostal;
  final Future<String?> Function(String code, String municipality)?
  postalLookup;
  const RegistrationAddressFields({
    super.key,
    required this.value,
    required this.onChanged,
    this.service,
    this.requirePostal = false,
    this.postalLookup,
  });
  @override
  State<RegistrationAddressFields> createState() =>
      _RegistrationAddressFieldsState();
}

class _RegistrationAddressFieldsState extends State<RegistrationAddressFields> {
  late final _api = widget.service ?? PsgcService();
  List<PsgcEntry> _regions = [], _provinces = [], _cities = [], _barangays = [];
  bool _loading = true;
  late final _street = TextEditingController(text: widget.value['street']);
  late final _house = TextEditingController(text: widget.value['house_no']);
  late final _postal = TextEditingController(text: widget.value['postal_code']);
  int _generation = 0;
  int _postalGeneration = 0;
  bool _postalLoading = false, _postalResolved = false;

  Future<void> _lookupPostal(String code, String name) async {
    final generation = ++_postalGeneration;
    setState(() => _postalLoading = true);
    String? postal;
    try {
      postal = await widget.postalLookup?.call(code, name);
    } catch (_) {
      // Match the website: allow manual entry when lookup is unavailable.
    }
    if (!mounted || generation != _postalGeneration) return;
    final resolved = RegExp(r'^\d{4}$').hasMatch(postal ?? '');
    setState(() {
      _postalLoading = false;
      _postalResolved = resolved;
      _postal.text = resolved ? postal! : '';
    });
    widget.onChanged({...widget.value, 'postal_code': _postal.text});
  }

  @override
  void initState() {
    super.initState();
    _load();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted &&
          widget.value['municipality_code'] != null &&
          _postal.text.isEmpty) {
        _lookupPostal(
          widget.value['municipality_code']!,
          widget.value['municipality']!,
        );
      }
    });
  }

  @override
  void dispose() {
    _street.dispose();
    _house.dispose();
    _postal.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final generation = ++_generation;
    final regions = await _api.fetchRegions();
    final region = widget.value['region_code'];
    final province = widget.value['province_code'];
    final city = widget.value['municipality_code'];
    final provinces = region == null
        ? <PsgcEntry>[]
        : await _api.fetchProvinces(region);
    final cities = province != null
        ? await _api.fetchCities(province)
        : region != null && provinces.isEmpty
        ? await _api.fetchCitiesForRegion(region)
        : <PsgcEntry>[];
    final barangays = city == null
        ? <PsgcEntry>[]
        : await _api.fetchBarangays(city);
    if (mounted && generation == _generation) {
      setState(() {
        _regions = regions;
        _provinces = provinces;
        _cities = cities;
        _barangays = barangays;
        _loading = false;
      });
    }
  }

  void _change(
    String key,
    String? code,
    List<PsgcEntry> choices,
    List<String> clear,
  ) {
    final updated = Map<String, String>.from(widget.value);
    if (key != 'barangay') {
      _postalGeneration++;
      _postal.clear();
      updated.remove('postal_code');
      _postalResolved = false;
      _postalLoading = false;
    }
    for (final field in clear) {
      updated.remove(field);
      updated.remove('${field}_code');
    }
    if (code != null) {
      updated['${key}_code'] = code;
      updated[key] = choices.firstWhere((e) => e.code == code).name;
    }
    // NCR has no province; record its region as the address province.
    if (key == 'municipality' && _provinces.isEmpty) {
      updated['province'] = (updated['region_code'] ?? '').startsWith('13')
          ? 'Metro Manila'
          : updated['region'] ?? 'Metro Manila';
    }
    widget.onChanged(updated);
    setState(() => _loading = true);
    _api.clearFailedLookups();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _load();
      if (key == 'municipality' && code != null) {
        _lookupPostal(code, updated[key]!);
      }
    });
  }

  Widget _selector(
    String label,
    String key,
    List<PsgcEntry> choices,
    List<String> clear,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: DropdownButtonFormField<String>(
      key: ValueKey('$key-${widget.value['${key}_code']}-${choices.length}'),
      initialValue: choices.any((e) => e.code == widget.value['${key}_code'])
          ? widget.value['${key}_code']
          : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: choices
          .map(
            (e) => DropdownMenuItem(
              value: e.code,
              child: Text(e.name, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: _loading || choices.isEmpty
          ? null
          : (code) => _change(key, code, choices, clear),
      validator: (_) =>
          (widget.value[key] ?? '').isEmpty ? 'Choose $label.' : null,
    ),
  );
  Widget _pair(Widget first, Widget second) => LayoutBuilder(
    builder: (context, size) {
      if (size.maxWidth < 280 ||
          MediaQuery.textScalerOf(context).scale(14) > 18)
        return Column(children: [first, second]);
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: first),
          const SizedBox(width: 12),
          Expanded(child: second),
        ],
      );
    },
  );
  @override
  Widget build(BuildContext context) => Column(
    children: [
      if (_loading) const LinearProgressIndicator(),
      if (_provinces.isNotEmpty)
        _pair(
          _selector('Region', 'region', _regions, [
            'province',
            'municipality',
            'barangay',
          ]),
          _selector('Province', 'province', _provinces, [
            'municipality',
            'barangay',
          ]),
        )
      else
        _selector('Region', 'region', _regions, [
          'province',
          'municipality',
          'barangay',
        ]),
      _pair(
        _selector('City / municipality', 'municipality', _cities, ['barangay']),
        _selector('Barangay', 'barangay', _barangays, []),
      ),
      if (!_loading &&
          (_regions.isEmpty ||
              (widget.value['municipality_code'] != null &&
                  _barangays.isEmpty)))
        TextButton.icon(
          onPressed: () {
            setState(() => _loading = true);
            _load();
          },
          icon: const Icon(Icons.refresh),
          label: const Text('Address service unavailable. Retry'),
        ),
      ...[
        TextFormField(
          decoration: InputDecoration(
            labelText: 'Postal code',
            suffixIcon:
                !_postalLoading &&
                    !_postalResolved &&
                    widget.value['municipality_code'] != null
                ? IconButton(
                    tooltip: 'Retry postal lookup',
                    icon: const Icon(Icons.refresh),
                    onPressed: () => _lookupPostal(
                      widget.value['municipality_code']!,
                      widget.value['municipality']!,
                    ),
                  )
                : null,
            helperText: _postalLoading
                ? 'Looking up postal code…'
                : _postalResolved
                ? 'Automatically filled from your city / municipality.'
                : 'Enter if known, or leave blank.',
          ),
          controller: _postal,
          readOnly: _postalLoading || _postalResolved,
          keyboardType: TextInputType.number,
          validator: (value) =>
              (!widget.requirePostal && (value ?? '').isEmpty) ||
                  RegExp(r'^\d{4}$').hasMatch(value ?? '')
              ? null
              : 'Enter a four-digit postal code.',
          onChanged: (text) =>
              widget.onChanged({...widget.value, 'postal_code': text}),
        ),
        const SizedBox(height: 14),
      ],
      AuthTextField(
        label: 'House / unit number',
        controller: _house,
        onChanged: (text) =>
            widget.onChanged({...widget.value, 'house_no': text}),
      ),
      const SizedBox(height: 14),
      AuthTextField(
        key: const ValueKey('registration-street'),
        label: 'Street / building',
        controller: _street,
        validator: (text) => (text ?? '').length > 100
            ? 'Use no more than 100 characters.'
            : null,
        onChanged: (text) =>
            widget.onChanged({...widget.value, 'street': text}),
      ),
    ],
  );
}
