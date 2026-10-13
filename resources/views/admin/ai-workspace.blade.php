@extends('admin.layout')
@section('title', 'AI Workspace')
@section('page-title', 'AI Workspace')
@section('page-sub', 'Reviews, insights and drafts')
@push('head')
<link rel="stylesheet" href="{{ asset('css/admin-ai.css') }}?v={{ filemtime(public_path('css/admin-ai.css')) }}">
@endpush
@section('content')
<div class="admin-ai" data-endpoint="{{ route('admin.ai.analyze') }}" data-clear="{{ route('admin.ai.clear') }}" data-photo="{{ route('admin.ai.photo', ['product' => '__PRODUCT__']) }}" data-order="{{ route('admin.ai.order', ['order' => '__ORDER__']) }}">
  <div class="ai-heading">
    <div><h2>Admin assistant</h2><p>Verify evidence before taking action.</p></div>
    <span class="ai-connection {{ $aiAvailable ? 'is-ready' : '' }}">{{ $aiAvailable ? 'Groq configured' : 'AI not configured' }}</span>
  </div>
  <div class="ai-tabs" role="tablist" aria-label="AI tasks">
    @foreach(['dashboard' => 'Overview', 'analytics' => 'Sales insights', 'inventory' => 'Inventory', 'anomalies' => 'Review alerts', 'reports' => 'Complaints', 'seller' => 'Seller review', 'product' => 'Product screening', 'product_copy' => 'Product copy', 'draft' => 'Drafts', 'chat' => 'Ask dashboard'] as $key => $label)
      <button type="button" role="tab" id="ai-tab-{{ $key }}" aria-controls="ai-task-form" aria-selected="false" data-mode="{{ $key }}">{{ $label }}</button>
    @endforeach
  </div>
  <div class="ai-workbench">
    <form id="ai-task-form" class="ai-inputs" role="tabpanel" data-initial-mode="{{ $selection['mode'] ?? 'dashboard' }}">
      <h3 id="ai-task-title">Overview</h3>
      <fieldset class="ai-chat-engine" data-for="chat" hidden><legend>Reply mode</legend><label><input type="radio" name="adminChatEngine" value="automated" @checked(($chatEngine ?? 'automated') === 'automated')> Automated</label><label><input type="radio" name="adminChatEngine" value="ai" @checked(($chatEngine ?? 'automated') === 'ai')> AI chat</label></fieldset>
      <div class="ai-field" data-for="dashboard reports chat analytics inventory anomalies">
        <label for="aiDays">Reporting window</label>
        <select id="aiDays" name="days"><option value="1">Today</option><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option></select>
        <a id="aiExport" class="btn btn-sm btn-outline" href="{{ route('admin.ai.export', ['days' => 30]) }}" data-url="{{ route('admin.ai.export') }}"><x-admin-icon name="download" /> Download data report</a>
      </div>
      <div class="ai-field" data-for="product product_copy" hidden>
        <label for="aiProduct">Product</label>
        <select id="aiProduct" name="product_id">
          <option value="">Choose a product</option>
          @foreach($products as $product)
            <option value="{{ $product->id }}" @selected(($selection['product_id'] ?? '') === $product->id)>{{ $product->name }} ({{ $product->status }})</option>
          @endforeach
        </select>
        <img class="ai-product-preview" id="aiProductPreview" alt="Selected product photo" hidden>
        <label class="ai-check"><input type="checkbox" name="include_photo" id="aiPhoto"> Include first available product photo</label>
        <p class="ai-note">Screening cannot certify safety, legality or authenticity. Images are shared with Groq only when selected.</p>
      </div>
      <div class="ai-field" data-for="seller" hidden>
        <label for="aiSeller">Seller application</label>
        <select id="aiSeller" name="seller_id">
          <option value="">Choose a seller</option>
          @foreach($sellers as $seller)
            <option value="{{ $seller->id }}" @selected((string)($selection['seller_id'] ?? '') === (string)$seller->id)>{{ $seller->business_name ?: 'Shop #'.$seller->id }} ({{ $seller->status }})</option>
          @endforeach
        </select>
        <p class="ai-note">Document presence only. IDs, selfies and permits are not sent or authenticated by AI.</p>
      </div>
      <div class="ai-field" data-for="draft" hidden>
        <label for="aiAudience">Audience</label>
        <select id="aiAudience" name="audience"><option value="all">All users</option><option value="buyer">Buyers</option><option value="seller">Sellers</option><option value="rider">Riders</option></select>
      </div>
      <div class="ai-field">
        <label for="aiMessage" id="aiMessageLabel">Focus (optional)</label>
        <textarea id="aiMessage" name="message" rows="4" maxlength="1500" placeholder="What should the summary focus on?"></textarea>
      </div>
      <div class="ai-question-suggestions" data-for="chat" hidden>
        <button type="button" data-question="Which shops have the most delivered or completed order value this month?">Top shops this month</button>
        <button type="button" data-question="What needs the admin's attention right now?">What needs attention?</button>
      </div>
      <label class="ai-check ai-consent"><input type="checkbox" id="aiConsent" required> Allow this task's selected data to be sent to Groq</label>
      <p class="ai-note" id="aiEngineNote">Free-tier quotas apply. Don't include passwords, payment details or private documents.</p>
      <button class="btn btn-primary" type="submit" id="aiRun"><x-admin-icon name="chart" /><span>Generate summary</span></button>
      <p id="aiStatus" role="status" aria-live="polite"></p>
    </form>
    <div class="ai-output" aria-busy="false">
      <div class="ai-empty" id="aiEmpty"><img src="{{ asset('images/logo.png') }}" width="42" height="42" alt=""><h3>Ready for review</h3></div>
      <section id="aiResult" hidden aria-label="AI analysis">
        <div class="ai-result-head"><h3>Analysis</h3><span id="aiRisk" hidden></span></div>
        <p id="aiScope" class="ai-note"></p>
        <p id="aiSummary"></p>
        <section id="aiEvidence" class="ai-result-section" hidden aria-label="Computed business evidence"></section>
        <div id="aiFindings"></div>
        <section id="aiLimitsSection" class="ai-result-section" hidden><h4>Limitations</h4><ul id="aiLimitations"></ul></section>
        <div id="aiSources" class="ai-sources"></div>
        <details class="ai-snapshot"><summary>Database snapshot</summary><pre id="aiSnapshot"></pre></details>
      </section>
      <section id="aiDraft" class="ai-draft" hidden>
        <h3>Announcement draft</h3>
        <form method="POST" action="{{ route('admin.ai.prepare-draft') }}">
          @csrf
          <label for="aiDraftTitle">Title</label><input id="aiDraftTitle" name="title" maxlength="255" required>
          <label for="aiDraftBody">Message</label><textarea id="aiDraftBody" name="body" rows="7" maxlength="3000" required></textarea>
          <input type="hidden" name="audience" id="aiDraftAudience">
          <div class="ai-draft-actions"><button class="btn btn-primary" type="submit"><x-admin-icon name="edit" /> Review in announcements</button><button class="btn btn-outline" type="button" id="aiCopyDraft"><x-admin-icon name="file" /> Copy draft</button></div>
        </form>
        <p class="ai-note">Not published. Check all dates, claims and placeholders before posting.</p>
      </section>
      <section id="aiProductCopy" class="ai-draft" hidden>
        <h3>Product copy draft</h3>
        <label for="aiCopyTitle">Title</label><input id="aiCopyTitle" maxlength="255">
        <label for="aiCopyBody">Description</label><textarea id="aiCopyBody" rows="7" maxlength="3000"></textarea>
        <label for="aiCopyTags">Suggested tags</label><input id="aiCopyTags">
        <label for="aiCopyCategory">Suggested category</label><input id="aiCopyCategory">
        <div class="ai-draft-actions"><button class="btn btn-outline" type="button" id="aiCopyProduct"><x-admin-icon name="file" /> Copy reviewed draft</button><a class="btn btn-outline" href="{{ route('admin.products') }}">Review products</a></div>
        <p class="ai-note">Draft only. Verify specifications and ask the seller to update the listing before approval.</p>
      </section>
      <section class="ai-conversation" data-for="chat" hidden>
        <div class="ai-result-head"><h3>Conversation</h3><button class="btn btn-sm btn-outline" type="button" id="aiClearChat"><x-admin-icon name="trash" /> Clear</button></div>
        <div id="aiChat" role="log" aria-live="polite">@foreach($chatHistory as $turn)<p data-role="{{ $turn['role'] }}">{{ $turn['content'] }}</p>@endforeach</div>
      </section>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/admin-ai.js') }}?v={{ filemtime(public_path('js/admin-ai.js')) }}" defer></script>
@endpush
