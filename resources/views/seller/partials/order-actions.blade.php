            @if($order->status === 'placed' && !$order->awaitingAdminPayment())
              <form method="POST" action="{{ route('seller.orders.confirm', $order) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm btn-primary">@include('seller.partials.icon', ['name' => 'check', 'size' => 13]) Confirm Order</button>
              </form>
            @endif
            @if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
              <button type="button" class="btn btn-sm btn-danger" data-seller-cancel-open>
                Cancel Order
              </button>
            @endif
            @if($order->status === 'confirmed' && !$order->awaitingAdminPayment())
              <form method="POST" action="{{ route('seller.orders.preparing', $order) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm btn-primary">@include('seller.partials.icon', ['name' => 'edit', 'size' => 13]) Start Preparing</button>
              </form>
            @endif
            @if($order->status === 'preparing' && !$shipment && !$order->awaitingAdminPayment())
              <button type="button" class="btn btn-sm btn-primary"
                onclick="openLogisticsPickerModal('{{ route('seller.orders.ready', $order) }}', {{ Illuminate\Support\Js::from($eligibleCompanies->values()) }})">
                @include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Ready for Pickup
              </button>
            @endif
            @if($shipment && $shipment->shipping_status === 'ready_for_pickup' && is_null($shipment->pickup_approved_at))
              @if(isset($shippingObligations[$order->id]))<a class="btn btn-sm btn-primary" href="{{ route('seller.shipping.show', $shippingObligations[$order->id]->id) }}">Shipping payment · {{ str_replace('_', ' ', $shippingObligations[$order->id]->status) }}</a>@endif
              <span style="color:var(--muted);font-size:11.5px">Waiting for {{ $shipment->logistics_company }}'s admin to confirm handoff</span>
            @endif
            @if($shipment && $shipment->shipping_status === 'ready_for_pickup' && $shipment->pickup_rider_id && $shipment->rider_confirmed_pickup_at)
              {{-- Only unlocked once the rider has confirmed receiving the parcel first. --}}
              <button type="button" class="btn btn-sm btn-primary"
                onclick="openConfirmPickupModal('{{ route('seller.orders.confirm-pickup', $order) }}', '{{ $order->order_number }}', {{ Illuminate\Support\Js::from(trim(($shipment->pickupRider->given_names ?? '').' '.($shipment->pickupRider->last_name ?? ''))) }})">
                @include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Confirm Pickup
              </button>
            @elseif($shipment && $shipment->shipping_status === 'ready_for_pickup' && $shipment->pickup_rider_id)
              <span style="color:var(--muted);font-size:11.5px">Waiting for the rider to confirm they picked it up</span>
            @endif
            @if($order->status === 'preparing' && !$order->awaitingAdminPayment())
              <button type="button" class="btn btn-sm btn-outline" data-seller-summary-waybill="{{ route('seller.orders.waybill', ['order' => $order, 'embedded' => 1]) }}" title="Print waybill">@include('seller.partials.icon', ['name' => 'file', 'size' => 13]) Print waybill</button>
            @endif
            @if($shipment)
              @if(!$shipment->picked_up_at)
              <button type="button" class="btn btn-sm btn-outline" title="Schedule Pickup" onclick="openScheduleModal('{{ $order->id }}', '{{ $order->order_number }}')">@include('seller.partials.icon', ['name' => 'clock', 'size' => 13]) Schedule</button>
              @endif
            @endif
