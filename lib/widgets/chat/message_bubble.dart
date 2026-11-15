import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/chat_ui.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import 'full_image_viewer.dart';

const _quickReactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
final _urlPattern = RegExp(r'((?:https?:\/\/)[^\s]+)', caseSensitive: false);

class MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final String myUserId;
  final void Function(ChatMessage message) onReply;
  final void Function(ChatMessage message, String emoji) onReact;
  final void Function(ChatProductPreview product)? onTapProduct;
  final void Function(ChatMessage message)? onReport;

  const MessageBubble({
    super.key,
    required this.message,
    required this.myUserId,
    required this.onReply,
    required this.onReact,
    this.onTapProduct,
    this.onReport,
  });

  void _showActions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: _quickReactions.map((emoji) {
                    return GestureDetector(
                      onTap: () {
                        Navigator.of(sheetContext).pop();
                        onReact(message, emoji);
                      },
                      child: Text(emoji, style: const TextStyle(fontSize: 26)),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 8),
                if (onReport != null)
                  ListTile(
                    leading: const Icon(Icons.flag_outlined),
                    title: const Text('Report message'),
                    onTap: () {
                      Navigator.of(sheetContext).pop();
                      onReport!(message);
                    },
                  ),
                ListTile(
                  leading: Icon(Icons.reply, color: AppColors.pink),
                  title: const Text('Reply'),
                  onTap: () {
                    Navigator.of(sheetContext).pop();
                    onReply(message);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final mine = message.isMine;

    final counts = <String, int>{};
    String? myEmoji;
    for (final r in message.reactions) {
      counts[r.emoji] = (counts[r.emoji] ?? 0) + 1;
      if (r.userId == myUserId) myEmoji = r.emoji;
    }

    final bubbleColumn = GestureDetector(
      onLongPress: () => _showActions(context),
      onDoubleTap: () => onReact(message, '❤️'),
      child: Column(
        crossAxisAlignment: mine
            ? CrossAxisAlignment.end
            : CrossAxisAlignment.start,
        children: [
          Container(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.72,
            ),
            margin: const EdgeInsets.only(top: 3),
            padding:
                message.imageUrl != null ||
                    (message.product != null && message.text.isEmpty)
                ? const EdgeInsets.all(4)
                : const EdgeInsets.symmetric(
                    horizontal: AppSpacing.md,
                    vertical: 10,
                  ),
            decoration: BoxDecoration(
              color: mine ? AppColors.pink : AppColors.white,
              border: mine ? null : Border.all(color: AppColors.border),
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(16),
                topRight: const Radius.circular(16),
                bottomLeft: Radius.circular(mine ? 16 : 4),
                bottomRight: Radius.circular(mine ? 4 : 16),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (message.replyTo != null)
                  _ReplyQuote(reply: message.replyTo!, mine: mine),
                if (message.product != null)
                  _ProductPreviewCard(
                    product: message.product!,
                    mine: mine,
                    onTap: onTapProduct == null
                        ? null
                        : () => onTapProduct!(message.product!),
                  ),
                if (message.imageUrl != null)
                  GestureDetector(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) =>
                            FullImageViewer(imageUrl: message.imageUrl!),
                      ),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: CachedNetworkImage(
                        imageUrl: message.imageUrl!,
                        width: 200,
                        height: 200,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Container(
                          width: 200,
                          height: 200,
                          color: AppColors.pinkSoft,
                        ),
                        errorWidget: (context, url, error) => Container(
                          width: 200,
                          height: 200,
                          color: AppColors.pinkSoft,
                          child: Icon(
                            Icons.broken_image_outlined,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ),
                    ),
                  ),
                if (message.text.isNotEmpty)
                  Padding(
                    padding: message.imageUrl != null
                        ? const EdgeInsets.fromLTRB(8, 6, 8, 2)
                        : EdgeInsets.zero,
                    child: _Linkified(text: message.text, mine: mine),
                  ),
              ],
            ),
          ),
          if (counts.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Wrap(
                spacing: 4,
                children: counts.entries.map((e) {
                  final isMine = e.key == myEmoji;
                  return GestureDetector(
                    onTap: () => onReact(message, e.key),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: isMine ? AppColors.pinkSoft : AppColors.white,
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color: isMine ? AppColors.pink : AppColors.border,
                        ),
                      ),
                      child: Text(
                        '${e.key} ${e.value}',
                        style: const TextStyle(fontSize: 11),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _time(message.sentAt),
                  style: AppTypography.caption.copyWith(fontSize: 10.5),
                ),
                if (mine && message.seenAt != null) ...[
                  const SizedBox(width: 4),
                  Icon(Icons.done_all, size: 13, color: AppColors.pink),
                  const SizedBox(width: 2),
                  Text(
                    'Seen',
                    style: AppTypography.caption.copyWith(
                      fontSize: 10.5,
                      color: AppColors.pink,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
    final Widget actionIcons = Padding(
      padding: const EdgeInsets.only(bottom: 22),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          _BubbleActionButton(
            icon: Icons.add_reaction_outlined,
            onTap: () => _showActions(context),
          ),
          const SizedBox(height: 6),
          _BubbleActionButton(
            icon: Icons.reply_outlined,
            onTap: () => onReply(message),
          ),
        ],
      ),
    );

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: mine
            ? [actionIcons, const SizedBox(width: 4), bubbleColumn]
            : [bubbleColumn, const SizedBox(width: 4), actionIcons],
      ),
    );
  }

  String _time(DateTime time) =>
      '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
}

/// A small, always-visible tap target beside a bubble — react or reply
/// without needing a long-press first.
class _BubbleActionButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _BubbleActionButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 28,
        height: 28,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.pinkSoft,
          shape: BoxShape.circle,
        ),
        child: Icon(icon, size: 15, color: AppColors.pinkDark),
      ),
    );
  }
}

class _ReplyQuote extends StatelessWidget {
  final ChatReplyPreview reply;
  final bool mine;

  const _ReplyQuote({required this.reply, required this.mine});

  @override
  Widget build(BuildContext context) {
    final barColor = mine ? Colors.white70 : AppColors.pink;
    final bg = mine ? Colors.white.withValues(alpha: 0.15) : AppColors.pinkSoft;
    final textColor = mine ? Colors.white : AppColors.textPrimary;
    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
        border: Border(left: BorderSide(color: barColor, width: 3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            reply.isMine ? 'You' : 'Them',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: textColor,
            ),
          ),
          Text(
            reply.snippet.isEmpty ? '📷 Photo' : reply.snippet,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 12,
              color: textColor.withValues(alpha: 0.85),
            ),
          ),
        ],
      ),
    );
  }
}

/// A real product card attached to a message (via `product_id`) — a
/// thumbnail, name, and price, tappable through to the product's own page.
/// Replaces the old dead `pocketfinds://product/<id>` plain-text link.
class _ProductPreviewCard extends StatelessWidget {
  final ChatProductPreview product;
  final bool mine;
  final VoidCallback? onTap;

  const _ProductPreviewCard({
    required this.product,
    required this.mine,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bg = mine ? Colors.white.withValues(alpha: 0.15) : AppColors.pinkSoft;
    final textColor = mine ? Colors.white : AppColors.textPrimary;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: product.image == null
                  ? Container(
                      width: 48,
                      height: 48,
                      color: AppColors.pinkSoft,
                      child: Icon(
                        Icons.shopping_bag_outlined,
                        size: 20,
                        color: AppColors.pink,
                      ),
                    )
                  : CachedNetworkImage(
                      imageUrl: product.image!,
                      width: 48,
                      height: 48,
                      fit: BoxFit.cover,
                      placeholder: (context, url) => Container(
                        width: 48,
                        height: 48,
                        color: AppColors.pinkSoft,
                      ),
                      errorWidget: (context, url, error) => Container(
                        width: 48,
                        height: 48,
                        color: AppColors.pinkSoft,
                      ),
                    ),
            ),
            const SizedBox(width: 8),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 150),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: textColor,
                    ),
                  ),
                  if (product.price != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      '₱${product.price!.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: mine ? Colors.white : AppColors.pinkDark,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Linkified extends StatelessWidget {
  final String text;
  final bool mine;

  const _Linkified({required this.text, required this.mine});

  @override
  Widget build(BuildContext context) {
    final baseStyle = AppTypography.body.copyWith(
      color: mine ? AppColors.white : AppColors.textPrimary,
    );
    final linkStyle = baseStyle.copyWith(
      color: mine ? AppColors.white : AppColors.pinkDark,
      decoration: TextDecoration.underline,
      fontWeight: FontWeight.w600,
    );

    final matches = _urlPattern.allMatches(text);
    if (matches.isEmpty) {
      return Text(text, style: baseStyle);
    }

    final spans = <InlineSpan>[];
    var cursor = 0;
    for (final match in matches) {
      if (match.start > cursor) {
        spans.add(TextSpan(text: text.substring(cursor, match.start)));
      }
      final url = match.group(0)!;
      spans.add(
        TextSpan(
          text: url,
          style: linkStyle,
          recognizer: TapGestureRecognizer()..onTap = () => _openLink(url),
        ),
      );
      cursor = match.end;
    }
    if (cursor < text.length) {
      spans.add(TextSpan(text: text.substring(cursor)));
    }
    return RichText(
      text: TextSpan(style: baseStyle, children: spans),
    );
  }

  Future<void> _openLink(String url) async {
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
