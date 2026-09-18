{{-- Renders the same category icon used on the guest homepage, matched by keyword. --}}
@php
  $__catIcons = [
    'pet'         => '<circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 015 5v3.5a3.5 3.5 0 01-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 018 10z"/>',
    'electronics' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    'gadget'      => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
    'phone'       => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
    'computer'    => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    'women'       => '<circle cx="12" cy="4" r="2"/><path d="M9 8h6l2 7h-4v7h-2v-7H7l2-7z"/>',
    'men'         => '<circle cx="12" cy="4" r="2"/><path d="M9 8h6v7h-2v7h-2v-7H9V8z"/>',
    'kid'         => '<ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/>',
    'baby'        => '<ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/>',
    'children'    => '<ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/>',
    'home'        => '<path d="M12 16v-3"/><path d="M12 13c-3 0-5-2-5-5 3 0 5 2 5 5z"/><path d="M12 13c3 0 5-2 5-5-3 0-5 2-5 5z"/><path d="M7 21h10l-1-5H8l-1 5z"/>',
    'garden'      => '<path d="M12 22V12"/><path d="M5 12C5 7 8 4 12 4c4 0 7 3 7 8"/><path d="M5 12c0-3 2-5 7-5"/>',
    'living'      => '<path d="M20 9V7a2 2 0 00-2-2H6a2 2 0 00-2 2v2M2 11h20v8a2 2 0 01-2 2H4a2 2 0 01-2-2v-8z"/><path d="M6 19v2M18 19v2"/>',
    'furniture'   => '<path d="M20 9V7a2 2 0 00-2-2H6a2 2 0 00-2 2v2M2 11h20v8a2 2 0 01-2 2H4a2 2 0 01-2-2v-8z"/><path d="M6 19v2M18 19v2"/>',
    'food'        => '<path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
    'drink'       => '<path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/>',
    'beverage'    => '<path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/>',
    'health'      => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'beauty'      => '<rect x="9" y="14" width="6" height="8" rx="1"/><rect x="8" y="10" width="8" height="4" rx="1"/><path d="M10 10V6c0-1 .5-3 2-4 1.5 1 2 3 2 4v4"/>',
    'cosmetic'    => '<rect x="9" y="14" width="6" height="8" rx="1"/><rect x="8" y="10" width="8" height="4" rx="1"/><path d="M10 10V6c0-1 .5-3 2-4 1.5 1 2 3 2 4v4"/>',
    'automotive'  => '<path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    'vehicle'     => '<path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    'fashion'     => '<path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/>',
    'clothing'    => '<path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/>',
    'apparel'     => '<path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/>',
    'sports'      => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M7 5H4a3 3 0 003 3"/><path d="M17 5h3a3 3 0 01-3 3"/>',
    'outdoors'    => '<path d="M3 17l4-8 4 5 3-3 4 6H3z"/><circle cx="18" cy="5" r="2"/>',
    'fitness'     => '<path d="M6 4v6M18 4v6M4 7h4M16 7h4M6 20v-6M18 20v-6M4 17h4M16 17h4M10 11h4v2h-4z"/>',
    'toys'        => '<path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14.5c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.83 8 21v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><rect x="2" y="10" width="20" height="4" rx="2"/>',
    'games'       => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4M15 11h.01M17 13h.01"/>',
    'books'       => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>',
    'book'        => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>',
    'stationery'  => '<line x1="18" y1="2" x2="22" y2="6"/><path d="M7.5 20.5L19 9l-4-4L3.5 16.5 2 22l5.5-1.5z"/><line x1="15" y1="5" x2="19" y2="9"/>',
    'school'      => '<line x1="18" y1="2" x2="22" y2="6"/><path d="M7.5 20.5L19 9l-4-4L3.5 16.5 2 22l5.5-1.5z"/>',
    'music'       => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'art'         => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 011.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
    'craft'       => '<path d="M2 13.5V20a2 2 0 002 2h16a2 2 0 002-2v-6.5"/><path d="M22 10l-5.5-8h-9L2 10h20z"/><path d="M12 2v8"/><path d="M2 10h20"/>',
  ];
  $__default = '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>';
  $__lower = strtolower($name ?? '');
  $__paths = $__default;
  foreach ($__catIcons as $__key => $__svg) {
    if (str_contains($__lower, $__key)) { $__paths = $__svg; break; }
  }
  $__size = $size ?? 22;
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $__size }}" height="{{ $__size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $__paths !!}</svg>
