import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/utils/license_number_formatter.dart';

void main() {
  final formatter = LicenseNumberFormatter();
  String format(String text) => formatter
      .formatEditUpdate(
        TextEditingValue.empty,
        TextEditingValue(
          text: text,
          selection: TextSelection.collapsed(offset: text.length),
        ),
      )
      .text;

  test('formats lowercase pasted license and limits length', () {
    expect(format('a1234123456'), 'A12-34-123456');
    expect(format('A12-34-123456789'), 'A12-34-123456');
  });
  test('blocks letters after the first character and invalid punctuation', () {
    expect(format('ab12xx34@123456'), 'A12-34-123456');
    expect(format('12345'), '');
  });
  test('supports partial input and insertion with a stable cursor', () {
    expect(format('a12'), 'A12');
    expect(format('a123'), 'A12-3');
    final result = formatter.formatEditUpdate(
      const TextEditingValue(text: 'A12-34-123456'),
      const TextEditingValue(
        text: 'A12-3-123456',
        selection: TextSelection.collapsed(offset: 5),
      ),
    );
    expect(result.text, 'A12-31-23456');
    expect(result.selection.extentOffset, 5);
  });
}
