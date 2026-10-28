@if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
