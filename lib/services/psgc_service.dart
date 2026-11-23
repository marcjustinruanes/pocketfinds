import 'dart:convert';

import 'package:http/http.dart' as http;

/// One PSGC (Philippine Standard Geographic Code) entry — a region,
/// province, city/municipality, or barangay.
class PsgcEntry {
  final String code;
  final String name;

  const PsgcEntry({required this.code, required this.name});

  factory PsgcEntry.fromJson(Map<String, dynamic> json) =>
      PsgcEntry(code: json['code'].toString(), name: json['name'] as String);
}

/// Real, free, no-API-key Philippine address lookups against the public
/// PSGC registry (https://psgc.gitlab.io/api/) — backs the cascading
/// Region → Province → City/Municipality → Barangay pickers on the address
/// form. Results are cached in-memory per level for the life of the app
/// (regions/provinces/cities/barangays don't change mid-session; caching
/// the in-flight `Future` itself also de-dupes concurrent repeat requests).
/// Every call is defensive: a network hiccup returns an empty list rather
/// than throwing, so the address form can fall back to free-text entry
/// instead of getting stuck.
class PsgcService {
  void clearFailedLookups() {
    _regionsCache = null;
    _provincesCache.clear();
    _citiesCache.clear();
    _barangaysCache.clear();
  }

  static const _base = 'https://psgc.gitlab.io/api';
  static final _client = http.Client();

  static Future<List<PsgcEntry>>? _regionsCache;
  static final Map<String, Future<List<PsgcEntry>>> _provincesCache = {};
  static final Map<String, Future<List<PsgcEntry>>> _citiesCache = {};
  static final Map<String, Future<List<PsgcEntry>>> _barangaysCache = {};

  Future<List<PsgcEntry>> fetchRegions() {
    return _regionsCache ??= _get('/regions/');
  }

  /// NCR (and a couple of other regions) have no provinces — cities sit
  /// directly under the region in that case. Callers should fall back to
  /// [fetchCitiesForRegion] when this returns an empty list.
  Future<List<PsgcEntry>> fetchProvinces(String regionCode) {
    return _provincesCache.putIfAbsent(
      regionCode,
      () => _get('/regions/$regionCode/provinces/'),
    );
  }

  Future<List<PsgcEntry>> fetchCities(String provinceCode) {
    return _citiesCache.putIfAbsent(
      'p_$provinceCode',
      () => _get('/provinces/$provinceCode/cities-municipalities/'),
    );
  }

  Future<List<PsgcEntry>> fetchCitiesForRegion(String regionCode) {
    return _citiesCache.putIfAbsent(
      'r_$regionCode',
      () => _get('/regions/$regionCode/cities-municipalities/'),
    );
  }

  Future<List<PsgcEntry>> fetchBarangays(String cityCode) {
    return _barangaysCache.putIfAbsent(
      cityCode,
      () => _get('/cities-municipalities/$cityCode/barangays/'),
    );
  }

  Future<List<PsgcEntry>> _get(String path) async {
    try {
      final response = await _client
          .get(Uri.parse('$_base$path'))
          .timeout(const Duration(seconds: 8));
      if (response.statusCode != 200) return const [];
      final list = jsonDecode(response.body) as List;
      return list
          .whereType<Map<String, dynamic>>()
          .map(PsgcEntry.fromJson)
          .toList()
        ..sort((a, b) => a.name.compareTo(b.name));
    } catch (_) {
      // Network hiccup / PSGC down — caller falls back to free-text entry.
      return const [];
    }
  }
}
