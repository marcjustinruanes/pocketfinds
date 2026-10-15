@php
    $editingHub = isset($addressHub);
    $formId = $editingHub ? 'hub-address-'.$addressHub->id : 'new-hub';
    $street = $editingHub ? ($addressHub->address ?? '') : old('address_line', '');
    $savedBarangay = '';
    $contact = (string) ($editingHub ? $addressHub->contact_no : old('contact_no', ''));
    $contactSuffix = preg_match('/^09[0-9]{9}$/D', $contact) ? substr($contact, 2) : '';
    if ($editingHub) {
        $suffix = ', '.$addressHub->municipality.', '.$addressHub->province;
        if (str_ends_with($street, $suffix)) {
            $parts = explode(', ', substr($street, 0, -strlen($suffix)));
            $savedBarangay = array_pop($parts);
            $street = implode(', ', $parts);
        }
    }
@endphp
<div class="logistics-form-grid" data-hub-address
     data-province="{{ $editingHub ? $addressHub->province : '' }}"
     data-municipality="{{ $editingHub ? $addressHub->municipality : '' }}"
     data-barangay="{{ $savedBarangay }}" data-fixed="{{ $editingHub ? '1' : '0' }}"
     data-old-province="{{ !$editingHub ? old('province_code') : '' }}"
     data-old-municipality="{{ !$editingHub ? old('municipality_code') : '' }}"
     data-old-barangay="{{ !$editingHub ? old('barangay_code') : '' }}">
    <label for="{{ $formId }}-name">Hub name *<input id="{{ $formId }}-name" name="name" value="{{ $editingHub ? $addressHub->name : old('name') }}" required maxlength="255"></label>
    <label for="{{ $formId }}-contact">Contact number *
        <span style="display:flex;align-items:center;border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--surface)">
            <span style="padding:10px 12px;border-right:1px solid var(--border);font-weight:600" aria-hidden="true">09</span>
            <input id="{{ $formId }}-contact" type="tel" inputmode="numeric" pattern="[0-9]{9}" minlength="9" maxlength="9" placeholder="XXXXXXXXX" value="{{ $contactSuffix }}" required data-hub-contact aria-label="Contact number: remaining nine digits after 09" style="border:0;border-radius:0;min-width:0">
        </span>
        <input type="hidden" name="contact_no" value="" data-hub-contact-full>
    </label>
    <label for="{{ $formId }}-province">Province *<select id="{{ $formId }}-province" @if(!$editingHub) name="province_code" @endif data-province-select required disabled><option value="">Loading provinces...</option></select></label>
    <label for="{{ $formId }}-municipality">Municipality / City *<select id="{{ $formId }}-municipality" @if(!$editingHub) name="municipality_code" @endif data-municipality-select required disabled><option value="">Select a province first</option></select></label>
    @if($editingHub)<input type="hidden" name="province_code" data-province-code><input type="hidden" name="municipality_code" data-municipality-code>@endif
    <label for="{{ $formId }}-barangay">Barangay *<select id="{{ $formId }}-barangay" name="barangay_code" data-barangay-select required disabled><option value="">Select a municipality first</option></select></label>
    <label for="{{ $formId }}-street">Street / building address *<input id="{{ $formId }}-street" name="address_line" value="{{ $street }}" required maxlength="200" placeholder="Building, house number, street"></label>
    @if($editingHub)<label for="{{ $formId }}-operations">Hub operations *<select id="{{ $formId }}-operations" name="is_operational" required><option value="1" @selected($addressHub->is_operational)>Operational</option><option value="0" @selected(!$addressHub->is_operational)>Closed</option></select></label>@endif
    <div><p class="logistics-note" data-address-status role="status" aria-live="polite">Loading address choices...</p><button type="button" class="btn btn-sm btn-outline" data-address-retry hidden>Retry address loading</button>@if($editingHub)<p class="logistics-note">Province and municipality are fixed to preserve this hub's parcel routes.</p>@endif</div>
</div>
