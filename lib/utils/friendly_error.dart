import 'dart:async';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../services/backend_service.dart';

/// A message written for the user, safe to display without exposing internals.
class UserVisibleException implements Exception {
  final String message;
  const UserVisibleException(this.message);
}

String friendlyError(Object error) {
  if (error is UserVisibleException) return error.message;
  if (error is SocketException || error is http.ClientException) {
    return 'No connection. Check your internet and the local API, then retry. Your changes have not been confirmed.';
  }
  if (error is TimeoutException) {
    return 'The connection timed out. Refresh to check whether it saved, then retry safely.';
  }
  if (error is AuthException) return error.message;
  if (error is DatabaseException) return error.message;
  return 'Something went wrong. Please try again.';
}
