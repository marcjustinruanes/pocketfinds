@php
  $filterOptions = $filterOptions ?? ['all'=>'All types','buyer'=>'Buyers','seller'=>'Sellers','rider'=>'Riders','logistics'=>'Logistics'];
@endphp
<span class="platform-column-filter">
  <span>{{ $filterLabel ?? 'Type' }}</span>
  <svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>
  <select aria-label="Filter by {{ strtolower($filterLabel ?? 'type') }}" data-platform-filter="{{ $filterField ?? 'accountType' }}" data-filter-table="{{ $filterTable }}">
    @foreach($filterOptions as $value=>$label)
      <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
  </select>
</span>
