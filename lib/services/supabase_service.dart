import 'package:supabase_flutter/supabase_flutter.dart';
import '../utils/supabase_config.dart';

/// Thin wrapper around the Supabase client so the rest of the app never has
/// to think about initialization — just call `SupabaseService.client`.
class SupabaseService {
  SupabaseService._();

  static Future<void> initialize() async {
    await Supabase.initialize(
      url: SupabaseConfig.url,
      publishableKey: SupabaseConfig.publishableKey,
    );
  }

  static SupabaseClient get client => Supabase.instance.client;
}
