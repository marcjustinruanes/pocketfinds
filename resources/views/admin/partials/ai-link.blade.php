@if(config('features.admin_ai') && (($aiMode ?? '') !== 'seller' || config('features.admin_ai_review')))
<div class="admin-ai-link" style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <a class="btn btn-sm btn-outline" href="{{ route('admin.ai', ['mode' => $aiMode]) }}"><x-admin-icon name="chart" /> {{ $aiLabel }}</a>
</div>

@endif
