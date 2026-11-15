import 'package:intl/intl.dart';

/// Formats a number as Philippine peso, matching the website's
/// `₱{{ number_format($p['price']) }}` (whole pesos, thousands separator).
String formatPeso(num amount) {
  final formatter = NumberFormat('#,##0', 'en_US');
  return '₱${formatter.format(amount)}';
}
