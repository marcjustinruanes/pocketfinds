@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
<section class="card" style="margin-bottom:18px">
  <div class="card-head"><h2>Profile photo</h2></div>
  <div class="card-pad">
    <form method="POST" action="{{ route('account.photo') }}" enctype="multipart/form-data" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      @csrf <x-user-avatar :user="auth()->user()" size="56" />
      <label>Choose photo<input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" required></label>
      <button class="btn btn-primary">Save photo</button>
      <small>JPG, PNG or WEBP · maximum 5 MB</small>
    </form>
    @error('profile_picture')<p role="alert" style="color:var(--danger)">{{ $message }}</p>@enderror
  </div>
</section>
