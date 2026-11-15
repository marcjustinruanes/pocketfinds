@section('body-class', 'buyer-pages buyer-account-page role-account-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/buyer-account.css') }}?v={{ filemtime(public_path('css/buyer-account.css')) }}">
<link rel="stylesheet" href="{{ asset('css/role-account.css') }}?v={{ filemtime(public_path('css/role-account.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/role-account-workspace.js') }}?v={{ filemtime(public_path('js/role-account-workspace.js')) }}" defer></script>
<script src="{{ asset('js/buyer-account-form.js') }}?v={{ filemtime(public_path('js/buyer-account-form.js')) }}" defer></script>
<script src="{{ asset('js/buyer-account-panels.js') }}?v={{ filemtime(public_path('js/buyer-account-panels.js')) }}" defer></script>
<script src="{{ asset('js/account-profile-photo.js') }}?v={{ filemtime(public_path('js/account-profile-photo.js')) }}" defer></script>
@endpush
