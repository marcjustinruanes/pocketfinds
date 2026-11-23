import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../services/conversation_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/chat/chat_input_bar.dart';
import '../../widgets/chat/message_bubble.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/message_icon.dart';
import 'product_detail_screen.dart';
import 'report_content_screen.dart';

class ConversationDetailScreen extends StatefulWidget {
  final ConversationSummary conversation;
  final ConversationService service;

  /// Auto-sent once on open (e.g. "Hi, I'm interested in `product`" from
  /// Product Detail's "Chat Now") — real, tied to [initialProductId] via
  /// `send_message`'s own `p_product_id` column, not just plain text.
  final String? initialMessage;
  final String? initialProductId;

  /// The signed-in caller, only used to open a product card tapped inside
  /// this conversation (via `ProductDetailScreen`) as a real signed-in
  /// buyer rather than a guest. Null is fine — the product card just won't
  /// be tappable in the few entry points that don't have a user in scope.
  final AppUser? user;

  const ConversationDetailScreen({
    super.key,
    required this.conversation,
    this.service = const EmptyConversationService(),
    this.initialMessage,
    this.initialProductId,
    this.user,
  });

  @override
  State<ConversationDetailScreen> createState() =>
      _ConversationDetailScreenState();
}

class _ConversationDetailScreenState extends State<ConversationDetailScreen> {
  static const _pollInterval = Duration(seconds: 4);

  List<ChatMessage>? _messages;
  Object? _error;
  DateTime? _presence;
  ChatMessage? _replyingTo;
  Timer? _pollTimer;
  bool _sending = false;
  bool _muted = false;
  bool _blocked = false;

  String get _muteKey => 'muted_conversation_${widget.conversation.id}';

  @override
  void initState() {
    super.initState();
    _load();
    _loadMuted();
    _loadBlocked();
    widget.service.touchPresence();
    _pollTimer = Timer.periodic(_pollInterval, (_) => _poll());
    if (widget.initialMessage != null) _sendInitialMessage();
  }

  Future<void> _sendInitialMessage() async {
    try {
      await widget.service.sendMessage(
        widget.conversation.id,
        widget.initialMessage!,
        productId: widget.initialProductId,
      );
      await _load();
    } catch (_) {
      // Non-fatal — the thread still opens; the user can just type instead.
    }
  }

  Future<void> _loadBlocked() async {
    try {
      final blocked = await widget.service.isUserBlocked(
        widget.conversation.id,
      );
      if (!mounted) return;
      setState(() => _blocked = blocked);
    } catch (_) {}
  }

  Future<void> _loadMuted() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (!mounted) return;
      setState(() => _muted = prefs.getBool(_muteKey) ?? false);
    } catch (_) {
      // Non-fatal — mute is a local convenience, not load-bearing.
    }
  }

  Future<void> _toggleMute() async {
    final next = !_muted;
    setState(() => _muted = next);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_muteKey, next);
    } catch (_) {}
    if (!mounted) return;
    AppSnackBar.info(
      context,
      next ? 'Muted this conversation.' : 'Unmuted this conversation.',
    );
  }

  void _viewProfile() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: AppColors.pinkGradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                shape: BoxShape.circle,
                boxShadow: AppShadows.sm,
              ),
              child: Text(
                widget.conversation.participantAvatarInitial,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 26,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              widget.conversation.participantName,
              style: AppTypography.sectionTitle,
            ),
            if (widget.conversation.roleLabel != null) ...[
              const SizedBox(height: 2),
              Text(
                widget.conversation.roleLabel!,
                style: AppTypography.caption,
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
          ],
        ),
      ),
    );
  }

  Future<void> _toggleBlock() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_blocked ? 'Unblock User?' : 'Block User?'),
        content: Text(
          _blocked ? 'You will be able to message each other again.' : 'They will no longer be able to message you, and you will not be able to message them.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              _blocked ? 'Unblock' : 'Block',
              style: TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      if (_blocked) {
        await widget.service.unblockUser(widget.conversation.id);
      } else {
        await widget.service.blockUser(widget.conversation.id);
      }
      if (!mounted) return;
      setState(() => _blocked = !_blocked);
      AppSnackBar.success(
        context,
        _blocked ? 'User blocked.' : 'User unblocked.',
      );
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not update: ${friendlyError(e)}');
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final messages = await widget.service.fetchMessages(
        widget.conversation.id,
      );
      final presence = await widget.service.fetchPresence(
        widget.conversation.id,
      );
      if (!mounted) return;
      setState(() {
        _messages = messages;
        _presence = presence;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  /// Quiet background refresh — keeps the currently-loaded list if this
  /// fails (a transient network hiccup shouldn't blank the screen).
  Future<void> _poll() async {
    if (!mounted ||
        WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    widget.service.touchPresence();
    try {
      final messages = await widget.service.fetchMessages(
        widget.conversation.id,
      );
      final presence = await widget.service.fetchPresence(
        widget.conversation.id,
      );
      if (!mounted) return;
      setState(() {
        _messages = messages;
        _presence = presence;
        _error = null;
      });
    } catch (_) {
      // Non-fatal — try again on the next tick.
    }
  }

  Future<void> _send(String text, File? image) async {
    if (_sending) return;
    setState(() => _sending = true);
    try {
      ChatAttachmentUpload? attachment;
      if (image != null) {
        attachment = await widget.service.uploadChatImage(image);
      }
      await widget.service.sendMessage(
        widget.conversation.id,
        text,
        replyToId: _replyingTo?.id,
        attachment: attachment,
      );
      setState(() => _replyingTo = null);
      await _load();
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not send: ${friendlyError(e)}');
      rethrow;
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _react(ChatMessage message, String emoji) async {
    try {
      await widget.service.reactToMessage(message.id, emoji);
      await _poll();
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not react: ${friendlyError(e)}');
    }
  }

  void _reply(ChatMessage message) => setState(() => _replyingTo = message);

  String? _presenceLabel() {
    final at = _presence;
    if (at == null) return null;
    final diff = DateTime.now().difference(at);
    if (diff.inSeconds < 90) return 'Online';
    if (diff.inMinutes < 60) return 'Last seen ${diff.inMinutes}m ago';
    if (diff.inHours < 24) return 'Last seen ${diff.inHours}h ago';
    return 'Last seen ${diff.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final presenceLabel = _presenceLabel();
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.conversation.participantName),
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) {
              switch (value) {
                case 'profile':
                  _viewProfile();
                case 'mute':
                  _toggleMute();
                case 'block':
                  _toggleBlock();
              }
            },
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'profile',
                child: Text('View Profile'),
              ),
              PopupMenuItem(
                value: 'mute',
                child: Text(_muted ? 'Unmute' : 'Mute'),
              ),
              PopupMenuItem(
                value: 'block',
                child: Text(
                  _blocked ? 'Unblock User' : 'Block User',
                  style: TextStyle(color: AppColors.danger),
                ),
              ),
            ],
          ),
        ],
        bottom: (widget.conversation.roleLabel != null || presenceLabel != null)
            ? PreferredSize(
                preferredSize: const Size.fromHeight(20),
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    [
                      if (widget.conversation.roleLabel != null)
                        widget.conversation.roleLabel!,
                      ?presenceLabel,
                    ].join(' · '),
                    style: TextStyle(
                      fontSize: 11.5,
                      color: presenceLabel == 'Online'
                          ? AppColors.success
                          : AppColors.textMuted,
                      fontWeight: presenceLabel == 'Online'
                          ? FontWeight.w700
                          : FontWeight.w400,
                    ),
                  ),
                ),
              )
            : null,
      ),
      body: Column(
        children: [
          Expanded(
            child: Builder(
              builder: (context) {
                if (_messages == null && _error == null) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (_error != null && _messages == null) {
                  return ErrorState(
                    message: 'Could not load messages.',
                    error: friendlyError(_error!),
                  );
                }
                final messages = _messages!;
                if (messages.isEmpty) {
                  return EmptyState(
                    iconWidget: MessageIcon(size: 32, color: AppColors.pink),
                    title: 'No messages yet',
                    subtitle: 'Say hello to get the conversation started.',
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: messages.length,
                  itemBuilder: (context, index) => MessageBubble(
                    message: messages[index],
                    myUserId: widget.service.myUserIdString,
                    onReply: _reply,
                    onReact: _react,
                    onReport: widget.user?.accountType == 'buyer'
                        ? (message) => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) =>
                                  ReportContentScreen(messageId: message.id),
                            ),
                          )
                        : null,
                    onTapProduct: (product) => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ProductDetailScreen(
                          user: widget.user,
                          productId: product.id,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          if (widget.conversation.isActive)
            ChatInputBar(
              onSend: _send,
              replyingTo: _replyingTo,
              onCancelReply: () => setState(() => _replyingTo = null),
            )
          else
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(AppSpacing.lg),
              decoration: BoxDecoration(
                color: AppColors.white,
                boxShadow: AppShadows.md,
              ),
              child: SafeArea(
                top: false,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.lock_outline,
                      size: 16,
                      color: AppColors.textMuted,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'This chat ended after delivery.',
                      style: AppTypography.caption,
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
      backgroundColor: AppColors.pageBackground,
    );
  }
}
