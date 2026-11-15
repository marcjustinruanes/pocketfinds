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