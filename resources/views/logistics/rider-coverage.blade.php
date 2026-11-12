@extends('logistics.layout')
@section('title', 'Rider Barangay Coverage')
@section('page-title', 'Rider Barangay Coverage')
@section('page-sub', 'Manage pickup and delivery barangays within the rider assigned hub')
@section('content')
<a class="btn btn-outline" href="{{ route('logistics.riders') }}">Back to riders</a>
<section class="card card-pad" style="margin-top:16px">
  <h2>{{ $rider->given_names }} {{ $rider->last_name }}</h2>
  <p>{{ $rider->logisticsHub?->municipality ?? 'No hub assigned' }}, {{ $rider->logisticsHub?->province }}</p>
  <p>Select one or more barangays. Clearing all selections removes this rider from pickup and delivery selection. Coverage must be reassigned after a hub change.</p>
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  @if($coverageError)
    <p role="alert">{{ $coverageError }}</p><a class="btn btn-outline" href="{{ url()->current() }}">Retry</a>
  @else
    <form method="POST" action="{{ route('logistics.riders.coverage.update', $rider->id) }}">
      @csrf @method('PUT')
      <input type="hidden" name="hub_id" value="{{ $rider->logistics_hub_id }}">
      <input type="hidden" name="version" value="{{ $version }}">
      <fieldset><legend>Barangay coverage</legend>
        @foreach($choices as $choice)
          <label style="display:block;padding:8px"><input type="checkbox" name="barangays[]" value="{{ $choice['code'] }}" @checked(in_array($choice['code'], old('barangays', $selected), true))> {{ $choice['name'] }}</label>
        @endforeach
      </fieldset>
      <button class="btn btn-primary" type="submit">Save coverage</button>
    </form>
  @endif
</section>
@endsection
