@extends('buyer.layout')
@section('title', 'FAQs')
@section('page-title', 'Buyer FAQs')
@push('head')<link rel="stylesheet" href="{{ asset('css/support-faqs.css') }}">@endpush
@section('content')
<section class="support-faqs" aria-label="Buyer frequently asked questions">
  @if(request()->filled('faq'))<a href="{{ route('buyer.faqs') }}">All FAQs</a>@endif
  @forelse($faqs as $faq)
  <details id="faq-{{ $faq->id }}" @if(request()->filled('faq')) open @endif><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>
  @empty<p>No buyer FAQs have been published yet.</p>@endforelse
  {{ $faqs->links() }}
</section>
@endsection
