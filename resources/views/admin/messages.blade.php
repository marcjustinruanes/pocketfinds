@extends(request()->boolean('embedded') ? 'seller.messages-embedded-layout' : 'admin.layout')
@section('title','Messages')
@section('page-title','Messages')
@section('content')
@include('account.partials.conversation-workspace', ['messageRole'=>'admin','buyer'=>$selectedUser,'admin'=>null,'conversations'=>collect(),'messageContacts'=>$users,'sellerProducts'=>collect(),'buyerOrders'=>collect(),'autoAttachOrder'=>null])
@endsection
