{{-- PocketFinds brand mark: a pocket with a small find glinting inside it — a deliberate
     departure from the generic shopping-bag icon this project used to duplicate inline in
     16 different files. Single source of truth now: every place the wordmark appears calls
     this component, so the brand only ever needs to change in one place. --}}
@props(['size' => 18])
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="PocketFinds">
  <path d="M5 9.5a3 3 0 013-3h8a3 3 0 013 3v7.5a3 3 0 01-3 3H8a3 3 0 01-3-3V9.5z"/>
  <path d="M8 6.5l1.8-3h4.4l1.8 3"/>
  <path d="M12 9.2l1.1 2.6 2.6 1.1-2.6 1.1-1.1 2.6-1.1-2.6-2.6-1.1 2.6-1.1z" fill="currentColor" stroke="none"/>
</svg>
