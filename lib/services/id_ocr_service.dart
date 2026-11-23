import 'dart:io';

import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

/// What the ID-Selfie step's OCR pass could make out of the uploaded ID
/// photo. Every field is a best-effort guess the Personal-info step
/// pre-fills into ordinary editable text fields — never authoritative, and
/// never locking out manual entry.
class IdOcrResult {
  final String? lastName;
  final String? givenNames;
  final String? middleName;
  final String? sex; // 'male' or 'female'
  final DateTime? birthday;

  /// Only true when we're confident enough to skip straight to the Personal
  /// step without the "Could not read your ID" banner — requires last name,
  /// given names, and birthday to all have parsed successfully. Sex is a
  /// bonus field and never required for confidence.
  bool get isConfident =>
      (lastName?.isNotEmpty ?? false) &&
      (givenNames?.isNotEmpty ?? false) &&
      birthday != null;

  const IdOcrResult({
    this.lastName,
    this.givenNames,
    this.middleName,
    this.sex,
    this.birthday,
  });

  static const empty = IdOcrResult();
}

/// On-device OCR of Philippine ID photos (Driver's License, PhilSys, UMID,
/// Postal ID, Voter's ID, etc.) via Google ML Kit's on-device text
/// recognizer — no image ever leaves the device for this step.
///
/// PH ID layouts vary a lot by issuer, so [parse] is deliberately
/// conservative: it only reports what it can match against a handful of
/// common label patterns, and callers must always still let the user type
/// over whatever comes back.
class IdOcrService {
  final TextRecognizer _recognizer = TextRecognizer(
    script: TextRecognitionScript.latin,
  );

  /// Runs OCR on [imageFile] and parses the result. Never throws — any
  /// recognizer failure (blurry photo, unsupported format, no text found)
  /// comes back as [IdOcrResult.empty] so callers can uniformly fall back to
  /// manual entry.
  Future<IdOcrResult> recognize(File imageFile) async {
    try {
      final input = InputImage.fromFilePath(imageFile.path);
      final recognized = await _recognizer.processImage(input);
      return parse(recognized.text);
    } catch (_) {
      return IdOcrResult.empty;
    }
  }

  void dispose() => _recognizer.close();

  /// Pure text-parsing heuristic — no plugin/platform dependency, so this is
  /// unit-testable against canned OCR output.
  static IdOcrResult parse(String rawText) {
    final lines = rawText
        .split('\n')
        .map((l) => l.trim())
        .where((l) => l.isNotEmpty)
        .toList();

    String? lastName;
    String? givenNames;
    String? middleName;
    String? sex;
    DateTime? birthday;

    String? valueAfterLabel(String line, List<String> labels) {
      final upper = line.toUpperCase();
      // Longest label first, so e.g. "GIVEN NAMES" matches whole rather than
      // "GIVEN NAME" matching as a prefix and leaving a stray trailing "S".
      final sortedLabels = [...labels]
        ..sort((a, b) => b.length.compareTo(a.length));
      for (final label in sortedLabels) {
        final idx = upper.indexOf(label);
        if (idx == -1) continue;
        final afterIdx = idx + label.length;
        // Require a word boundary right after the label — otherwise a
        // shorter label could still match inside a longer word by chance.
        if (afterIdx < upper.length &&
            RegExp(r'[A-Z]').hasMatch(upper[afterIdx])) {
          continue;
        }
        final rest = line.substring(afterIdx).trim();
        final cleaned = rest.replaceFirst(RegExp(r'^[:\-\s]+'), '').trim();
        return cleaned.isEmpty ? null : cleaned;
      }
      return null;
    }

    bool lineIsJustLabel(String line, List<String> labels) {
      final upper = line.toUpperCase().trim();
      return labels.any((l) => upper == l || upper == '$l:');
    }

    for (var i = 0; i < lines.length; i++) {
      final line = lines[i];
      final nextLine = i + 1 < lines.length ? lines[i + 1] : null;

      const lastNameLabels = ['LAST NAME', 'SURNAME', 'APELYIDO'];
      const givenNameLabels = [
        'GIVEN NAME',
        'GIVEN NAMES',
        'FIRST NAME',
        'PANGALAN',
      ];
      const middleNameLabels = [
        'MIDDLE NAME',
        'GITNANG APELYIDO',
        'GITNANG PANGALAN',
      ];
      const sexLabels = ['SEX', 'GENDER', 'KASARIAN'];
      const dobLabels = [
        'DATE OF BIRTH',
        'BIRTHDATE',
        'BIRTH DATE',
        'KAARAWAN',
        'DOB',
      ];

      lastName ??=
          valueAfterLabel(line, lastNameLabels) ??
          (lineIsJustLabel(line, lastNameLabels) ? nextLine : null);
      givenNames ??=
          valueAfterLabel(line, givenNameLabels) ??
          (lineIsJustLabel(line, givenNameLabels) ? nextLine : null);
      middleName ??=
          valueAfterLabel(line, middleNameLabels) ??
          (lineIsJustLabel(line, middleNameLabels) ? nextLine : null);
      if (sex == null) {
        final raw =
            valueAfterLabel(line, sexLabels) ??
            (lineIsJustLabel(line, sexLabels) ? nextLine : null);
        if (raw != null) {
          final upperRaw = raw.toUpperCase();
          if (upperRaw.startsWith('M')) sex = 'male';
          if (upperRaw.startsWith('F')) sex = 'female';
        }
      }
      if (birthday == null) {
        final raw =
            valueAfterLabel(line, dobLabels) ??
            (lineIsJustLabel(line, dobLabels) ? nextLine : null);
        if (raw != null) birthday = _tryParseDate(raw);
      }
    }

    // Some IDs never label sex/DOB explicitly but do print a bare date
    // somewhere on the card — take the first plausible date as a last resort.
    birthday ??= _findAnyDate(lines);

    return IdOcrResult(
      lastName: lastName,
      givenNames: givenNames,
      middleName: middleName,
      sex: sex,
      birthday: birthday,
    );
  }

  static DateTime? _tryParseDate(String raw) {
    final cleaned = raw.trim();

    // mm/dd/yyyy (most common on PH IDs) or dd-mm-yyyy etc. — when neither
    // part exceeds 12 the format is genuinely ambiguous, so we default to
    // month-first; if only one part *can* be a month (<=12), that one wins
    // regardless of position.
    final numeric = RegExp(r'^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$');
    final numericMatch = numeric.firstMatch(cleaned);
    if (numericMatch != null) {
      var a = int.parse(numericMatch.group(1)!);
      var b = int.parse(numericMatch.group(2)!);
      var year = int.parse(numericMatch.group(3)!);
      if (year < 100) year += year < 30 ? 2000 : 1900;
      int month, day;
      if (a > 12 && b <= 12) {
        month = b;
        day = a;
      } else if (b > 12 && a <= 12) {
        month = a;
        day = b;
      } else {
        month = a; // default: mm/dd/yyyy
        day = b;
      }
      if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
        try {
          return DateTime(year, month, day);
        } catch (_) {
          return null;
        }
      }
      return null;
    }

    // yyyy-mm-dd
    final iso = RegExp(r'^(\d{4})-(\d{1,2})-(\d{1,2})$').firstMatch(cleaned);
    if (iso != null) {
      try {
        return DateTime(
          int.parse(iso.group(1)!),
          int.parse(iso.group(2)!),
          int.parse(iso.group(3)!),
        );
      } catch (_) {
        return null;
      }
    }

    // "Month DD, YYYY" e.g. "January 5, 1998"
    const months = [
      'JANUARY',
      'FEBRUARY',
      'MARCH',
      'APRIL',
      'MAY',
      'JUNE',
      'JULY',
      'AUGUST',
      'SEPTEMBER',
      'OCTOBER',
      'NOVEMBER',
      'DECEMBER',
    ];
    final wordy = RegExp(r'^([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})$')
        .firstMatch(cleaned);
    if (wordy != null) {
      final monthIndex = months.indexOf(wordy.group(1)!.toUpperCase());
      if (monthIndex != -1) {
        try {
          return DateTime(
            int.parse(wordy.group(3)!),
            monthIndex + 1,
            int.parse(wordy.group(2)!),
          );
        } catch (_) {
          return null;
        }
      }
    }

    return null;
  }

  static DateTime? _findAnyDate(List<String> lines) {
    for (final line in lines) {
      final date = _tryParseDate(line);
      if (date != null) return date;
    }
    return null;
  }
}
