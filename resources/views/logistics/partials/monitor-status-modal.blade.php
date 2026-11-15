<div class="modal-overlay" id="monitor-status-{{ $s->id }}">
<section class="modal monitor-status-dialog" role="dialog" aria-modal="true" aria-labelledby="monitor-status-title-{{ $s->id }}">
  <header class="modal-head"><div><h3 id="monitor-status-title-{{ $s->id }}">Update parcel status</h3><p>{{ $s->tracking_number ?? $s->id }}</p></div><button type="button" class="modal-close" data-modal-close aria-label="Close status update">&times;</button></header>
  <div class="modal-body"><p class="monitor-current-state">Current status <strong>{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</strong></p>
<form method="POST" action="{{ route('logistics.status.update', $s->id) }}" class="monitor-status-form" data-confirm data-confirm-title="Update this parcel status?" data-confirm-message="The selected status and reason will be recorded in the shipment history." data-confirm-action="Update status" data-confirm-tone="success">
              @csrf @method('PATCH')
              <input type="hidden" name="attempt_token" value="{{ $s->failedDeliveryToken() }}">
              <label class="monitor-status-select"><span>New status</span><select name="status" class="select">
                @foreach($allowedNext as $st)
                <option value="{{ $st }}">{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select></label>
              <label>Reason<input name="reason" minlength="10" maxlength="300" required ></label><button class="btn btn-primary" type="submit">Update status</button>
            </form>
  </div>
</section>
</div>
