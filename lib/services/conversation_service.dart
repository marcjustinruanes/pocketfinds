import 'dart:convert';

import 'delivery_service.dart';

import 'dart:async';
import 'dart:io';

import '../models/chat_ui.dart';
import '../models/product.dart';
import 'backend_service.dart';

/// A picked-and-uploaded chat image, ready to attach to `sendMessage`.
class ChatAttachmentUpload {
  final String path;
  final String name;
  final String mime;
  final int size;

  const ChatAttachmentUpload({
    required this.path,
    required this.name,
    required this.mime,
    required this.size,
  });
}

abstract class ConversationService {
  /// The caller's own id, stringified — lets UI (e.g. `MessageBubble`,
  /// deciding which reaction chip is "mine") work from the service alone
  /// without every construction site threading a separate `myUserId` param.
  String get myUserIdString;

  Future<List<ConversationSummary>> fetchConversations(String userId);
  Future<List<ChatMessage>> fetchMessages(String conversationId);
  Future<void> sendMessage(
    String conversationId,
    String text, {
    String? replyToId,
    ChatAttachmentUpload? attachment,
    String? productId,
  });
  Future<ChatAttachmentUpload> uploadChatImage(File file);
  Future<void> reactToMessage(String messageId, String emoji);

  /// The counterpart's real `last_active_at`, or null if they've never been
  /// active or don't share a thread with the caller yet.
  Future<DateTime?> fetchPresence(String conversationId);

  /// Fire-and-forget: marks the caller as active right now. Called on
  /// conversation open and on each poll tick so a chat partner's "Online"
  /// status stays meaningful while this user is actually looking at the
  /// screen.
  Future<void> touchPresence();

  Future<void> blockUser(String userId);
  Future<void> unblockUser(String userId);
  Future<bool> isUserBlocked(String userId);
}

/// Backed by `public.messages`. A "conversation" is derived (there's no
/// `conversation_id` column) as all messages between the caller and one
/// counterpart — [ConversationSummary.id]/the `conversationId` params
/// throughout this class are that counterpart's `public.users.id`, stringified.
class BackendConversationService implements ConversationService {
  final _sendRequests = <String, String>{};
  final _uploadedImages = <String, ChatAttachmentUpload>{};
  final int myUserId;

  BackendConversationService({required this.myUserId});

  @override
  String get myUserIdString => myUserId.toString();

  @override
  Future<List<ConversationSummary>> fetchConversations(String userId) async {
    final rows = await BackendService.client.rpc('fetch_my_conversations');
    final list = List<Map<String, dynamic>>.from(rows as List);
    return list.map((row) {
      final name =
          (row['counterpart_name'] as String?)?.trim() ?? 'PocketFinds user';
      final attachmentType = row['last_message_attachment_type']?.toString();
      final productName = row['last_message_product_name']?.toString();
      final body = row['last_message_body']?.toString();
      final bodyEmpty = body == null || body.isEmpty;
      return ConversationSummary(
        id: row['counterpart_id'].toString(),
        participantName: name,
        participantAvatarInitial: name.isNotEmpty ? name[0].toUpperCase() : '?',
        lastMessagePreview: bodyEmpty && attachmentType == 'image'
            ? '📷 Photo'
            : bodyEmpty && productName != null && productName.isNotEmpty
            ? '📦 $productName'
            : (body ?? ''),
        lastMessageAt:
            DateTime.tryParse(row['last_message_at']?.toString() ?? '') ??
            DateTime.now(),
        unreadCount: (row['unread_count'] as num?)?.toInt() ?? 0,
        participantRole: row['counterpart_role']?.toString(),
        lastMessageIsImage: attachmentType == 'image',
        lastMessageIsProduct: productName != null && productName.isNotEmpty,
        isActive: row['is_active'] != false,
      );
    }).toList();
  }

  @override
  Future<List<ChatMessage>> fetchMessages(String conversationId) async {
    final counterpartId = int.parse(conversationId);
    final rows = await BackendService.client.rpc(
      'fetch_conversation_messages',
      params: {'p_counterpart_id': counterpartId},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    // Fire-and-forget — opening the thread marks the counterpart's messages
    // read; failure here shouldn't block showing the messages themselves.
    unawaited(_markRead(counterpartId));
    return list.map((row) => _fromRow(row, conversationId)).toList();
  }

  ChatMessage _fromRow(Map<String, dynamic> row, String conversationId) {
    final senderId = (row['sender_id'] as num).toInt();
    final mine = senderId == myUserId;
    final attachmentType = row['attachment_type']?.toString();
    final attachmentPath = row['attachment_path']?.toString();
    final imageUrl = (attachmentType == 'image' && attachmentPath != null)
        ? BackendService.client.storage
              .from('messages')
              .getPublicUrl(attachmentPath)
        : null;

    ChatReplyPreview? replyTo;
    final replyToId = row['reply_to_id'];
    if (replyToId != null) {
      final replySenderId = (row['reply_to_sender_id'] as num?)?.toInt();
      final replyBody = row['reply_to_body']?.toString();
      final replyHasAttachment = row['reply_to_has_attachment'] == true;
      replyTo = ChatReplyPreview(
        id: replyToId.toString(),
        isMine: replySenderId == myUserId,
        snippet: (replyBody == null || replyBody.isEmpty)
            ? (replyHasAttachment ? '📷 Photo' : '')
            : replyBody,
        hasAttachment: replyHasAttachment,
      );
    }

    final productId = row['product_id']?.toString();
    ChatProductPreview? product;
    if (productId != null && productId.isNotEmpty) {
      product = ChatProductPreview(
        id: productId,
        name: row['product_name']?.toString() ?? 'Product',
        image: Product.resolveImageUrl(row['product_image']?.toString()),
        price: double.tryParse('${row['product_price']}'),
      );
    }

    final reactionsRaw = row['reactions'];
    final reactions = reactionsRaw is List
        ? reactionsRaw
              .whereType<Map>()
              .map(
                (r) => MessageReaction(
                  userId: r['user_id'].toString(),
                  emoji: r['emoji'].toString(),
                ),
              )
              .toList()
        : <MessageReaction>[];

    final read = row['read'] == true;
    final updatedAt = DateTime.tryParse(row['updated_at']?.toString() ?? '');

    return ChatMessage(
      id: row['id'].toString(),
      conversationId: conversationId,
      senderId: senderId.toString(),
      text: row['body']?.toString() ?? '',
      sentAt:
          DateTime.tryParse(row['created_at']?.toString() ?? '') ??
          DateTime.now(),
      isMine: mine,
      imageUrl: imageUrl,
      product: product,
      replyTo: replyTo,
      reactions: reactions,
      seenAt: (mine && read) ? updatedAt : null,
    );
  }

  @override
  Future<void> sendMessage(
    String conversationId,
    String text, {
    String? replyToId,
    ChatAttachmentUpload? attachment,
    String? productId,
  }) async {
    final key = jsonEncode([
      conversationId,
      text,
      replyToId,
      attachment?.path,
      productId,
    ]);
    final request = _sendRequests.putIfAbsent(key, DeliveryService.requestId);
    await BackendService.client.rpc(
      'send_message',
      params: {
        'p_request_id': request,
        'p_receiver_id': int.parse(conversationId),
        'p_body': text,
        'p_product_id': ?productId,
        if (replyToId != null) 'p_reply_to_id': int.parse(replyToId),
        if (attachment != null) ...{
          'p_attachment_path': attachment.path,
          'p_attachment_name': attachment.name,
          'p_attachment_mime': attachment.mime,
          'p_attachment_size': attachment.size,
        },
      },
    );
    _sendRequests.remove(key);
  }

  @override
  Future<ChatAttachmentUpload> uploadChatImage(File file) async {
    if (_uploadedImages.containsKey(file.path)) {
      return _uploadedImages[file.path]!;
    }
    final originalName = file.path.split(Platform.pathSeparator).last;
    final dot = originalName.lastIndexOf('.');
    final ext = dot == -1
        ? 'jpg'
        : originalName.substring(dot + 1).toLowerCase();
    final mime = switch (ext) {
      'png' => 'image/png',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'heic' || 'heif' => 'image/heic',
      _ => 'image/jpeg',
    };
    final path =
        '$myUserId/${DateTime.now().millisecondsSinceEpoch}_$originalName';
    await BackendService.client.storage.from('messages').upload(path, file);
    final size = await file.length();
    final upload = ChatAttachmentUpload(
      path: path,
      name: originalName,
      mime: mime,
      size: size,
    );
    _uploadedImages[file.path] = upload;
    return upload;
  }

  @override
  Future<void> reactToMessage(String messageId, String emoji) async {
    await BackendService.client.rpc(
      'react_to_message',
      params: {'p_message_id': int.parse(messageId), 'p_emoji': emoji},
    );
  }

  @override
  Future<DateTime?> fetchPresence(String conversationId) async {
    final rows = await BackendService.client.rpc(
      'get_conversation_presence',
      params: {'p_counterpart_id': int.parse(conversationId)},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return null;
    final raw = list.first['last_active_at']?.toString();
    if (raw == null) return null;
    return DateTime.tryParse(raw);
  }

  @override
  Future<void> touchPresence() async {
    try {
      await BackendService.client.rpc('touch_my_presence');
    } catch (_) {
      // Non-fatal — presence just won't refresh this tick.
    }
  }

  @override
  Future<void> blockUser(String userId) async {
    await BackendService.client.rpc(
      'block_user',
      params: {'p_user_id': int.parse(userId)},
    );
  }

  @override
  Future<void> unblockUser(String userId) async {
    await BackendService.client.rpc(
      'unblock_user',
      params: {'p_user_id': int.parse(userId)},
    );
  }

  @override
  Future<bool> isUserBlocked(String userId) async {
    final rows = await BackendService.client.rpc('fetch_blocked_users');
    final list = List<Map<String, dynamic>>.from(rows as List);
    return list.any((r) => r['user_id'].toString() == userId);
  }

  Future<void> _markRead(int counterpartId) async {
    try {
      await BackendService.client.rpc(
        'mark_messages_read',
        params: {'p_counterpart_id': counterpartId},
      );
    } catch (_) {
      // Non-fatal — unread counts just won't update this time.
    }
  }
}

/// Genuinely empty, not mock data standing in for real conversations —
/// useful for widget tests/previews that shouldn't touch a live connection.
class EmptyConversationService implements ConversationService {
  const EmptyConversationService();

  @override
  String get myUserIdString => '';

  @override
  Future<List<ConversationSummary>> fetchConversations(String userId) async =>
      const [];

  @override
  Future<List<ChatMessage>> fetchMessages(String conversationId) async =>
      const [];

  @override
  Future<void> sendMessage(
    String conversationId,
    String text, {
    String? replyToId,
    ChatAttachmentUpload? attachment,
    String? productId,
  }) async {}

  @override
  Future<ChatAttachmentUpload> uploadChatImage(File file) async =>
      const ChatAttachmentUpload(path: '', name: '', mime: '', size: 0);

  @override
  Future<void> reactToMessage(String messageId, String emoji) async {}

  @override
  Future<DateTime?> fetchPresence(String conversationId) async => null;

  @override
  Future<void> touchPresence() async {}

  @override
  Future<void> blockUser(String userId) async {}

  @override
  Future<void> unblockUser(String userId) async {}

  @override
  Future<bool> isUserBlocked(String userId) async => false;
}
