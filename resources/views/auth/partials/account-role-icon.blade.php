<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size ?? 22 }}" height="{{ $size ?? 22 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@if($role === 'seller')
    <path d="M3 10v11h18V10M2 10l2-7h16l2 7"/>
    <path d="M2 10a3 3 0 0 0 5 2 3 3 0 0 0 5 0 3 3 0 0 0 5 0 3 3 0 0 0 5-2M9 21v-7h6v7"/>
@else
    <path d="M3 21V8l9-5 9 5v13M2 21h20M7 21V11h10v10M7 14h10M7 17h10"/>
@endif
</svg>
