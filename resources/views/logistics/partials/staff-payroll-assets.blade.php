@push('head')
<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">
<link rel="stylesheet" href="{{ asset('css/logistics-finance-navigation.css') }}?v={{ filemtime(public_path('css/logistics-finance-navigation.css')) }}">
<link rel="stylesheet" href="{{ asset('css/staff-payroll.css') }}?v={{ filemtime(public_path('css/staff-payroll.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/staff-payroll.js') }}" defer></script><script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
