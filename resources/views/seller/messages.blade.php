@extends(request()->boolean('embedded') ? 'seller.messages-embedded-layout' : 'seller.layout')
@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
@include('account.partials.conversation-workspace')
@endsection
