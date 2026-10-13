@extends('admin.layout')
@section('title', 'FAQs')
@section('page-title', 'FAQs')
@section('page-sub', 'Buyer and admin support')
@push('head')<link rel="stylesheet" href="{{ asset('css/support-faqs.css') }}">@endpush
@section('content')
<section class="support-faqs">
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  <nav class="faq-tabs" aria-label="FAQ audience"><a href="{{ route('admin.faqs', ['audience' => 'admin']) }}" aria-current="{{ $audience === 'admin' ? 'page' : 'false' }}">Admin FAQs</a><a href="{{ route('admin.faqs', ['audience' => 'buyer']) }}" aria-current="{{ $audience === 'buyer' ? 'page' : 'false' }}">Buyer FAQs</a></nav>
  <div class="faq-management">
    <div>
      @forelse($faqs as $faq)
      <article class="faq-entry" id="faq-{{ $faq->id }}"><div class="faq-entry-head"><strong>{{ $faq->question }}</strong><span>{{ $faq->is_published ? 'Published' : 'Draft' }}</span></div><p>{{ $faq->answer }}</p>
        <div class="faq-actions"><a class="btn btn-outline" href="{{ route('admin.faqs', ['audience' => $audience, 'edit' => $faq->id]) }}" aria-label="Edit FAQ" title="Edit FAQ"><x-admin-icon name="edit" /></a>
          <details><summary>Delete</summary><form method="POST" action="{{ route('admin.faqs.delete', $faq) }}">@csrf @method('DELETE')<p>Delete this FAQ?</p><button class="btn btn-danger" type="submit">Confirm deletion</button></form></details>
        </div>
      </article>
      @empty<p>No {{ $audience }} FAQs yet.</p>@endforelse
      {{ $faqs->links() }}
    </div>
    <form class="faq-editor" method="POST" action="{{ $editing ? route('admin.faqs.update', $editing) : route('admin.faqs.store') }}">
      @csrf @if($editing) @method('PUT') @endif
      <h2>{{ $editing ? 'Edit FAQ' : 'New FAQ' }}</h2>
      <label for="faqAudience">Audience</label><select id="faqAudience" name="audience">@foreach(['buyer' => 'Buyer', 'admin' => 'Admin'] as $value => $label)<option value="{{ $value }}" @selected(old('audience', $editing?->audience ?? $audience) === $value)>{{ $label }}</option>@endforeach</select>
      <label for="faqQuestion">Question</label><input id="faqQuestion" name="question" maxlength="255" value="{{ old('question', $editing?->question) }}" required>
      <label for="faqAnswer">Answer</label><textarea id="faqAnswer" name="answer" rows="6" maxlength="3000" required>{{ old('answer', $editing?->answer) }}</textarea>
      <label for="faqKeywords">Match keywords, comma separated</label><input id="faqKeywords" name="keywords_text" maxlength="500" value="{{ old('keywords_text', implode(', ', $editing?->keywords ?? [])) }}">
      <label for="faqSort">Sort order</label><input id="faqSort" name="sort_order" type="number" min="0" max="9999" step="1" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}" required>
      <input type="hidden" name="is_published" value="0"><label class="faq-check"><input name="is_published" type="checkbox" value="1" @checked(old('is_published', $editing?->is_published ?? false))> Published</label>
      <div class="faq-actions"><button class="btn btn-primary" type="submit">Save FAQ</button>@if($editing)<a href="{{ route('admin.faqs', ['audience' => $audience]) }}">Cancel</a>@endif</div>
    </form>
  </div>
</section>
@endsection
