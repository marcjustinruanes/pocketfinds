@push('head')
<link rel="stylesheet" href="{{ asset('css/commission-enrollment.css') }}?v={{ filemtime(public_path('css/commission-enrollment.css')) }}">
@endpush
@php
  $commissionActive = (bool) ($commissionEnrollment->enabled ?? false);
  $commissionTime = $commissionEnrollment?->activation_at ? \Carbon\CarbonImmutable::parse($commissionEnrollment->activation_at)->timezone('Asia/Manila') : null;
  $commissionErrors = collect(['action', 'revision', 'reason', 'confirmed', 'activation_at', 'commission_rate', 'enabled'])->flatMap(fn ($field) => $errors->get($field));
@endphp
<section class="card commission-enrollment" aria-labelledby="commission-enrollment-title">
  <header class="ce-header">
    <div><p class="ce-eyebrow">Platform commission</p><h2 id="commission-enrollment-title">Commission enrollment</h2><p class="ce-description">Control when eligible new transactions start earning commission.</p></div>
    <span class="ce-status {{ $commissionActive ? 'ce-status-active' : '' }}" role="status"><span aria-hidden="true" class="ce-status-dot"></span>{{ !$commissionEnrollment ? 'Unavailable' : ($commissionActive ? 'Active' : 'Inactive') }}</span>
  </header>
  @if(!$commissionEnrollment)
    <div class="ce-body"><p class="ce-notice" role="status">Commission controls are not available yet. Contact your system administrator to complete setup.</p></div>
  @else
    <div class="ce-body">
      <dl class="ce-summary">
        <div><dt>Commission rate</dt><dd class="ce-rate" aria-label="10% (fixed)">10% <span>fixed</span></dd><dd class="ce-caption">On eligible product value</dd></div>
        <div><dt>{{ $commissionActive ? 'Activation time' : 'Last activation time' }}</dt><dd>{{ $commissionTime ? $commissionTime->format('j M Y') : 'Not activated' }}</dd><dd class="ce-caption">{{ $commissionTime ? $commissionTime->format('H:i:s').' PHT' : 'Starts only after confirmation' }}</dd></div>
        <div><dt>Payment collection</dt><dd>Manual</dd><dd class="ce-caption">Platform Admin verifies receipt</dd></div>
      </dl>
      <div class="ce-policy"><span class="ce-policy-icon" aria-hidden="true">i</span><div>
        <strong>{{ $commissionActive ? 'New eligible transactions can enroll.' : 'New transactions are not being enrolled.' }}</strong>
        <p>{{ $commissionActive ? 'Deactivating stops future enrollment. Existing obligations, reconciliation, payments and history continue.' : 'Activation starts a new cutoff for eligible orders and GCash purchases. Previous orders and transactions from inactive periods stay excluded.' }}</p>
      </div></div>
      <div class="ce-action-layout">
        <div class="ce-action-copy"><h3>{{ $commissionActive ? 'Pause new enrollment' : 'Start new enrollment' }}</h3>
          <p>{{ $commissionActive ? 'Turn commission enrollment off without interrupting existing settlements.' : 'Confirm the official GCash account and live activation requirements before turning this on.' }}</p>
          <a href="{{ route('commissions.index') }}">Review commission statements &amp; receiving account <span aria-hidden="true">&rarr;</span></a>
        </div>
        <form class="ce-form" method="POST" action="{{ route('admin.settings.commission.update') }}"
          data-confirm data-confirm-title="{{ $commissionActive ? 'Deactivate commission enrollment?' : 'Activate commission enrollment?' }}"
          data-confirm-message="{{ $commissionActive ? 'Only future enrollment will stop. Existing obligations and payments will continue.' : 'This starts a new cutoff for eligible transactions. Verify the official GCash account and readiness requirements before continuing.' }}"
          data-confirm-action="{{ $commissionActive ? 'Deactivate' : 'Activate' }}" data-confirm-tone="{{ $commissionActive ? 'danger' : 'success' }}"
          onsubmit="if (this.dataset.confirmed === '1') { const button = this.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Saving change…'; }">
          @csrf
          <input type="hidden" name="revision" value="{{ $commissionEnrollment->revision }}">
          <input type="hidden" name="action" value="{{ $commissionActive ? 'deactivate' : 'activate' }}">
          @if($commissionErrors->isNotEmpty())<div class="ce-errors" role="alert"><strong>Change not saved</strong><ul>@foreach($commissionErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
          <div class="form-row">
            <label for="commission-change-reason">Reason for change <span class="ce-required">(required)</span></label>
            <textarea id="commission-change-reason" name="reason" rows="3" minlength="3" maxlength="1000" required aria-describedby="commission-reason-help" @if($errors->has('reason')) aria-invalid="true" @endif placeholder="e.g. Approved launch after account and readiness review">{{ old('reason') }}</textarea>
            <p id="commission-reason-help" class="ce-help">Saved with your Admin identity in the activation history. Up to 1,000 characters.</p>
          </div>
          <label class="ce-confirmation" for="commission-confirmed"><input id="commission-confirmed" type="checkbox" name="confirmed" value="1" required>
            <span><strong>{{ $commissionActive ? 'I confirm this deactivation' : 'I approve this activation' }}</strong><span>{{ $commissionActive ? 'Only future enrollment will stop. Existing obligations will continue.' : 'The official GCash account and live activation requirements are verified. I approve 10% commission on eligible new transactions.' }}</span></span>
          </label>
          <div class="ce-submit-row"><span class="ce-help">{{ $commissionActive ? 'Takes effect after saving.' : 'Starts from the new activation time after saving.' }}</span><button class="btn {{ $commissionActive ? 'ce-deactivate' : 'btn-primary' }}" type="submit">{{ $commissionActive ? 'Deactivate Commission' : 'Activate Commission' }}</button></div>
        </form>
      </div>
    </div>
    <div class="ce-history">
      <div class="ce-history-heading"><div><h3>Commission activation history</h3><p>Admin changes and their reasons. All times are in PHT.</p></div><span class="ce-history-count">{{ $commissionEnrollmentEvents?->total() ?? 0 }} {{ ($commissionEnrollmentEvents?->total() ?? 0) === 1 ? 'change' : 'changes' }}</span></div>
      @if($commissionEnrollmentEvents && $commissionEnrollmentEvents->count())
        <ol class="ce-history-list">@foreach($commissionEnrollmentEvents as $event)
          <li class="ce-history-item">
            <div class="ce-event-top"><strong>{{ $event->action === 'activate' ? 'Commission activated' : 'Commission deactivated' }}</strong><span class="ce-event-revision">Revision {{ $event->revision }}</span></div>
            <p class="ce-event-meta">{{ $event->actor_name }} &middot; {{ \Carbon\CarbonImmutable::parse($event->created_at)->timezone('Asia/Manila')->format('j M Y, H:i:s') }} PHT</p>
            <p class="ce-event-reason">{{ $event->reason }}</p>
            @if($event->activation_at)<p class="ce-event-cutoff">{{ $event->action === 'activate' ? 'Enrollment cutoff' : 'Previous enrollment cutoff' }}: {{ \Carbon\CarbonImmutable::parse($event->activation_at)->timezone('Asia/Manila')->format('j M Y, H:i:s') }} PHT</p>@endif
          </li>
        @endforeach</ol>
        <div class="ce-pagination">{{ $commissionEnrollmentEvents->links() }}</div>
      @else
        <div class="ce-empty"><span aria-hidden="true">&#9719;</span><div><strong>No changes yet</strong><p>Activations and deactivations will appear here with the Admin, time and reason.</p></div></div>
      @endif
    </div>
  @endif
</section>
