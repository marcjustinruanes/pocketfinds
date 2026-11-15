import 'services/incoming_navigation.dart';
import 'services/push_service.dart';

import 'dart:async';

import 'package:flutter/material.dart';

import 'services/backend_service.dart';
import 'services/auth_service.dart';
import 'theme/app_colors.dart';
import 'theme/app_theme.dart';
import 'theme/theme_controller.dart';
import 'utils/session_navigation.dart';
import 'utils/backend_config.dart';
import 'widgets/splash_intro.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ThemeController.load();
  if (BackendConfig.isConfigured) {
    await BackendService.initialize();
  }
  incomingNavigation.start();
  await pushService.initialize();
  runApp(const PocketFindsApp());
}

/// Every screen the app can land on is reached by navigating away from a
/// single splash screen — this key lets the app-wide auth listener below
/// drive that navigation from outside any particular screen's widget tree.
final navigatorKey = GlobalKey<NavigatorState>();
final incomingNavigation = IncomingNavigation(navigatorKey);
final pushService = PushService(
  onOpen: incomingNavigation.open,
  onForeground: (title, body) {
    final context = navigatorKey.currentContext;
    if (context != null && context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$title: $body')));
    }
  },
);

class PocketFindsApp extends StatefulWidget {
  const PocketFindsApp({super.key});

  @override
  State<PocketFindsApp> createState() => _PocketFindsAppState();
}

class _PocketFindsAppState extends State<PocketFindsApp> {
  StreamSubscription<AuthState>? _authSubscription;

  // Marks when the splash's intro animation actually started, so the first
  // auth resolution (cold start) can wait out whatever's left of it below —
  // every later resolution (sign-in/sign-out while already past the splash)
  // has long since cleared this and never waits.
  final _launchedAt = DateTime.now();

  @override
  void initState() {
    super.initState();
    if (BackendConfig.isConfigured) {
      // One listener drives ALL auth-driven navigation — cold start
      // (the local client's initial auth event tells us whether a
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
    // Never cuts the splash's category-icons-to-logo reveal short: on a
    // fast network resolveSession() can return well before the animation
    // has finished, so hold here for whatever's left of its total runtime.
    // A slow network just means the held, finished title doubles as the
    // loading state — no separate spinner needed.
    final remaining =
        SplashIntro.totalDuration - DateTime.now().difference(_launchedAt);
    if (remaining > Duration.zero) await Future.delayed(remaining);
    final context = navigatorKey.currentContext;
    if (context == null || !context.mounted) return;
    await navigateForSession(context, resolution);
    if (resolution.user?.isApproved == true &&
        resolution.user?.notifyEnabled == true) {
      await pushService.enable();
    }
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => incomingNavigation.flush(),
    );
  }

  @override
  void dispose() {
    _authSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Rebuilds the whole app (and, via it, every screen's next build) the
    // moment `ThemeController.isDarkNotifier` flips — the real dark/light
    // switch (Settings), not the OS's system setting.
    return ValueListenableBuilder<bool>(
      valueListenable: ThemeController.isDarkNotifier,
      builder: (context, isDark, _) {
        return MaterialApp(
          navigatorKey: navigatorKey,
          title: 'PocketFinds',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.current,
          home: BackendConfig.isConfigured
              ? const _SplashScreen()
              : const _BackendSetupNeededScreen(),
        );
      },
    );
  }
}

/// Shown while resolving whether a session exists at launch — the auth
/// listener above replaces this with Guest Home or the signed-in user's
/// home once [SplashIntro]'s reveal has run its course (see
/// `_handleAuthChange`).
class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) => const SplashIntro();
}

/// Shown instead of a confusing network error when
/// `lib/utils/backend_config.dart` hasn't been given a real anon key yet.
class _BackendSetupNeededScreen extends StatelessWidget {
  const _BackendSetupNeededScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.key_off, size: 48, color: AppColors.pink),
              const SizedBox(height: 16),
              const Text(
                'Local API setup needed',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                'Start scripts/start-local-api.ps1 and configure your '
                'local API address using API_BASE_URL, '
                'then restart the app.',
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
