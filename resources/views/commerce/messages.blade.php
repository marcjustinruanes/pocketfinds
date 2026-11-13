@if(session('success'))
  @if(auth()->check() && auth()->user()->account_type === 'seller' && session('success') === 'Voucher created.')
    @push('scripts')
    <script>showToast(@json(session('success')));</script>
    @endpush
  @else
    <p class="notice" role="status">{{ session('success') }}</p>
  @endif
@endif
@if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
