import '../../services/buyer_photo_service.dart';
import '../../utils/friendly_error.dart';

import 'package:geolocator/geolocator.dart';

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/chat_ui.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';

/// A sticky-bottom message composer — bordered text field + circular pink
/// send button, an attach-image button (real upload, via `image_picker` —
/// same camera/gallery-choice pattern as the registration wizard), and,
/// when replying to a specific message, a cancellable "Replying to ..."
/// strip above the field.
class ChatInputBar extends StatefulWidget {
  final Future<void> Function(String text, File? image) onSend;
  final ChatMessage? replyingTo;
  final VoidCallback? onCancelReply;

  const ChatInputBar({
    super.key,
    required this.onSend,
    this.replyingTo,
    this.onCancelReply,
  });

  @override
  State<ChatInputBar> createState() => _ChatInputBarState();
}

class _ChatInputBarState extends State<ChatInputBar> {
  final _controller = TextEditingController();
  final _picker = ImagePicker();
  File? _pickedImage;
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_sending) return;
    final text = _controller.text.trim();
    if (text.isEmpty && _pickedImage == null) return;
    if (text.length > 1000) {
      setState(() => _error = 'Messages can contain at most 1,000 characters.');
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await widget.onSend(text, _pickedImage);
      if (mounted) {
        _controller.clear();
        setState(() => _pickedImage = null);
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = 'Not sent. ${friendlyError(e)} Tap Send to retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _attachImage() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    try {
      final picked = await _picker.pickImage(source: source, imageQuality: 85);
      if (picked == null) return;
      final photo = await BuyerPhotoService.prepare(
        picked,
        camera: source == ImageSource.camera,
      );
      final dir = await Directory.systemTemp.createTemp('pocketfinds-chat-');
      final file = File('${dir.path}/photo.${photo.extension}');
      await file.writeAsBytes(photo.bytes);
      if (mounted) setState(() => _pickedImage = file);
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = 'Photo could not be selected. ${friendlyError(e)}',
      );
      final open = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Photo access'),
          content: const Text(
            'Check camera/photo access in Settings, or choose another image.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Open Settings'),
            ),
          ],
        ),
      );
      if (open == true) await Geolocator.openAppSettings();
    }
  }

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.white,
        boxShadow: AppShadows.md,
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(8),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (widget.replyingTo != null)
              _ReplyingToBar(
                message: widget.replyingTo!,
                onCancel: widget.onCancelReply,
              ),
            if (_pickedImage != null)
              _PickedImagePreview(
                file: _pickedImage!,
                onRemove: () => setState(() => _pickedImage = null),
              ),
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.md,
                vertical: AppSpacing.sm,
              ),
              child: Row(
                children: [
                  IconButton(
                    onPressed: _sending ? null : _attachImage,
                    icon: Icon(Icons.image_outlined, color: AppColors.pink),
                  ),
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      enabled: !_sending,
                      maxLength: 1000,
                      minLines: 1,
                      maxLines: 4,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: const InputDecoration(
                        hintText: 'Type a message…',
                      ),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Material(
                    color: AppColors.pink,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: _sending ? null : _send,
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Icon(
                          Icons.send_rounded,
                          color: AppColors.white,
                          size: 20,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReplyingToBar extends StatelessWidget {
  final ChatMessage message;
  final VoidCallback? onCancel;

  const _ReplyingToBar({required this.message, this.onCancel});

  @override
  Widget build(BuildContext context) {
    final snippet = message.text.isNotEmpty
        ? message.text
        : (message.imageUrl != null ? '📷 Photo' : '');
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: 8,
      ),
      decoration: BoxDecoration(
        color: AppColors.pinkSoft,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Icon(Icons.reply, size: 16, color: AppColors.pink),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  message.isMine ? 'Replying to yourself' : 'Replying',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.pinkDark,
                  ),
                ),
                Text(
                  snippet,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
              ],
            ),
          ),
          if (onCancel != null)
            IconButton(
              icon: const Icon(Icons.close, size: 18),
              onPressed: onCancel,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(),
            ),
        ],
      ),
    );
  }
}

class _PickedImagePreview extends StatelessWidget {
  final File file;
  final VoidCallback onRemove;

  const _PickedImagePreview({required this.file, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: 8,
      ),
      alignment: Alignment.centerLeft,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.file(file, width: 72, height: 72, fit: BoxFit.cover),
          ),
          Positioned(
            top: -6,
            right: -6,
            child: GestureDetector(
              onTap: onRemove,
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.textPrimary,
                  shape: BoxShape.circle,
                ),
                padding: const EdgeInsets.all(2),
                child: const Icon(Icons.close, size: 14, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
