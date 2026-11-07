<section aria-label="Payment messages">
<h4>Messages</h4>
@if($messagingInstalled)
<p>{{ $messageCounts[$conversationKind.':'.$conversationId]??0 }} entries. <a href="{{ route('commissions.conversation',[$conversationKind,$conversationId]) }}">Read messages and reply</a></p>
@else<p>Payment messaging requires installation. Existing payment records remain available.</p>@endif
</section>
