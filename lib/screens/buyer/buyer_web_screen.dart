import 'dart:async';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

import '../../models/app_user.dart';
import '../../services/auth_service.dart';
import '../../services/backend_service.dart';
import '../../services/buyer_web_service.dart';
import '../../services/order_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/theme_controller.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import 'gcash_payment_screen.dart';
import 'orders_tab.dart';
import 'order_detail_screen.dart';
import 'settings_screen.dart';

/// Actual buyer Blade pages, CSS, JavaScript and authenticated Laravel actions.
/// App settings and historical app-payment records retain native pages.
class BuyerWebScreen extends StatefulWidget {
  final AppUser user;
  final String initialPath;
  const BuyerWebScreen({
    super.key,
    required this.user,
    this.initialPath = '/buyer/dashboard',
  });
  @override
  State<BuyerWebScreen> createState() => _BuyerWebScreenState();
}

class _BuyerWebScreenState extends State<BuyerWebScreen> {
  static bool _assetsRefreshed = false;
  final _service = BuyerWebService();
  late WebViewController _controller;
  late AppUser _user = widget.user;
  String? _nonce, _error;
  int _progress = 0;
  bool _ready = false,
      _nativeBusy = false,
      _connecting = false,
      _exiting = false;

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  Future<void> _initialize() async {
    try {
      _controller = WebViewController();
      if (!_assetsRefreshed) {
        await _controller.clearCache();
        _assetsRefreshed = true;
      }
      await _controller.setJavaScriptMode(JavaScriptMode.unrestricted);
      await _controller.setBackgroundColor(AppColors.pageBackground);
      await _controller.addJavaScriptChannel(
        'PocketFindsMobile',
        onMessageReceived: (message) => _message(message.message),
      );
      await _controller.setOnJavaScriptAlertDialog((request) async {
        if (!mounted) return;
        await showDialog<void>(
          context: context,
          builder: (c) => AlertDialog(
            content: Text(request.message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(c),
                child: const Text('OK'),
              ),
            ],
          ),
        );
      });
      await _controller.setOnJavaScriptConfirmDialog((request) async {
        if (!mounted) return false;
        return await showDialog<bool>(
              context: context,
              builder: (c) => AlertDialog(
                content: Text(request.message),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(c, false),
                    child: const Text('Cancel'),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.pop(c, true),
                    child: const Text('Confirm'),
                  ),
                ],
              ),
            ) ??
            false;
      });
      await _controller.setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: _navigate,
          onProgress: (value) {
            if (mounted) setState(() => _progress = value);
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _progress = 100);
            unawaited(_applyTheme());
          },
          onWebResourceError: (error) {
            if (mounted && error.isForMainFrame == true) {
              setState(
                () => _error = 'Could not load this page. Check your connection and retry.',
              );
            }
          },
          onHttpError: (error) {
            if (mounted && error.response?.statusCode == 401) {
              setState(
                () => _error =
                    'Your buyer web session expired. Reconnect to continue.',
              );
            }
          },
        ),
      );
      if (_controller.platform is AndroidWebViewController) {
        final android = _controller.platform as AndroidWebViewController;
        await android.setOnShowFileSelector(_pickFile);
        await android.setMediaPlaybackRequiresUserGesture(true);
        await android.setGeolocationPermissionsPromptCallbacks(
          onShowPrompt: (request) async {
            if (!_service.trusted(request.origin)) {
              return const GeolocationPermissionsResponse(
                allow: false,
                retain: false,
              );
            }
            var permission = await Geolocator.checkPermission();
            if (permission == LocationPermission.denied) {
              permission = await Geolocator.requestPermission();
            }
            return GeolocationPermissionsResponse(
              allow:
                  permission == LocationPermission.always ||
                  permission == LocationPermission.whileInUse,
              retain: false,
            );
          },
        );
      }
      if (!mounted) return;
      setState(() => _ready = true);
      await _connect();
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  Future<void> _connect() async {
    if (_connecting) return;
    _connecting = true;
    if (mounted) {
      setState(() {
        _error = null;
        _progress = 0;
      });
    }
    try {
      final session = await _service.open(widget.initialPath);
      _nonce = session.nonce;
      await _controller.loadRequest(session.uri);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      _connecting = false;
    }
  }

  Future<NavigationDecision> _navigate(NavigationRequest request) async {
    final uri = Uri.tryParse(request.url);
    if (uri == null) return NavigationDecision.prevent;
    if (_service.trusted(request.url)) {
      if (request.isMainFrame && uri.path.startsWith('/message-media/')) {
        await _openAttachment(uri);
        return NavigationDecision.prevent;
      }
      if (uri.path == '/login' || uri.path == '/logout') {
        unawaited(_exit());
        return NavigationDecision.prevent;
      }
      return _service.buyerPath(uri.path)
          ? NavigationDecision.navigate
          : NavigationDecision.prevent;
    }
    if (['https', 'http', 'mailto', 'tel'].contains(uri.scheme)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
    return NavigationDecision.prevent;
  }

  Future<List<String>> _pickFile(FileSelectorParams params) async {
    try {
      final current = await _controller.currentUrl();
      if (current == null || !_service.trusted(current)) return [];
      final accepts = params.acceptTypes
          .expand((type) => type.split(','))
          .map((type) => type.trim().toLowerCase())
          .toList();
      if (params.isCaptureEnabled &&
          accepts.any((type) => type.startsWith('image/'))) {
        final photo = await ImagePicker().pickImage(source: ImageSource.camera);
        return photo == null ? [] : [Uri.file(photo.path).toString()];
      }
      if (params.isCaptureEnabled &&
          accepts.any((type) => type.startsWith('video/'))) {
        final video = await ImagePicker().pickVideo(source: ImageSource.camera);
        return video == null ? [] : [Uri.file(video.path).toString()];
      }
      const extensions = {
        'image/jpeg': ['jpg', 'jpeg'],
        'image/png': ['png'],
        'image/gif': ['gif'],
        'image/webp': ['webp'],
        'image/*': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'bmp'],
        'video/mp4': ['mp4'],
        'video/quicktime': ['mov'],
        'video/*': ['mp4', 'mov', 'avi', 'webm', 'm4v'],
        'application/pdf': ['pdf'],
      };
      final allowed = <String>{};
      for (final accept in accepts) {
        if (accept.startsWith('.')) {
          allowed.add(accept.substring(1));
        } else {
          allowed.addAll(extensions[accept] ?? []);
        }
      }
      final type = allowed.isEmpty ? FileType.any : FileType.custom;
      final filters = allowed.isEmpty ? null : allowed.toList();
      final files = params.mode == FileSelectorMode.openMultiple
          ? await FilePicker.pickFiles(type: type, allowedExtensions: filters)
          : [await FilePicker.pickFile(type: type, allowedExtensions: filters)]
                .whereType<PlatformFile>()
                .toList();
      return files
          .map((file) => file.path)
          .whereType<String>()
          .map((path) => Uri.file(path).toString())
          .toList();
    } catch (error) {
      if (mounted) AppSnackBar.error(context, friendlyError(error));
      return [];
    }
  }

  Future<void> _message(String raw) async {
    if (_nativeBusy || _nonce == null) return;
    final current = await _controller.currentUrl();
    if (!mounted || current == null) return;
    final data = _service.message(raw, nonce: _nonce!, currentUrl: current);
    if (data == null) return;
    _nativeBusy = true;
    try {
      switch (data['action']) {
        case 'google-email':
          await AuthService().signInWithGoogle(verifyAccountEmail: true);
          final updated = await AuthService().fetchMyProfile();
          if (updated != null) _user = updated;
          if (mounted) await _replace('/buyer/account#account-security');
        case 'settings':
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => SettingsScreen(user: _user)),
          );
          final user = await AuthService().fetchMyProfile();
          if (user != null) _user = user;
          if (mounted) await _replace('/buyer/account');
        case 'payments':
          // Historical app payment records remain accessible without rewriting them.
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) =>
                  OrdersTab(user: _user, initialStatus: 'pending_payment'),
            ),
          );
          if (mounted) await _replace('/buyer/orders');
        case 'payment':
          final id = '${data['order_id']}';
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => GcashPaymentScreen(orderIds: [id]),
            ),
          );
          if (mounted) await _controller.reload();
        case 'order':
          final order = await OrderService().findOrder(
            _user.id,
            '${data['order_id']}',
          );
          if (order != null && mounted) {
            await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) =>
                    OrderDetailScreen(order: order, myUserId: _user.id),
              ),
            );
          }
        case 'document':
          final uri = Uri.parse('${data['url']}');
          await _openAttachment(uri);
      }
    } catch (error) {
      if (mounted) AppSnackBar.error(context, friendlyError(error));
    } finally {
      _nativeBusy = false;
    }
  }

  Future<void> _replace(String path) => _controller.runJavaScript(
    'location.replace(${jsonEncode(_service.page(path).toString())})',
  );

  Future<void> _openAttachment(Uri uri) async {
    if (!_service.trusted(uri.toString()) ||
        !uri.path.startsWith('/message-media/')) {
      throw const FormatException('This attachment is unavailable.');
    }
    final path = uri.pathSegments.skip(1).join('/');
    final url = BackendService.client.storage
        .from('messages')
        .getPublicUrl(path);
    if (!await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.externalApplication,
    )) {
      throw const FormatException('Could not open this attachment.');
    }
  }

  Future<void> _applyTheme() async {
    final current = await _controller.currentUrl();
    if (current == null || !_service.trusted(current)) return;
    // Light mode uses the website CSS verbatim; dark mode is the retained app preference.
    const css =
        ':root,body.buyer-pages{color-scheme:dark;--page:#121013;--paper:#121013;--white:#1c1a1f;--surface:#1c1a1f;--text:#f4f4f5;--muted:#a1a1aa;--border:#3f3f46;--pink-soft:#3a2430;--pink-line:#5c3a4c}body.buyer-pages{background:#121013!important}.logo-pocket{color:#f4f4f5!important}input,select,textarea{background:var(--white);color:var(--text)}';
    try {
      await _controller.runJavaScript(
        "document.getElementById('mobile-dark-theme')?.remove();${ThemeController.isDark ? "(()=>{const s=document.createElement('style');s.id='mobile-dark-theme';s.textContent=${jsonEncode(css)};document.head.append(s)})()" : ''}",
      );
    } catch (_) {
      /* Navigation may have replaced the document. */
    }
  }

  Future<void> _exit() async {
    if (_exiting) return;
    _exiting = true;
    try {
      await WebViewCookieManager().clearCookies();
      await AuthService().signOut();
    } catch (_) {
      await BackendService.client.auth.clearSession();
    }
  }

  Future<void> _back() async {
    if (_ready && await _controller.canGoBack()) {
      await _controller.goBack();
      return;
    }
    if (!mounted) return;
    if (Navigator.canPop(context)) {
      Navigator.pop(context);
    } else {
      await SystemNavigator.pop();
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) _back();
    },
    child: Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            if (_ready) WebViewWidget(controller: _controller),
            if (_error == null && (!_ready || _progress < 100))
              const Align(
                alignment: Alignment.topCenter,
                child: LinearProgressIndicator(),
              ),
            if (_error != null)
              ColoredBox(
                color: AppColors.pageBackground,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.wifi_off_outlined, size: 36),
                        const SizedBox(height: 16),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _ready ? _connect : _initialize,
                          child: const Text('Reconnect'),
                        ),
                        TextButton(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => SettingsScreen(user: _user),
                            ),
                          ),
                          child: const Text('App Settings'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    ),
  );
}
