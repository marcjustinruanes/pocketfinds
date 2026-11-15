import 'package:flutter/services.dart';

/// Philippine license: one letter, two digits, two digits, six digits.
class LicenseNumberFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final source = newValue.text.toUpperCase();
    var raw = '';
    var beforeCursor = 0;
    for (var i = 0; i < source.length; i++) {
      final character = source[i];
      final valid = raw.isEmpty
          ? RegExp(r'[A-Z]').hasMatch(character)
          : RegExp(r'[0-9]').hasMatch(character);
      if (valid && raw.length < 11) {
        raw += character;
        if (i < newValue.selection.extentOffset) beforeCursor++;
      }
    }
    String formatted(String value) => value.length <= 3
        ? value
        : value.length <= 5
        ? '${value.substring(0, 3)}-${value.substring(3)}'
        : '${value.substring(0, 3)}-${value.substring(3, 5)}-${value.substring(5)}';
    final text = formatted(raw);
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(
        offset: formatted(raw.substring(0, beforeCursor)).length,
      ),
    );
  }
}
