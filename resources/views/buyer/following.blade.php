@extends('buyer.layout')
@section('title', 'Following')
@section('page-title', 'Following stores')
@section('page-sub', 'Stores you follow')
@section('content')
<div class="stack">
@forelse($follows as $follow)
  @if($follow->seller)
  <article class="card" data-following-seller="{{ $follow->seller_id }}"><div class="card-pad">
    <a href="{{ route('buyer.shop', $follow->seller->username) }}">{{ $follow->seller->business_name ?? $follow->seller->given_names }}</a>
  </div></article>
  @endif
@empty<p>You are not following any stores yet.</p>@endforelse
</div>
@endsection
