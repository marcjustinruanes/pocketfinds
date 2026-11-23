import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/utils/friendly_error.dart';

void main() {
  test('actionable location errors are shown while internal errors stay hidden', () {
    const message = 'Location is turned off. Turn on Location, then retry.';
    expect(friendlyError(const UserVisibleException(message)), message);
    expect(friendlyError(Exception('private internal details')),
        'Something went wrong. Please try again.');
  });
}
