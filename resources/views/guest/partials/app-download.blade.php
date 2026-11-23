@if(is_file(storage_path('app/releases/pocketfinds.apk')))
<section class="app-download" aria-labelledby="app-download-title">
  <div>
    <h2 id="app-download-title">Take PocketFinds with you</h2>
    <p>Download the Android app and keep your finds close.</p>
    <small>Android APK · {{ round(filesize(storage_path('app/releases/pocketfinds.apk')) / 1048576) }} MB · Demo network required</small>
  </div>
  <div class="app-download-action">
    <a href="{{ route('app.download.android', [], false) }}" download="PocketFinds.apk">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4"/></svg>
      Download for Android
    </a>
    <small>Open the downloaded file to install. If prompted, allow installation from your browser.</small>
  </div>
</section>
<style>
.app-download{display:flex;align-items:center;justify-content:space-between;gap:28px;margin:32px 0 0;padding:28px;border:1px solid #eadce5;border-radius:16px;background:#fff5fa;color:#593346}
.app-download h2{margin:0 0 8px;font-size:22px;line-height:1.3}
.app-download p{margin:0 0 8px;font-size:14px;line-height:1.6}
.app-download small{display:block;color:#7e6470;font-size:12px;line-height:1.6}
.app-download-action{max-width:340px}
.app-download-action a{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:13px 20px;border-radius:10px;background:#bf3c7d;color:#fff!important;font-size:14px;font-weight:700;text-decoration:none!important}
.app-download-action a:hover{background:#9f2e66}
.app-download-action a:focus-visible{outline:3px solid #593346;outline-offset:4px}
.app-download-action small{margin-top:10px}
@media(max-width:600px){.app-download{align-items:stretch;flex-direction:column;padding:22px;gap:18px}.app-download-action{max-width:none}.app-download-action a{display:flex}}
</style>
@endif
