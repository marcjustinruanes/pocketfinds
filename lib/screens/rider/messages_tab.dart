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
import '../buyer/conversation_detail_screen.dart';

enum _Filter { all, active }

/// The Rider Messages tab — same shape as Buyer's, see
/// `lib/screens/buyer/messages_tab.dart` for the shared `ConversationService`
/// wiring against `public.messages`. Real All/Active filter — a rider's
/// counterparts are always buyers, so (unlike Buyer's Seller/Rider split)
/// the useful real distinction here is whether the underlying delivery is
/// still ongoing (`ConversationSummary.isActive`).
class RiderMessagesTab extends StatefulWidget {
  final AppUser user;
  final ConversationService? service;

  const RiderMessagesTab({super.key, required this.user, this.service});

  @override
  State<RiderMessagesTab> createState() => _RiderMessagesTabState();
}

class _RiderMessagesTabState extends State<RiderMessagesTab>
    with WidgetsBindingObserver, DeliveryRefresh<RiderMessagesTab> {
  late final ConversationService _service =
      widget.service ?? BackendConversationService(myUserId: widget.user.id);
  late Future<List<ConversationSummary>> _future = _load();
  Future<List<ConversationSummary>> _load() =>
      _service.fetchConversations(widget.user.id.toString());
  Future<void> _refresh() async {
    setState(() {
      _future = _load();
    });
    await _future;
  }

  @override
  Future<void> refreshDeliveryData() => _refresh();
  _Filter _filter = _Filter.all;

  List<ConversationSummary> _applyFilter(
    List<ConversationSummary> conversations,
  ) {
    if (_filter == _Filter.active) {
      return conversations.where((c) => c.isActive).toList();
    }
    return conversations;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('Messages', style: AppTypography.pageTitle)),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<List<ConversationSummary>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    ErrorState(
                      message: 'Could not load messages.',
                      error: friendlyError(snapshot.error!),
                      onRetry: _refresh,
                    ),
                  ],
                );
              }
              final allConversations =
                  snapshot.data ?? const <ConversationSummary>[];
              final conversations = _applyFilter(allConversations);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SegmentedControl<_Filter>(
                    options: const [
                      SegmentedControlOption(_Filter.all, 'All'),
                      SegmentedControlOption(_Filter.active, 'Active'),
                    ],
                    value: _filter,
                    onChanged: (v) => setState(() => _filter = v),
                  ),
                  Expanded(
                    child: conversations.isEmpty
                        ? CustomScrollView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            slivers: [
                              SliverFillRemaining(
                                hasScrollBody: false,
                                child: Center(
                                  child: EmptyState(
                                    iconWidget: MessageIcon(
                                      size: 32,
                                      color: AppColors.pink,
                                    ),
                                    title: allConversations.isEmpty
                                        ? 'No messages yet'
                                        : 'No active conversations',
                                    subtitle: allConversations.isEmpty
                                        ? 'Messages with buyers, sellers and logistics will appear here.'
                                        : 'Conversations for ongoing deliveries appear here.',
                                  ),
                                ),
                              ),
                            ],
                          )
                        : ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.fromLTRB(4, 8, 4, 24),
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
                                        builder: (_) =>
                                            ConversationDetailScreen(
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
        ),
      ),
    );
  }
}
