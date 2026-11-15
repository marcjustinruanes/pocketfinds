@extends(request()->boolean('embedded') ? 'seller.messages-embedded-layout' : 'logistics.layout')
@section('title','Messages')
@section('page-title','Messages')
@section('content')
@include('account.partials.conversation-workspace', ['messageRole'=>'logistics','buyer'=>$activeUser,'admin'=>null,'conversations'=>collect(),'messageContacts'=>$admins->concat($couriers)->concat($sellers)->unique('id')->sortByDesc(fn($contact)=>$contact->last_message?->created_at),'sellerProducts'=>collect(),'buyerOrders'=>collect(),'autoAttachOrder'=>null])
@endsection
