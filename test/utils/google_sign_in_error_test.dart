import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/utils/google_sign_in_error.dart';

void main() {
  test(
    'native account failures explain device recovery and email fallback',
    () {
      final message = googleSignInErrorMessage('unknownError');
      expect(message, contains('device Settings'));
      expect(message, contains('internet'));
      expect(message, contains('email with a code'));
    },
  );
  test('configuration failures do not suggest resetting the user account', () {
    expect(
      googleSignInErrorMessage('clientConfigurationError'),
      contains('not configured'),
    );
    expect(
      googleSignInErrorMessage('providerConfigurationError'),
      contains('not configured'),
    );
    expect(googleSignInErrorMessage('interrupted'), contains('interrupted'));
  });
}
