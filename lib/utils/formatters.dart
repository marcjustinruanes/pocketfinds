import 'package:intl/intl.dart';

/// Formats a number as Philippine peso, matching the website's
/// `₱{{ number_format($p['price']) }}` (whole pesos, thousands separator).
String formatPeso(num amount) {
  final formatter = NumberFormat('#,##0.00', 'en_US');
  return '₱${formatter.format(amount)}';
}

/// Formats a time as "5:30 PM" — used for task pickup/delivery deadlines.
String formatTime(DateTime time) {
  return DateFormat('h:mm a').format(time);
}

/// Capitalizes the first letter of every word — used to turn raw
/// `status.replaceAll('_', ' ')` strings ("out for delivery") into a
/// display-ready label ("Out For Delivery") without changing the
/// underlying status value anywhere.
String titleCase(String value) {
  return value
      .split(' ')
      .map(
        (word) => word.isEmpty
            ? word
            : '${word[0].toUpperCase()}${word.substring(1)}',
      )
      .join(' ');
}
