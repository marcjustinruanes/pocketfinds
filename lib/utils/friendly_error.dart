import 'package:supabase_flutter/supabase_flutter.dart';

/// Turns an exception into a message safe to show a normal user.
///
/// `AuthException`/`PostgrestException` messages are shown as-is — Supabase
/// Auth's own messages ("Invalid login credentials") and the messages our
/// own RPC functions `RAISE EXCEPTION` with ("This delivery is no longer
/// available.", "That username is already taken.") are already
/// human-written and safe to display. Anything else (network failures,
/// unexpected Dart errors) falls back to a generic message instead of
/// exposing `e.toString()` internals.
String friendlyError(Object error) {
  if (error is AuthException) return error.message;
  if (error is PostgrestException) return error.message;
  return 'Something went wrong. Please try again.';
}
