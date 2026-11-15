/// Field validators mirroring the rules in `RegisterController::store()`
/// and the login form, so Flutter rejects bad input the same way the
/// website does.
class Validators {
  Validators._();

  static String? required(String? value, {String label = 'This field'}) {
    if (value == null || value.trim().isEmpty) return '$label is required.';
    return null;
  }

  static String? email(String? value) {
    if (value == null || value.trim().isEmpty) return 'Email is required.';
    final pattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
    if (!pattern.hasMatch(value.trim())) return 'Enter a valid email address.';
    return null;
  }

  /// Laravel requires `09XXXXXXXXX` (11 digits, PH mobile format).
  static String? contactNo(String? value) {
    if (value == null || value.trim().isEmpty) return 'Contact number is required.';
    final pattern = RegExp(r'^09\d{9}$');
    if (!pattern.hasMatch(value.trim())) {
      return 'Enter a valid PH mobile number (e.g. 09171234567).';
    }
    return null;
  }

  /// Laravel: `min:8|alpha_dash` (letters, numbers, dashes, underscores).
  static String? username(String? value) {
    if (value == null || value.trim().isEmpty) return 'Username is required.';
    final trimmed = value.trim();
    if (trimmed.length < 8) return 'Username must be at least 8 characters.';
    final pattern = RegExp(r'^[a-zA-Z0-9_-]+$');
    if (!pattern.hasMatch(trimmed)) {
      return 'Username can only contain letters, numbers, dashes, and underscores.';
    }
    return null;
  }

  /// Laravel: `min:8` for new passwords.
  static String? password(String? value) {
    if (value == null || value.isEmpty) return 'Password is required.';
    if (value.length < 8) return 'Password must be at least 8 characters.';
    return null;
  }

  static String? confirmPassword(String? value, String original) {
    if (value == null || value.isEmpty) return 'Please confirm your password.';
    if (value != original) return 'Passwords do not match.';
    return null;
  }
}
