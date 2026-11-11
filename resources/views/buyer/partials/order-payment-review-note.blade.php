@if($rejectionEvent?->reason)
 <p class="order-payment-error" role="status"><strong>Admin’s note:</strong> {{ $rejectionEvent->reason }}</p>
@endif
