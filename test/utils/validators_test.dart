import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/utils/validators.dart';

void main() {
  group('Validators.otpCode', () {
    test('accepts a 6-digit code', () {
      expect(Validators.otpCode('123456'), isNull);
    });

    test('trims surrounding whitespace before checking', () {
      expect(Validators.otpCode('  123456  '), isNull);
    });

    test('rejects null/empty', () {
      expect(Validators.otpCode(null), isNotNull);
      expect(Validators.otpCode(''), isNotNull);
    });

    test('rejects the wrong length', () {
      expect(Validators.otpCode('12345'), isNotNull);
      expect(Validators.otpCode('1234567'), isNotNull);
    });

    test('rejects non-digit characters', () {
      expect(Validators.otpCode('12345a'), isNotNull);
    });
  });
}
