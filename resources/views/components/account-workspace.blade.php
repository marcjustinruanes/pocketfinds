@props(['sections' => null, 'role' => 'buyer', 'salaryAvailable' => false, 'companyAvailable' => false])
@php
  $sections ??= \App\Support\AccountWorkspace::sections($role, $salaryAvailable, $companyAvailable);
@endphp
<div class="account-layout" id="accountLayout" data-account-role="{{ $role }}">
  <script type="application/json" data-account-sections>@json($sections)</script>
  <aside class="account-sidebar" aria-label="Account navigation">
    <div class="profile-summary">
      <x-user-avatar :user="auth()->user()" size="64" class="profile-summary-avatar" />
      <div class="profile-summary-info">
        <h1>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</h1>
        <p data-profile-username>{{ auth()->user()->username }}</p>
        <div class="profile-summary-badges"></div>
      </div>
    </div>
    <nav class="account-section-nav" aria-label="My account sections">
      @foreach($sections as $anchor => $section)
        <a href="#{{ $anchor }}" data-account-section="{{ $anchor }}" @if($loop->first) aria-current="page" @endif>
          @include('buyer.partials.icon', ['name'=>$section['icon'], 'size'=>18])
          <span class="account-nav-label">{{ $section['title'] }}</span>
          @if(isset($section['count']))<span class="account-count">{{ $section['count'] }}</span>@endif
        </a>
      @endforeach
    </nav>
  </aside>
  <div class="account-main">
    {{ $slot }}
  </div>
</div>
