class ConversationSummary {
  final String id;
  final String participantName;
  final String participantAvatarInitial;
  final String lastMessagePreview;
  final DateTime lastMessageAt;
  final int unreadCount;

  /// The counterpart's real `account_type` (buyer/seller/rider/admin/
  /// logistics), null when constructed ad hoc for a brand-new thread
  /// (e.g. messaging a seller from Product Detail) rather than read back
  /// from `fetch_my_conversations()`. Used to show "Seller"/"PocketFinds
  /// Courier" under the contact's name so it's clear who's on the other end.
  final String? participantRole;

  /// True when the last message was image-only (no text body) — lets the
  /// list preview show "📷 Photo" instead of a blank line.
  final bool lastMessageIsImage;

  /// True when the last message carried a product (from `product_id`), so
  /// the list preview can show "📦 " + the product name instead of a blank line.
  final bool lastMessageIsProduct;

  /// Real, computed from actual order/shipment status (see
  /// `conversation_is_active()`) — false once every order/shipment between
  /// the caller and this counterpart has been delivered/completed/returned/
  /// cancelled. True by default for a brand-new thread with no order yet.
  final bool isActive;

  const ConversationSummary({
    required this.id,
    required this.participantName,
    required this.participantAvatarInitial,
    required this.lastMessagePreview,
    required this.lastMessageAt,
    this.unreadCount = 0,
    this.participantRole,
    this.lastMessageIsImage = false,
    this.lastMessageIsProduct = false,
    this.isActive = true,
  });

  /// A friendly label for [participantRole] — "Seller"/"PocketFinds
  /// Courier"/etc. Null (not shown) when the role isn't known.
  String? get roleLabel => switch (participantRole) {
    'seller' => 'Seller',
    'rider' => 'PocketFinds Courier',
    'admin' => 'PocketFinds Support',
    'logistics' => 'PocketFinds Logistics',
    'buyer' => 'Buyer',
    _ => null,
  };
}

/// One emoji reaction on a message, from `message_reactions`.
class MessageReaction {
  final String userId;
  final String emoji;

  const MessageReaction({required this.userId, required this.emoji});
}

/// A small quoted preview of the message being replied to — denormalized
/// from `fetch_conversation_messages()`'s `reply_to_*` columns rather than
/// requiring a second fetch for the original message.
class ChatReplyPreview {
  final String id;
  final bool isMine;
  final String snippet;
  final bool hasAttachment;

  const ChatReplyPreview({
    required this.id,
    required this.isMine,
    required this.snippet,
    required this.hasAttachment,
  });
}

/// A lightweight product snapshot attached to a message via its
/// `product_id` (joined server-side in `fetch_conversation_messages()`) —
/// lets a chat bubble render a real tappable product card instead of the
/// dead `product_id` the field used to be.
class ChatProductPreview {
  final String id;
  final String name;
  final String? image;
  final double? price;

  const ChatProductPreview({
    required this.id,
    required this.name,
    this.image,
    this.price,
  });
}

class ChatMessage {
  final String id;
  final String conversationId;
  final String senderId;
  final String text;
  final DateTime sentAt;
  final bool isMine;

  /// A real photo URL (from the `messages` Storage bucket via
  /// `attachment_path`), only set when `attachment_type == 'image'`. Null
  /// for a plain-text message or a non-image attachment.
  final String? imageUrl;

  /// The product this message is about, if any (see `product_id`) —
  /// renders as a tappable product card in the bubble.
  final ChatProductPreview? product;

  /// The message being replied to, if any (see `reply_to_id`).
  final ChatReplyPreview? replyTo;

  /// Real reactions from `message_reactions`, one per user.
  final List<MessageReaction> reactions;

  /// When this (my own) message was marked read by the counterpart —
  /// `messages.updated_at` at the point `read` flipped true. Null for a
  /// message that hasn't been seen yet, or for a message that isn't mine
  /// (seen status is only ever shown on the sender's own messages).
  final DateTime? seenAt;

  const ChatMessage({
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.text,
    required this.sentAt,
    required this.isMine,
    this.imageUrl,
    this.product,
    this.replyTo,
    this.reactions = const [],
    this.seenAt,
  });
}
