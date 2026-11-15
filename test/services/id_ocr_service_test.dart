// Unit tests for IdOcrService.parse() — the pure, plugin-free text-parsing
// heuristic (no camera/ML Kit plugin dependency, so this runs anywhere).

import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/services/id_ocr_service.dart';

void main() {
  group('IdOcrService.parse', () {
    test('parses a clean label:value layout (e.g. UMID-style)', () {
      const text = '''
UNIFIED MULTI-PURPOSE ID
Last Name: DELA CRUZ
Given Name: JUAN
Middle Name: SANTOS
Sex: M
Date of Birth: 01/05/1998
''';
      final result = IdOcrService.parse(text);

      expect(result.lastName, 'DELA CRUZ');
      expect(result.givenNames, 'JUAN');
      expect(result.middleName, 'SANTOS');
      expect(result.sex, 'male');
      expect(result.birthday, DateTime(1998, 1, 5));
      expect(result.isConfident, isTrue);
    });

    test('parses a label-on-its-own-line layout (label, then value below)', () {
      const text = '''
Surname
DELA CRUZ
Given Names
JUAN
Birthdate
1998-01-05
''';
      final result = IdOcrService.parse(text);

      expect(result.lastName, 'DELA CRUZ');
      expect(result.givenNames, 'JUAN');
      expect(result.birthday, DateTime(1998, 1, 5));
      expect(result.isConfident, isTrue);
    });

    test('parses a wordy date format', () {
      const text = '''
Last Name: DELA CRUZ
Given Name: JUAN
Date of Birth: January 5, 1998
''';
      final result = IdOcrService.parse(text);
      expect(result.birthday, DateTime(1998, 1, 5));
    });

    test('is not confident when required fields are missing', () {
      const text = '''
REPUBLIC OF THE PHILIPPINES
SOME UNRECOGNIZABLE GARBLED TEXT
''';
      final result = IdOcrService.parse(text);

      expect(result.lastName, isNull);
      expect(result.givenNames, isNull);
      expect(result.isConfident, isFalse);
    });

    test('is not confident when birthday is missing even if names parsed', () {
      const text = '''
Last Name: DELA CRUZ
Given Name: JUAN
''';
      final result = IdOcrService.parse(text);

      expect(result.lastName, 'DELA CRUZ');
      expect(result.givenNames, 'JUAN');
      expect(result.birthday, isNull);
      expect(result.isConfident, isFalse);
    });

    test('sex is a bonus field — never required for confidence', () {
      const text = '''
Last Name: DELA CRUZ
Given Name: JUAN
Date of Birth: 01/05/1998
''';
      final result = IdOcrService.parse(text);

      expect(result.sex, isNull);
      expect(result.isConfident, isTrue);
    });

    test('empty input parses to an unconfident empty result', () {
      final result = IdOcrService.parse('');
      expect(result.isConfident, isFalse);
      expect(result, isA<IdOcrResult>());
    });
  });
}
