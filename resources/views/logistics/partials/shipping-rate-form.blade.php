<form method="POST" action="{{ route('logistics.finance.shipping.rate') }}" class="fin-rate-form">
  @csrf
  <input type="hidden" name="revision" value="{{ $rate?->revision ?? 0 }}">
  <label for="shipping-region-{{ $formId }}">
    Destination region
    @if(!$rate)<span class="fin-field-hint">Match the region shown in the delivery address</span>@endif
  </label>
  <input id="shipping-region-{{ $formId }}" name="region" maxlength="150" required
    value="{{ $rate?->region }}" @if($rate) readonly aria-readonly="true" @else placeholder="e.g. Region IV-A (CALABARZON)" @endif>
  <label for="shipping-first-{{ $formId }}">
    First kilogram (PHP)
    @if(!$rate)<span class="fin-field-hint">Base rate for the first kg</span>@endif
  </label>
  <input id="shipping-first-{{ $formId }}" name="first_kg" type="number" min="0" max="9999999.99" step="0.01" required
    value="{{ $rate ? number_format($rate->first_kg_cents/100,2,'.','') : '' }}" placeholder="0.00">
  <label for="shipping-extra-{{ $formId }}">
    Each additional kilogram (PHP)
    @if(!$rate)<span class="fin-field-hint">Rate per kg beyond the first</span>@endif
  </label>
  <input id="shipping-extra-{{ $formId }}" name="additional_kg" type="number" min="0" max="9999999.99" step="0.01" required
    value="{{ $rate ? number_format($rate->additional_kg_cents/100,2,'.','') : '' }}" placeholder="0.00">
  <button class="btn btn-primary fin-rate-submit">{{ $rate ? 'Approve rate revision' : 'Approve regional rate' }}</button>
</form>
