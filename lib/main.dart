import 'dart:async';
import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'services/auth_service.dart';
import 'services/supabase_service.dart';
import 'theme/app_colors.dart';
import 'theme/app_theme.dart';
import 'utils/session_navigation.dart';
import 'utils/supabase_config.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (SupabaseConfig.isConfigured) {
    await SupabaseService.initialize();
  }
  runApp(const PocketFindsApp());
}

/// Every screen the app can land on is reached by navigating away from a
/// single splash screen — this key lets the app-wide auth listener below
/// drive that navigation from outside any particular screen's widget tree.
final navigatorKey = GlobalKey<NavigatorState>();

class PocketFindsApp extends StatefulWidget {
  const PocketFindsApp({super.key});

  @override
  State<PocketFindsApp> createState() => _PocketFindsAppState();
}

class _PocketFindsAppState extends State<PocketFindsApp> {
  StreamSubscription<AuthState>? _authSubscription;

  @override
  void initState() {
    super.initState();
    if (SupabaseConfig.isConfigured) {
      // One listener drives ALL auth-driven navigation — cold start
      // (Supabase's very first `initialSession` event tells us whether a
      // persisted session exists), sign-in, sign-out, and session
      // expiration all flow through here, so there's exactly one place
      // deciding "what screen should be showing right now".
      _authSubscription = AuthService().onAuthStateChange.listen((_) {
        if (!AuthService.suppressGlobalNavigation) _handleAuthChange();
      });
    }
  }

  Future<void> _handleAuthChange() async {
    final resolution = await AuthService().resolveSession();
    final context = navigatorKey.currentContext;
    if (context == null || !context.mounted) return;
    await navigateForSession(context, resolution);
  }

  @override
  void dispose() {
    _authSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: navigatorKey,
      title: 'PocketFinds',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      home: SupabaseConfig.isConfigured
          ? const _SplashScreen()
          : const _SupabaseSetupNeededScreen(),
    );
  }
}

/// Shown only for the brief moment it takes to resolve whether a session
/// exists at launch — the auth listener above replaces this almost
/// immediately with Guest Home or the signed-in user's home.
class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: Center(child: CircularProgressIndicator(color: AppColors.pink)),
    );
  }
}

/// Shown instead of a confusing network error when
/// `lib/utils/supabase_config.dart` hasn't been given a real anon key yet.
class _SupabaseSetupNeededScreen extends StatelessWidget {
  const _SupabaseSetupNeededScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.key_off, size: 48, color: AppColors.pink),
              const SizedBox(height: 16),
              const Text(
                'Supabase key needed',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              const Text(
                'Open lib/utils/supabase_config.dart and paste your '
                'Supabase project\'s anon/public (publishable) API key into '
                'publishableKey, then restart the app.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textMuted),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
