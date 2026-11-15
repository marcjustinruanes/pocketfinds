import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../services/conversation_service.dart';
import '../../services/delivery_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/chat/conversation_list_tile.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/segmented_control.dart';
import '../../widgets/staggered_fade_in.dart';
import 'conversation_detail_screen.dart';

enum _Filter { all, sellers, riders }

/// The Buyer Messages tab — backed by `public.messages` via
/// `BackendConversationService` (see that class for the RPCs involved).
/// Real All/Sellers/Riders filter (client-side, on the already-returned
/// `participantRole`). Pass [service] to override for tests/previews
/// (e.g. `EmptyConversationService`).
class MessagesTab extends StatefulWidget {
  final AppUser user;
  final ConversationService? service;

  const MessagesTab({super.key, required this.user, this.service});

  @override
  State<MessagesTab> createState() => _MessagesTabState();
}

class _MessagesTabState extends State<MessagesTab>
    with WidgetsBindingObserver, DeliveryRefresh<MessagesTab> {
  @override
  Future<void> refreshDeliveryData() => _refresh();
  Future<void> _refresh() async {
    setState(
      () => _future = _service.fetchConversations(widget.user.id.toString()),
    );
    await _future;
  }

  late final ConversationService _service =
      widget.service ?? BackendConversationService(myUserId: widget.user.id);
  late Future<List<ConversationSummary>> _future = _service.fetchConversations(
    widget.user.id.toString(),
  );
  _Filter _filter = _Filter.all;

  List<ConversationSummary> _applyFilter(
    List<ConversationSummary> conversations,
  ) {
    return switch (_filter) {
      _Filter.all => conversations,
      _Filter.sellers =>
        conversations.where((c) => c.participantRole == 'seller').toList(),
      _Filter.riders =>
        conversations.where((c) => c.participantRole == 'rider').toList(),
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('Messages', style: AppTypography.pageTitle),
        actions: [
          IconButton(
            tooltip: 'Refresh messages',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: 'Could not load messages.',
              error: friendlyError(snapshot.error!),
            );
          }
          final allConversations =
              snapshot.data ?? const <ConversationSummary>[];
          final conversations = _applyFilter(allConversations);
          return Column(
            children: [
              SegmentedControl<_Filter>(
                options: const [
                  SegmentedControlOption(_Filter.all, 'All'),
                  SegmentedControlOption(_Filter.sellers, 'Sellers'),
                  SegmentedControlOption(_Filter.riders, 'Riders'),
                ],
                value: _filter,
                onChanged: (v) => setState(() => _filter = v),
              ),
              Expanded(
                child: conversations.isEmpty
                    ? EmptyState(
                        iconWidget: MessageIcon(
                          size: 32,
                          color: AppColors.pink,
                        ),
                        title: allConversations.isEmpty
                            ? 'No messages yet'
                            : 'No conversations here',
                        subtitle: allConversations.isEmpty
                            ? 'Conversations with sellers will show up here.'
                            : null,
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        itemCount: conversations.length,
                        itemBuilder: (context, index) {
                          final conversation = conversations[index];
                          return StaggeredFadeIn(
                            index: index,
                            child: ConversationListTile(
                              conversation: conversation,
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => ConversationDetailScreen(
                                      conversation: conversation,
                                      service: _service,
                                      user: widget.user,
                                    ),
                                  ),
                                );
                                if (mounted) await _refresh();
                              },
                            ),
                          );
                        },
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}
