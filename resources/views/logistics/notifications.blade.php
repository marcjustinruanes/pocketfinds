@extends('logistics.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'System alerts and updates')

@section('content')
<div class="card card-pad">
  <div class="empty">
    <div class="ic"><x-admin-icon name="bell" /></div>
    <h3>No notifications</h3>
    <p>You're all caught up.</p>
  </div>
</div>
@endsection
