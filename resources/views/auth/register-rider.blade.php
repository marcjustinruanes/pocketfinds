<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Registration — PocketFinds</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <style>
        .prefilled-note { font-size:11px;color:var(--auth-primary);margin:0 0 14px;padding:6px 12px;background:var(--auth-primary-soft);border-radius:8px;border-left:3px solid var(--auth-primary); }
        .img-lightbox { display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center; }
        .img-lightbox.open { display:flex; }
        .img-lightbox img { max-width:90vw;max-height:90vh;border-radius:8px; }
        .img-lightbox-close { position:absolute;top:16px;right:20px;color:#fff;font-size:28px;cursor:pointer;line-height:1;background:none;border:none; }
        .enlarge-btn { position:absolute;top:6px;left:6px;background:rgba(0,0,0,.45);color:#fff;border:none;border-radius:6px;padding:3px 6px;cursor:pointer;display:flex;align-items:center; }
        .ocr-result { margin-top:8px;padding:8px 10px;border-radius:8px;font-size:11px;line-height:1.5;display:none; }
        .ocr-result.checking { display:block;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
        .ocr-result.match { display:block;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0; }
        .ocr-result.mismatch { display:block;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa; }
        .vehicle-type-card { display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 8px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;color:#374151;background:#fff;transition:border-color .15s,background .15s,color .15s;text-align:center; }
        input[type="radio"]:checked + .vehicle-type-card { border-color:var(--auth-primary);background:var(--auth-primary-soft);color:var(--auth-primary); }
        .ownership-card { display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 10px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;color:#374151;background:#fff;transition:border-color .15s,background .15s,color .15s;text-align:center; }
        input[type="radio"]:checked + .ownership-card { border-color:var(--auth-primary);background:var(--auth-primary-soft);color:var(--auth-primary); }
        /* ── Company & Hub step — same rich picker as register-logistics-staff.blade.php ── */
        .company-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-top:6px; }
        .company-option-label { display:block;margin:0;cursor:pointer; }
        .company-card { position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px 10px;min-height:118px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;font-size:12px;font-weight:700;color:#374151;background:#fff;transition:all .18s ease;text-align:center;box-sizing:border-box;user-select:none; }
        .company-card:hover { border-color:#cbd5e1;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.04); }
        .company-card img { width:42px;height:42px;border-radius:8px;object-fit:cover;display:block; }
        .company-card svg { color:#94a3b8; }
        .company-card .company-title { line-height:1.25;word-break:break-word; }
        .company-card.selected, input[type="radio"]:checked + .company-card { border-color:var(--auth-primary) !important;background:var(--auth-primary-soft) !important;color:var(--auth-primary) !important;box-shadow:0 0 0 1.5px var(--auth-primary) !important; }
        .company-card.disabled { opacity:0.45 !important;cursor:not-allowed !important;background:#f8fafc !important;border:1.5px dashed #cbd5e1 !important;color:#94a3b8 !important;pointer-events:none !important;transform:none !important;box-shadow:none !important; }
        .company-card.disabled img { filter:grayscale(1);opacity:0.35; }
        .company-tag { font-size:9.5px;font-weight:700;padding:2.5px 7px;border-radius:6px;line-height:1.2;text-align:center;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .company-tag.tag-hiring { background:#dcfce7;color:#15803d;border:1px solid #bbf7d0; }
        .company-tag.tag-not-hiring { background:#fef3c7;color:#b45309;border:1px solid #fde68a; }
        .company-tag.tag-no-hubs { background:#fee2e2;color:#dc2626;border:1px solid #fecaca; }

        .hub-section-wrapper { margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0; }
        .selected-hub-card { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1.5px solid #86efac;border-radius:12px;background:#f0fdf4;transition:all .2s ease; }
        .selected-hub-card.nearby-chosen { border-color:var(--auth-primary);background:var(--auth-primary-soft); }
        .selected-hub-main { display:flex;align-items:center;gap:10px;min-width:0; }
        .selected-hub-icon { width:34px;height:34px;border-radius:10px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .selected-hub-card.nearby-chosen .selected-hub-icon { background:#fff;color:var(--auth-primary);box-shadow:0 2px 6px rgba(217,70,143,.12); }
        .selected-hub-details { min-width:0; }
        .selected-hub-header { display:flex;align-items:center;gap:6px;flex-wrap:wrap; }
        .selected-hub-name { font-size:13.5px;font-weight:700;color:#166534;line-height:1.25; }
        .selected-hub-card.nearby-chosen .selected-hub-name { color:var(--auth-primary); }
        .selected-hub-desc { margin:2px 0 0;font-size:11.5px;color:#15803d;line-height:1.3; }
        .selected-hub-card.nearby-chosen .selected-hub-desc { color:#475569; }
        .btn-change-hub { display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#334155;font-size:11.5px;font-weight:700;padding:6px 12px;cursor:pointer;flex-shrink:0;transition:all .15s ease; }
        .btn-change-hub:hover { border-color:var(--auth-primary);color:var(--auth-primary);background:#fff; }
        .no-hub-selected-card { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1.5px dashed #cbd5e1;border-radius:12px;background:#f8fafc; }
        .no-hub-icon { width:36px;height:36px;border-radius:999px;background:var(--auth-primary-soft);color:var(--auth-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .btn-browse-hubs { display:inline-flex;align-items:center;gap:5px;background:var(--auth-primary);border:none;border-radius:8px;color:#fff;font-size:12px;font-weight:700;padding:7px 14px;cursor:pointer;flex-shrink:0;transition:opacity .15s ease; }
        .btn-browse-hubs:hover { opacity:0.9; }
        .hub-notice { border-radius:10px;padding:10px 12px;display:flex;align-items:flex-start;gap:8px;font-size:11.5px;line-height:1.4; }
        .hub-notice.warning { background:#fff7ed;border:1.5px solid #fed7aa;color:#9a3412; }
        .hub-notice.info { background:#f8fafc;border:1.5px solid #e2e8f0;color:#334155; }

        /* ── Hubs Modal ── */
        .hubs-modal-backdrop { position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.65);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;animation:hubsModalFadeIn .15s ease-out; }
        @keyframes hubsModalFadeIn { from{opacity:0} to{opacity:1} }
        .hubs-modal-dialog { background:#fff;border-radius:16px;width:min(540px,100%);max-height:85vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25);overflow:hidden;animation:hubsModalSlideUp .18s ease-out; }
        @keyframes hubsModalSlideUp { from{transform:translateY(12px) scale(.98)} to{transform:translateY(0) scale(1)} }
        .hubs-modal-header { padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-shrink:0; }
        .hubs-modal-title { margin:0;font-size:16px;font-weight:800;color:#0f172a; }
        .hubs-modal-sub { margin:3px 0 0;font-size:12px;color:#64748b; }
        .hubs-modal-close { background:none;border:none;color:#94a3b8;font-size:24px;line-height:1;cursor:pointer;padding:0;transition:color .15s ease; }
        .hubs-modal-close:hover { color:#0f172a; }
        .hubs-modal-search { padding:10px 20px;border-bottom:1px solid #f1f5f9;background:#fafafa;flex-shrink:0; }
        .hubs-search-wrapper { position:relative;display:flex;align-items:center; }
        .hubs-search-wrapper svg { position:absolute;left:11px;color:#94a3b8;pointer-events:none; }
        .hubs-search-input { width:100%;padding:8px 32px 8px 34px;border:1px solid #e2e8f0;border-radius:8px;font-size:12.5px;background:#fff;outline:none;transition:border-color .15s ease;box-sizing:border-box; }
        .hubs-search-input:focus { border-color:var(--auth-primary);box-shadow:0 0 0 2px var(--auth-primary-soft); }
        .hubs-modal-body { padding:14px 20px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:8px;min-height:120px;max-height:52vh; }
        .hubs-modal-footer { padding:12px 20px;border-top:1px solid #f1f5f9;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0; }
        .hubs-footer-left { font-size:12px;color:#475569;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .hubs-selection-val { color:#0f172a;margin-left:4px; }
        .hubs-footer-actions { display:flex;align-items:center;gap:8px;flex-shrink:0; }
        .btn-hubs-cancel { background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:700;color:#475569;cursor:pointer;transition:all .15s ease; }
        .btn-hubs-cancel:hover { background:#f1f5f9; }
        .btn-hubs-confirm { background:var(--auth-primary);border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:700;color:#fff;cursor:pointer;transition:opacity .15s ease; }
        .btn-hubs-confirm:disabled { opacity:0.5;cursor:not-allowed; }
        .btn-hubs-confirm:not(:disabled):hover { opacity:0.9; }
        .hub-choice-card { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:12px;background:#fff;cursor:pointer;transition:all .15s ease;box-sizing:border-box;user-select:none; }
        .hub-choice-card:hover { border-color:#cbd5e1;transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .hub-choice-card.selected { border-color:var(--auth-primary) !important;background:var(--auth-primary-soft) !important;box-shadow:0 0 0 1.5px var(--auth-primary) !important; }
        .hub-choice-card.direct-match { border-color:#86efac;background:#fff; }
        .hub-choice-card.direct-match.selected { border-color:#16a34a !important;background:#f0fdf4 !important;box-shadow:0 0 0 1.5px #16a34a !important; }
        .hub-choice-card.not-hiring { opacity:0.55 !important;background:#f8fafc !important;border-style:dashed !important;cursor:not-allowed !important;pointer-events:none !important;transform:none !important;box-shadow:none !important; }
        .hub-badge { display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2.5px 8px;border-radius:999px;line-height:1;white-space:nowrap; }
        .hub-badge-hiring { background:#dcfce7;color:#15803d;border:1px solid #bbf7d0; }
        .hub-badge-not-hiring { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
        .hub-badge-regional { background:#fef9c3;color:#854d0e;border:1px solid #fde047; }
        @media (max-width:480px) {
            .company-grid { grid-template-columns:repeat(2,1fr);gap:8px; }
            .company-card { padding:10px 6px;min-height:105px;font-size:11px; }
            .company-card img { width:36px;height:36px; }
            .hub-choice-card { padding:10px 12px; }
        }
        .upload-box { border:2px dashed #e5e7eb;border-radius:10px;min-height:100px;display:flex;align-items:center;justify-content:center;cursor:pointer;background:#f9f9f9;text-align:center;padding:10px;transition:border-color .15s; }
        .upload-box:hover { border-color:var(--auth-primary); }
        .vehicle-details-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .document-upload { min-height:140px; }
        #vehicleDocsSection { margin-top:8px; }
        .license-layout { display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:stretch; }
        .license-fields { display:flex;flex-direction:column;gap:10px; }
        .document-upload .upload-info { display:block;flex-basis:100%;margin-top:6px;font-size:10px;color:var(--auth-muted);line-height:1.35; }
        @media (max-width:640px) { .vehicle-details-grid { grid-template-columns:1fr; } .license-layout { grid-template-columns:1fr; } }

        /* ── Address summary pill (shown once address is already known, e.g. on the Company step) ── */
        .address-summary-pill { margin:12px 0 16px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;justify-content:space-between;gap:10px; }
        .address-summary-left { display:flex;align-items:center;gap:10px;min-width:0; }
        .address-summary-left .pin-icon { width:28px;height:28px;border-radius:8px;background:var(--auth-primary-soft);color:var(--auth-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .location-meta { display:flex;flex-direction:column;min-width:0; }
        .location-label { font-size:10px;font-weight:700;color:var(--auth-muted);text-transform:uppercase;letter-spacing:.05em;line-height:1.2; }
        .location-value { font-size:12.5px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3; }
        .change-address-btn { background:#fff;border:1px solid #cbd5e1;border-radius:6px;color:var(--auth-primary);font-size:11px;font-weight:700;cursor:pointer;padding:4px 10px;flex-shrink:0;transition:all .15s ease; }
        .change-address-btn:hover { background:var(--auth-primary-soft);border-color:var(--auth-primary); }

        /* ── Resume Upload Card ── */
        .resume-upload-card { border:1.5px dashed var(--auth-border,#cbd5e1);border-radius:10px;padding:12px 14px;background:#fafafa;margin-top:12px;transition:border-color .15s ease; }
        .resume-upload-card:hover { border-color:#94a3b8; }
        .resume-idle { display:flex;align-items:center;justify-content:space-between;gap:12px; }
        .resume-idle-left { display:flex;align-items:center;gap:10px;min-width:0; }
        .resume-icon-box { width:36px;height:36px;border-radius:8px;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .btn-resume-browse { display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:8px;font-size:11.5px;font-weight:700;cursor:pointer;flex-shrink:0;transition:all .15s ease; }
        .btn-resume-browse:hover { background:var(--auth-primary-soft); }
        .resume-selected { display:flex;align-items:center;justify-content:space-between;gap:12px; }
        .resume-file-info { min-width:0; }
        .resume-file-name { font-size:12.5px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .resume-file-size { font-size:11px;color:var(--auth-muted); }
        .btn-resume-remove { background:#fee2e2;border:1px solid #fca5a5;color:#ef4444;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;flex-shrink:0; }
        .btn-resume-remove:hover { background:#fecaca; }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><x-brand-logo :size="18" /></span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">Become a courier.</h1>
                <p class="auth-brand-text">Deliver orders for PocketFinds sellers and earn per delivery. Fill in your details, add your vehicle info, and your account will be reviewed by our admin team.</p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Secure &amp; verified registration</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Admin-approved accounts</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> Confirmation sent to your email</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">

                @php
                    $regType = 'rider';
                    $typeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/></svg>';
                @endphp

                {{-- Step indicator: Address(1) Company(2) License(3) Vehicle(4) Personal(5) Contact(6) Account(7) —
                     address comes first so the Company step can immediately show which hub will
                     actually handle this rider's deliveries, instead of a "fill in your address
                     later" placeholder. --}}
                <div class="steps" id="stepIndicator">
                    <div class="step-item active" data-step="5">
                        <div class="step-circle">1</div>
                        <span class="step-label">Address</span>
                    </div>
                    <div class="step-item" data-step="9">
                        <div class="step-circle">2</div>
                        <span class="step-label">Company</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle">3</div>
                        <span class="step-label">License</span>
                    </div>
                    <div class="step-item" data-step="7">
                        <div class="step-circle">4</div>
                        <span class="step-label">Vehicle</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle">5</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle">6</div>
                        <span class="step-label">Contact</span>
                    </div>
                    <div class="step-item" data-step="6">
                        <div class="step-circle">7</div>
                        <span class="step-label">Account</span>
                    </div>
                </div>

                <form id="buyerForm" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="account_type" value="rider">
                    <input type="hidden" name="auth_method" value="{{ ($isGoogleSignup ?? false) ? 'google' : 'manual' }}">
                    <input type="hidden" name="logistics_hub_id" id="selected_hub_id" value="">
                    @if($isGoogleSignup ?? false)
                        <input type="hidden" name="google_id" value="{{ $googleId }}">
                    @endif

                    {{-- ── STEP 5 (UI Step 1): Address ── --}}
                    <div class="step-panel active" id="panel-5">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Address</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 12px">Where are you located? We'll use this to show which hub covers your area on the next step.</p>
                        <div class="address-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="province">Province <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="province" name="province" required>
                                    <option value="" disabled selected>Loading provinces…</option>
                                </select>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="municipality">City / Municipality <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="municipality" name="municipality" required disabled>
                                    <option value="" disabled selected>Select province first</option>
                                </select>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="barangay">Barangay <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="barangay" name="barangay" required disabled>
                                    <option value="" disabled selected>Select city / municipality first</option>
                                </select>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="house_no">House No. / Unit</label>
                                <input class="auth-input" id="house_no" name="house_no" type="text" placeholder="e.g. 123">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="street">Street</label>
                                <input class="auth-input" id="street" name="street" type="text" placeholder="e.g. Rizal St.">
                            </div>
                        </div>
                        <div class="step-nav">
                            <span></span>
                            <button type="button" class="btn-next" onclick="nextStep(5)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 9 (UI Step 2): Choose Your Company ── --}}
                    <div class="step-panel" id="panel-9">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Choose Your Company</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Which logistics company will you deliver for?</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        {{-- Address summary banner --}}
                        <div class="address-summary-pill">
                            <div class="address-summary-left">
                                <div class="pin-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </div>
                                <div class="location-meta">
                                    <span class="location-label">Your Location</span>
                                    <strong class="location-value" id="pillAddressText">—</strong>
                                </div>
                            </div>
                            <button type="button" class="change-address-btn" onclick="prevStep(9)">Change</button>
                        </div>

                        {{-- 1. Company Picker --}}
                        <div class="auth-field full" style="margin-bottom:14px">
                            <label class="auth-label">1. Which company will you deliver for? <span class="auth-required">*</span></label>
                            <div class="company-grid" id="companyGrid">
                                @forelse($logisticsCompanies ?? [] as $i => $lc)
                                <label class="company-option-label" id="companyLabel_{{ $i }}">
                                    <input type="radio" name="business_name" value="{{ $lc->business_name }}" id="lc_{{ $i }}" style="display:none" onchange="onCompanyChange()">
                                    <div class="company-card" data-for="lc_{{ $i }}" id="companyCard_{{ $i }}">
                                        @if($lc->company_logo)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($lc->company_logo) }}" alt="{{ $lc->business_name }}">
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                        @endif
                                        <span class="company-title">{{ $lc->business_name }}</span>
                                        <span class="company-tag" id="companyTag_{{ $i }}" style="display:none"></span>
                                    </div>
                                </label>
                                @empty
                                <p style="grid-column:1/-1;color:var(--auth-muted);font-size:13px">No logistics companies are open for riders to join yet — please check back later or contact support.</p>
                                @endforelse
                            </div>
                            <span id="companyError" style="display:none;color:red;font-size:11px;margin-top:8px">Please choose an available company.</span>
                        </div>

                        {{-- 2. Hub Selection & Hiring Status Section --}}
                        <div id="hubSection" class="hub-section-wrapper" style="display:none">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                                <label class="auth-label" style="margin:0">2. Hub You'll Be Dispatched From <span class="auth-required">*</span></label>
                            </div>
                            <div id="hubNoticeContainer" style="display:none;margin-bottom:10px"></div>
                            <div id="hubSelectionContainer"></div>
                            <span id="hubSelectError" style="display:none;color:red;font-size:11px;margin-top:8px">Please select an available hub to continue.</span>
                        </div>

                        <div class="step-nav" style="margin-top:16px">
                            <button type="button" class="btn-prev" onclick="prevStep(9)">← Back</button>
                            <button type="button" class="btn-next" id="btnStep9Next" onclick="nextStep(9)">Continue →</button>
                        </div>
                    </div>

                    {{-- Company Terms & Conditions content, keyed by company name — swapped into
                         #tcContent by onCompanyChange() below as the rider picks a company. --}}
                    <script type="application/json" id="companyTermsData">{!! str_replace('</script', '<\/script', json_encode(
                        collect($companyPolicies ?? [])->mapWithKeys(fn ($p) => [$p->company_name => $p->content])
                    )) !!}</script>

                    {{-- ── STEP 2 (UI Step 3): License, Selfie & Resume ── --}}
                    <div class="step-panel" id="panel-2">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">License, Selfie &amp; Resume</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Upload your driver's license, take a selfie, and attach your resume.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        {{-- Row 1: License Number + Expiry Date, side by side --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                            <div class="auth-field">
                                <label class="auth-label" for="license_number">License Number <span class="auth-required">*</span></label>
                                <input class="auth-input" id="license_number" name="license_number" type="text" placeholder="e.g. N01-23-456789">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="license_expiry">Expiry Date <span class="auth-required">*</span></label>
                                <input class="auth-input" id="license_expiry" name="license_expiry" type="date">
                            </div>
                        </div>

                        {{-- Row 2: License Photo + Selfie, side by side, below row 1 --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                            <div class="auth-field">
                                <label class="auth-label">License Photo <span class="auth-required">*</span></label>
                                <div class="upload-box document-upload" id="licenseBox" onclick="document.getElementById('license_file').click()" style="min-height:150px;max-height:150px">
                                    <div id="licenseIdle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M14 9h4M14 13h2"/></svg>
                                        <p style="margin:4px 0 0;font-size:11px;color:#888">Upload file</p>
                                        <span class="upload-info">JPG, PNG or PDF · max 5 MB</span>
                                    </div>
                                    <div id="licensePreview" style="display:none;position:relative">
                                        <img id="licenseImg" style="width:100%;max-height:130px;object-fit:cover;border-radius:6px" alt="License">
                                        <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openDocLightbox('license')" aria-label="Enlarge driver's license"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
                                        <button type="button" onclick="event.stopPropagation();clearUpload('license')" style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer">✕</button>
                                    </div>
                                </div>
                                <input type="file" id="license_file" name="license_file" accept="image/*,.pdf" style="display:none" onchange="handleDocUpload('license', this)">
                                <span id="licenseError" style="display:none;color:red;font-size:11px">Please upload your driver's license.</span>
                            </div>

                            <div class="auth-field">
                                <label class="auth-label">Selfie with License <span class="auth-required">*</span></label>
                                <div id="selfieBox" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:150px;max-height:150px;display:flex;flex-direction:column;justify-content:center">
                                    <div id="selfieCamera" style="display:none;position:relative">
                                        <video id="selfieVideo" autoplay playsinline style="width:100%;max-height:150px;object-fit:cover;display:block"></video>
                                        <button type="button" onclick="snapSelfie()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--auth-primary,#e74c3c);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                    <div id="selfiePreview" style="display:none;position:relative">
                                        <img id="selfieImg" style="width:100%;max-height:150px;object-fit:cover;display:block" alt="Selfie">
                                        <button type="button" class="enlarge-btn" onclick="openLightbox('selfieImg')"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
                                        <button type="button" onclick="retakeSelfie()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>Retake</button>
                                    </div>
                                    <div id="selfieIdle" style="padding:12px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:5px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        <p style="margin:0 0 7px;font-size:11px;color:#888">Take a selfie holding your license</p>
                                        <button type="button" id="openCameraBtn" onclick="startCamera()" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid var(--auth-primary,#e74c3c);color:var(--auth-primary,#e74c3c);background:#fff;border-radius:8px;font-size:12px;cursor:pointer"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Open Camera</button>
                                    </div>
                                </div>
                                <canvas id="selfieCanvas" style="display:none"></canvas>
                                <span id="selfieError" style="display:none;color:red;font-size:11px">Please take a selfie.</span>
                            </div>
                        </div>

                        {{-- Resume Upload (Optional) --}}
                        <div class="resume-upload-card" id="resumeCard">
                            <div class="resume-idle" id="resumeIdle">
                                <div class="resume-idle-left">
                                    <div class="resume-icon-box">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    </div>
                                    <div>
                                        <strong style="font-size:12.5px;color:#1e293b;display:block">Resume / Curriculum Vitae <span style="font-weight:400;font-size:11px;color:var(--auth-muted)">(Optional)</span></strong>
                                        <span style="font-size:11px;color:var(--auth-muted)">Attach your CV (.pdf, .doc, .docx · max 5MB)</span>
                                    </div>
                                </div>
                                <label for="resume_file" class="btn-resume-browse">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span>Upload</span>
                                </label>
                            </div>
                            <div class="resume-selected" id="resumeSelected" style="display:none">
                                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                    <div class="resume-icon-box" style="background:var(--auth-primary-soft);color:var(--auth-primary)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    </div>
                                    <div class="resume-file-info">
                                        <div class="resume-file-name" id="resumeFileName">resume.pdf</div>
                                        <div class="resume-file-size" id="resumeFileSize">0 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-resume-remove" onclick="removeResumeFile()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    <span>Remove</span>
                                </button>
                            </div>
                            <input type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx" style="display:none" onchange="handleResumeSelect(this)">
                            <span id="resumeFileError" style="display:none;color:red;font-size:11px;margin-top:4px"></span>
                        </div>

                        <div class="step-nav" style="margin-top:14px">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 7 (UI Step 2): Vehicle Information ── --}}
                    <div class="step-panel" id="panel-7">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Vehicle Information</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 14px">Tell us about the vehicle you'll use for deliveries.</p>

                        {{-- Vehicle Ownership --}}
                        <div class="auth-field" style="margin-bottom:14px">
                            <label class="auth-label">Vehicle Ownership <span class="auth-required">*</span></label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px">
                                <label>
                                    <input type="radio" name="vehicle_ownership" value="own" id="vo_own" style="display:none" onchange="onOwnershipChange()">
                                    <div class="ownership-card" data-for="vo_own">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 3H8l-2 4h12l-2-4z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                                        <span>My Own Vehicle</span>
                                        <span style="font-size:10px;font-weight:400;color:inherit;opacity:.75">I own the vehicle</span>
                                    </div>
                                </label>
                                <label>
                                    <input type="radio" name="vehicle_ownership" value="company" id="vo_company" style="display:none" onchange="onOwnershipChange()">
                                    <div class="ownership-card" data-for="vo_company">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                                        <span>Company Vehicle</span>
                                        <span style="font-size:10px;font-weight:400;color:inherit;opacity:.75">Provided by logistics</span>
                                    </div>
                                </label>
                            </div>
                            <span id="ownershipError" style="display:none;color:red;font-size:11px;margin-top:4px">Please select vehicle ownership.</span>
                        </div>

                        {{-- Vehicle Type --}}
                        @php
                            $vehicleTypeIcons = [
                                'two_wheels'  => '<circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/><path d="M8 17h8M12 5l3 7H8l1-4"/><path d="M15 5h3l1 4"/>',
                                'four_wheels' => '<path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
                            ];
                            $defaultVehicleIcon = '<rect x="3" y="7" width="18" height="10" rx="2"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>';
                        @endphp
                        <div class="auth-field" style="margin-bottom:14px">
                            <label class="auth-label">Vehicle Type <span class="auth-required">*</span></label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px" id="vehicleTypeGroup">
                                @foreach(DB::table('vehicle_types')->orderBy('id')->get() as $vt)
                                <label style="flex:1;min-width:90px">
                                    <input type="radio" name="vehicle_type" value="{{ $vt->slug }}" id="vt_{{ $vt->slug }}"
                                           data-requires-documents="1"
                                           style="display:none" onchange="onVehicleTypeChange()">
                                    <div class="vehicle-type-card" data-for="vt_{{ $vt->slug }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $vehicleTypeIcons[$vt->slug] ?? $defaultVehicleIcon !!}</svg>
                                        <span>{{ $vt->name }}</span>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            <span id="vehicleTypeError" style="display:none;color:red;font-size:11px;margin-top:4px">Please select a vehicle type.</span>
                        </div>

                        {{-- Own vehicle details (brand, model, plate, OR, CR) --}}
                        <div id="ownVehicleSection" style="display:none">
                            <div class="auth-form-grid vehicle-details-grid">
                                <div class="auth-field">
                                    <label class="auth-label" for="vehicle_brand">Brand <span class="auth-required">*</span></label>
                                    <input class="auth-input" id="vehicle_brand" name="vehicle_brand" type="text" placeholder="e.g. Honda">
                                </div>
                                <div class="auth-field">
                                    <label class="auth-label" for="vehicle_model">Model <span class="auth-required">*</span></label>
                                    <input class="auth-input" id="vehicle_model" name="vehicle_model" type="text" placeholder="e.g. Click 125i">
                                </div>
                                <div class="auth-field">
                                    <label class="auth-label" for="plate_number">Plate Number <span class="auth-required">*</span></label>
                                    <input class="auth-input" id="plate_number" name="plate_number" type="text" placeholder="e.g. ABC 1234">
                                </div>
                            </div>
                            <div id="vehicleDocsSection" style="margin-top:8px">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                    <div>
                                        <label class="auth-label" style="font-size:11px;color:var(--auth-muted)">Official Receipt (OR)</label>
                                        <div id="orBox" class="document-upload" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:120px;display:flex;flex-direction:column;justify-content:center">
                                            <div id="orIdle" style="padding:10px;text-align:center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:5px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                <p style="margin:0 0 7px;font-size:11px;color:#888">Upload OR document</p>
                                                <label for="or_file" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:8px;font-size:11px;cursor:pointer"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Upload</label>
                                                <span class="upload-info">Photo, scan, or PDF · max 5 MB</span>
                                            </div>
                                            <div id="orPreview" style="display:none;position:relative">
                                                <img id="orImg" style="width:100%;max-height:110px;object-fit:cover;display:block" alt="OR">
                                                <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openDocLightbox('or')"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
                                                <button type="button" onclick="event.stopPropagation();clearUpload('or')" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">Retake</button>
                                            </div>
                                        </div>
                                        <input type="file" id="or_file" name="or_file" accept="image/*,.pdf" style="display:none" onchange="handleDocUpload('or', this)">
                                        <span id="orError" style="display:none;color:red;font-size:11px">Required.</span>
                                    </div>
                                    <div>
                                        <label class="auth-label" style="font-size:11px;color:var(--auth-muted)">Certificate of Registration (CR)</label>
                                        <div id="crBox" class="document-upload" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:120px;display:flex;flex-direction:column;justify-content:center">
                                            <div id="crIdle" style="padding:10px;text-align:center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:5px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                <p style="margin:0 0 7px;font-size:11px;color:#888">Upload CR document</p>
                                                <label for="cr_file" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:8px;font-size:11px;cursor:pointer"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Upload</label>
                                                <span class="upload-info">Photo, scan, or PDF · max 5 MB</span>
                                            </div>
                                            <div id="crPreview" style="display:none;position:relative">
                                                <img id="crImg" style="width:100%;max-height:110px;object-fit:cover;display:block" alt="CR">
                                                <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openDocLightbox('cr')"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
                                                <button type="button" onclick="event.stopPropagation();clearUpload('cr')" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">Retake</button>
                                            </div>
                                        </div>
                                        <input type="file" id="cr_file" name="cr_file" accept="image/*,.pdf" style="display:none" onchange="handleDocUpload('cr', this)">
                                        <span id="crError" style="display:none;color:red;font-size:11px">Required.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-nav" style="margin-top:16px">
                            <button type="button" class="btn-prev" onclick="prevStep(7)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(7)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 3: Personal Info ── --}}
                    <div class="step-panel" id="panel-3">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Personal Information</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 12px">Fill in your personal details.</p>
                        <div id="ocrPrefillNote" class="prefilled-note" style="display:none"></div>
                        <div class="auth-form-grid">
                            <div class="auth-field">
                                <label class="auth-label" for="last_name">Last name <span class="auth-required">*</span></label>
                                <input class="auth-input" id="last_name" name="last_name" type="text" placeholder="Dela Cruz" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="given_names">Given names <span class="auth-required">*</span></label>
                                <input class="auth-input" id="given_names" name="given_names" type="text" placeholder="Juan" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="middle_name">Middle name</label>
                                <input class="auth-input" id="middle_name" name="middle_name" type="text" placeholder="Santos" maxlength="50">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="suffix">Suffix</label>
                                <select class="auth-input auth-select" id="suffix" name="suffix">
                                    <option value="">None</option>
                                    @foreach(\App\Enums\Suffix::cases() as $suffixOption)
                                        <option value="{{ $suffixOption->value }}">{{ $suffixOption->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="sex">Sex <span class="auth-required">*</span></label>
                                <select class="auth-input auth-select" id="sex" name="sex" required>
                                    <option value="" disabled selected>Select sex</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="birthday">Birthday <span class="auth-required">*</span></label>
                                <input class="auth-input" id="birthday" name="birthday" type="date" required>
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="age">Age</label>
                                <input class="auth-input input-readonly" id="age" name="age" type="text" placeholder="Auto-generated" readonly>
                            </div>
                        </div>
                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(3)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(3)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 4: Contact ── --}}
                    <div class="step-panel" id="panel-4">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Contact Details</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 12px">How can we reach you?</p>
                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="email">Email address <span class="auth-required">*</span></label>
                                <div class="email-verify-row">
                                    <input class="auth-input" id="email" name="email" type="email" placeholder="juan@gmail.com" required autocomplete="off"
                                        value="{{ ($isGoogleSignup ?? false) ? $googleEmail : old('email') }}"
                                        @if($isGoogleSignup ?? false) readonly @endif>
                                    <button type="button" class="btn-send-otp" id="sendOtpBtn" onclick="sendOtp()" @if($isGoogleSignup ?? false) style="display:none" @endif>Send Code</button>
                                </div>
                                <span class="field-hint" id="emailHint">{{ ($isGoogleSignup ?? false) ? 'Prefilled from your Google account.' : '' }}</span>
                            </div>
                            <div class="auth-field full" id="otpField" style="display:none">
                                <label class="auth-label" for="otp_code">Verification code <span class="auth-required">*</span></label>
                                <div class="email-verify-row">
                                    <input class="auth-input" id="otp_code" type="text" placeholder="Enter 6-digit code" maxlength="6" autocomplete="off">
                                    <button type="button" class="btn-send-otp" id="verifyOtpBtn" onclick="verifyOtp()">Verify</button>
                                </div>
                                <span class="field-hint" id="otpHint">Enter the code sent to your email. <button type="button" class="btn-inline-link" id="resendOtpBtn" onclick="resendOtp()" disabled>Resend</button></span>
                            </div>
                            <div class="auth-field full" id="verifiedBadge" style="{{ ($isGoogleSignup ?? false) ? '' : 'display:none' }}">
                                <div class="email-verified-badge">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    {{ ($isGoogleSignup ?? false) ? 'Verified via Google' : 'Email verified' }}
                                </div>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="contact_no">Contact number <span class="auth-required">*</span></label>
                                <input class="auth-input" id="contact_no" name="contact_no" type="tel" placeholder="09XXXXXXXXX" maxlength="11" required>
                                <span class="field-hint" id="contactHint"></span>
                            </div>
                        </div>
                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(4)">← Back</button>
                            <button type="button" class="btn-next" id="contactNextBtn" onclick="nextStep(4)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 6: Account Setup ── --}}
                    <div class="step-panel" id="panel-6">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Account Setup</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Rider</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 12px">Create your username and set a password.</p>
                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="username">Username <span class="auth-required">*</span></label>
                                <div style="position:relative">
                                    <input class="auth-input" id="username" name="username" type="text" placeholder="e.g. juandelacruz" required minlength="8" maxlength="30" autocomplete="off">
                                    <span id="usernameStatus" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px"></span>
                                </div>
                                <div id="usernameSuggestions" style="display:none;margin-top:6px;font-size:12px;color:var(--auth-muted)"></div>
                            </div>
                            @if($isGoogleSignup ?? false)
                            <div class="auth-field full">
                                <span class="field-hint">Set a password too, so you can also sign in with your username later without going through Google.</span>
                            </div>
                            @endif
                            <div class="auth-field full">
                                <label class="auth-label" for="password">Password <span class="auth-required">*</span></label>
                                <input class="auth-input" id="password" name="password" type="password" placeholder="Min. 8 characters" required minlength="8">
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="password_confirmation">Confirm password <span class="auth-required">*</span></label>
                                <input class="auth-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="Re-enter password" required>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin:14px 0 4px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa">
                            <input type="checkbox" id="tcCheckbox" name="agreed_to_terms" value="1" style="width:16px;height:16px;accent-color:var(--auth-primary);flex-shrink:0;cursor:pointer" disabled>
                            <label for="tcCheckbox" style="font-size:12px;color:#374151;cursor:pointer;line-height:1.5;flex:1">
                                I have read and agree to the <button type="button" id="tcOpenBtn" onclick="openTc()" style="background:none;border:none;padding:0;color:var(--auth-primary);font-weight:700;font-size:12px;cursor:pointer;text-decoration:underline">Terms &amp; Conditions</button>
                            </label>
                            <span id="tcBadge" style="display:none;flex-shrink:0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg></span>
                        </div>
                        <span id="tcError" style="display:none;color:red;font-size:11px">Please read and agree to the Terms &amp; Conditions.</span>
                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(6)">← Back</button>
                            <button type="submit" class="btn-submit" id="btnStep6Submit">Submit Registration</button>
                        </div>
                    </div>

                </form>

                {{-- Terms & Conditions Modal — riders review BOTH documents: PocketFinds' own
                     platform-wide rider Terms & Conditions (admin-authored, static, see
                     admin/settings.blade.php), and the CHOSEN COMPANY's own Terms & Conditions
                     (admin-approved, see logistics/account.blade.php), swapped in by
                     onCompanyChange() as the rider picks a company. "Scroll to the bottom"
                     naturally means scrolling past both, since they're stacked in one box. --}}
                <div id="tcModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:18px;width:min(520px,94vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25)">
                        <div style="padding:20px 22px;border-bottom:1px solid #f1f5f9;flex-shrink:0">
                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--auth-primary)">Terms & Conditions</span>
                        </div>
                        <div id="tcContent" style="flex:1;overflow-y:auto;padding:18px 22px;font-size:13px;line-height:1.7;color:#374151">
                            <div>
                                <h4 style="margin:0 0 8px;font-size:12px;font-weight:800;color:var(--auth-primary)">{{ $terms->title ?? 'PocketFinds Terms & Conditions' }}</h4>
                                <div style="white-space:pre-wrap">{{ $terms->content ?? 'Terms & Conditions are not available right now — please contact support.' }}</div>
                            </div>
                            <hr style="margin:18px 0;border:none;border-top:1px solid #f1f5f9">
                            <div>
                                <h4 id="tcCompanyName" style="margin:0 0 8px;font-size:12px;font-weight:800;color:var(--auth-primary)">Company Terms & Conditions</h4>
                                <div id="tcCompanyContent" style="white-space:pre-wrap">Please choose a company first.</div>
                            </div>
                        </div>
                        <div style="padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0">
                            <span id="tcScrollHint" style="font-size:11px;color:var(--auth-muted)">Scroll to the bottom to continue</span>
                            <button type="button" id="tcCloseBtn" onclick="closeTc()" style="padding:8px 20px;background:var(--auth-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0">Close</button>
                        </div>
                    </div>
                </div>

                {{-- Hub Selection Modal --}}
                <div id="hubsModal" class="hubs-modal-backdrop" style="display:none" onclick="if(event.target===this)closeHubModal()">
                    <div class="hubs-modal-dialog">
                        <div class="hubs-modal-header">
                            <div>
                                <h3 class="hubs-modal-title" id="hubsModalTitle">Choose a Hub</h3>
                                <p class="hubs-modal-sub" id="hubsModalSub">Browse and select an available hub in your province</p>
                            </div>
                            <button type="button" class="hubs-modal-close" onclick="closeHubModal()" aria-label="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="hubs-modal-search" id="hubsModalSearchBox">
                            <div class="hubs-search-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="hubsSearchInput" placeholder="Search municipality or hub..." oninput="filterHubsModal(this.value)">
                            </div>
                        </div>
                        <div class="hubs-modal-body" id="hubsModalList"></div>
                        <div class="hubs-modal-footer">
                            <div class="hubs-footer-left">
                                <span>Selected:</span>
                                <strong class="hubs-selection-val" id="hubsModalSelectedText">None</strong>
                            </div>
                            <div class="hubs-footer-actions">
                                <button type="button" class="btn-hubs-cancel" onclick="closeHubModal()">Cancel</button>
                                <button type="button" class="btn-hubs-confirm" id="btnConfirmHubSelection" onclick="confirmHubModalSelection()">Confirm Selection</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Lightbox --}}
                <div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()">
                    <button class="img-lightbox-close" onclick="closeLightbox()">&times;</button>
                    <img id="lightboxImg" src="" alt="Preview" style="display:none">
                    {{-- Rendered in-page via an <iframe>, injected by showPdfLightbox() in register.js — no external tab. --}}
                    <div id="lightboxPdf" style="display:none" onclick="event.stopPropagation()"></div>
                </div>

                {{-- Success screen --}}
                <div class="success-screen" id="successScreen">
                    <div class="success-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                    <h3>Registration Submitted!</h3>
                    <p>Thank you for registering. Please wait for the administrator's approval — a confirmation will be sent to your email.</p>
                    <a class="success-btn" href="{{ url('/') }}">Back to Homepage</a>
                </div>

                <p class="auth-bottom" id="signinLink">
                    Already have an account?
                    <a class="auth-link" href="{{ url('/login') }}">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>

<script>const IS_GOOGLE_SIGNUP = @json($isGoogleSignup ?? false);
const COMPANY_HUBS = @json($companyHubs ?? (object) []);
</script>
<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
<script>
// Resume upload (optional) — mirrors register-logistics-staff.blade.php's handling exactly.
function handleResumeSelect(input) {
    const file = input.files && input.files[0];
    const errEl = document.getElementById('resumeFileError');
    if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }

    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        if (errEl) {
            errEl.textContent = 'Resume file size cannot exceed 5MB.';
            errEl.style.display = 'block';
        }
        input.value = '';
        return;
    }

    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf', 'doc', 'docx'].includes(ext)) {
        if (errEl) {
            errEl.textContent = 'Please select a valid document (.pdf, .doc, or .docx).';
            errEl.style.display = 'block';
        }
        input.value = '';
        return;
    }

    const nameEl = document.getElementById('resumeFileName');
    const sizeEl = document.getElementById('resumeFileSize');
    const idleEl = document.getElementById('resumeIdle');
    const selEl  = document.getElementById('resumeSelected');

    if (nameEl) nameEl.textContent = file.name;
    if (sizeEl) {
        const kb = (file.size / 1024).toFixed(1);
        const mb = (file.size / (1024 * 1024)).toFixed(2);
        sizeEl.textContent = file.size >= 1024 * 1024 ? `${mb} MB` : `${kb} KB`;
    }

    if (idleEl) idleEl.style.display = 'none';
    if (selEl)  selEl.style.display  = 'flex';
}

function removeResumeFile() {
    const input = document.getElementById('resume_file');
    if (input) input.value = '';
    const idleEl = document.getElementById('resumeIdle');
    const selEl  = document.getElementById('resumeSelected');
    const errEl  = document.getElementById('resumeFileError');

    if (idleEl) idleEl.style.display = 'flex';
    if (selEl)  selEl.style.display  = 'none';
    if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
}

// Rider-specific: ownership toggle
function onOwnershipChange() {
    const own = document.getElementById('vo_own')?.checked;
    document.getElementById('ownVehicleSection').style.display = own ? '' : 'none';
    document.querySelectorAll('.ownership-card').forEach(card => {
        const radio = document.getElementById(card.dataset.for);
        card.style.borderColor = radio?.checked ? 'var(--auth-primary)' : '#e5e7eb';
        card.style.background  = radio?.checked ? 'var(--auth-primary-soft)' : '#fff';
        card.style.color       = radio?.checked ? 'var(--auth-primary)' : '#374151';
    });
    document.getElementById('ownershipError').style.display = 'none';
    updateVehicleTypeAvailability();
}

/**
 * Disables a vehicle-type card the rider's chosen hub simply can't provide right now —
 * only matters for "Company Vehicle" (their own vehicle needs no hub inventory at all).
 * Reads `available_vehicle_types` on each hub in COMPANY_HUBS, populated server-side from
 * CompanyVehicle::availableTypesForHub() (approved + not-in-maintenance).
 */
function updateVehicleTypeAvailability() {
    const ownership = document.querySelector('input[name="vehicle_ownership"]:checked')?.value;
    const selectedCompany = document.querySelector('input[name="business_name"]:checked')?.value;
    const hub = (COMPANY_HUBS[selectedCompany] || []).find(h => h.id === currentSelectedHubId);
    const restrict = ownership === 'company' && hub && Array.isArray(hub.available_vehicle_types);

    document.querySelectorAll('#vehicleTypeGroup input[name="vehicle_type"]').forEach(radio => {
        const card = document.querySelector(`.vehicle-type-card[data-for="${radio.id}"]`);
        const isAvailable = !restrict || hub.available_vehicle_types.includes(radio.value);
        radio.disabled = !isAvailable;
        if (!isAvailable && radio.checked) { radio.checked = false; }
        if (!card) return;
        card.style.opacity      = isAvailable ? '' : '.45';
        card.style.cursor       = isAvailable ? '' : 'not-allowed';
        card.style.pointerEvents = isAvailable ? '' : 'none';
        if (isAvailable) { card.style.borderColor = radio.checked ? 'var(--auth-primary)' : '#e5e7eb'; }
        let note = card.querySelector('.not-available-note');
        if (!isAvailable && !note) {
            note = document.createElement('span');
            note.className = 'not-available-note';
            note.style.cssText = 'display:block;font-size:9px;color:#dc2626;font-weight:700;margin-top:2px';
            note.textContent = 'Not available at this hub';
            card.appendChild(note);
        } else if (isAvailable && note) {
            note.remove();
        }
    });
}

// ── Company & Hub step — same picker as register-logistics-staff.blade.php ──
const COMPANY_TERMS = JSON.parse(document.getElementById('companyTermsData')?.textContent || '{}');

// Reset company + hub selection when address changes (even within the same province) —
// mirrors resetCompanyAndHubStep() in register-logistics-staff.blade.php exactly.
function resetCompanyAndHubStep() {
    document.querySelectorAll('input[name="business_name"]').forEach(r => { r.checked = false; });
    document.querySelectorAll('#companyGrid .company-card').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = '';
        card.style.background  = '';
        card.style.color       = '';
    });
    lastSelectedCompany = null;
    const coErr = document.getElementById('companyError');
    if (coErr) coErr.style.display = 'none';

    currentSelectedHubId = null;
    tempModalHubId = null;
    const hubInput = document.getElementById('selected_hub_id');
    if (hubInput) hubInput.value = '';

    const hubSection = document.getElementById('hubSection');
    if (hubSection) hubSection.style.display = 'none';
    const hubContainer = document.getElementById('hubSelectionContainer');
    if (hubContainer) hubContainer.innerHTML = '';
    const noticeEl = document.getElementById('hubNoticeContainer');
    if (noticeEl) { noticeEl.style.display = 'none'; noticeEl.innerHTML = ''; }
    const hubErr = document.getElementById('hubSelectError');
    if (hubErr) hubErr.style.display = 'none';
}

// Re-check company availability/tags and refresh the address pill based on the rider's address.
function refreshCompanyAndHubOptions() {
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    const pillText = document.getElementById('pillAddressText');
    if (pillText) pillText.textContent = (municipality ? municipality + ', ' : '') + (province || 'Not specified');

    document.querySelectorAll('#companyGrid .company-card').forEach((card, idx) => {
        const radio = document.getElementById(card.dataset.for);
        if (!radio) return;
        const companyName = radio.value;
        const hubs = COMPANY_HUBS[companyName] || [];
        const provHubs = hubs.filter(h => h.province.toLowerCase() === province.toLowerCase());
        const tag = document.getElementById('companyTag_' + idx);

        if (provHubs.length === 0) {
            card.classList.add('disabled');
            card.classList.remove('selected');
            radio.disabled = true;
            if (radio.checked) radio.checked = false;
            if (tag) {
                tag.style.display = 'block';
                tag.className = 'company-tag tag-no-hubs';
                tag.textContent = province ? 'No hubs in ' + province : 'No hubs nearby';
            }
        } else {
            card.classList.remove('disabled');
            radio.disabled = false;
            const hiringCount = provHubs.filter(h => h.is_hiring).length;
            if (tag) {
                tag.style.display = 'block';
                if (hiringCount > 0) {
                    tag.className = 'company-tag tag-hiring';
                    tag.textContent = `${hiringCount} hiring hub${hiringCount > 1 ? 's' : ''}`;
                } else {
                    tag.className = 'company-tag tag-not-hiring';
                    tag.textContent = 'Not currently hiring';
                }
            }
        }
    });

    onCompanyChange();
}

let lastSelectedCompany = null;

function onCompanyChange() {
    const checkedRadio = document.querySelector('input[name="business_name"]:checked');
    const selectedCompany = checkedRadio?.value || null;

    if (selectedCompany !== lastSelectedCompany) {
        lastSelectedCompany = selectedCompany;
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
    }

    document.querySelectorAll('#companyGrid .company-card').forEach(card => {
        const radio = document.getElementById(card.dataset.for);
        if (radio?.disabled) {
            card.classList.add('disabled');
            card.classList.remove('selected');
        } else {
            card.classList.remove('disabled');
            card.classList.toggle('selected', !!radio?.checked);
        }
        card.style.borderColor = '';
        card.style.background  = '';
        card.style.color       = '';
    });

    document.getElementById('companyError').style.display = 'none';

    const name = selectedCompany;
    const content = name ? COMPANY_TERMS[name] : null;
    const nameEl = document.getElementById('tcCompanyName');
    if (nameEl) nameEl.textContent = name ? `${name} — Terms & Conditions` : 'Company Terms & Conditions';
    const contentEl = document.getElementById('tcCompanyContent');
    if (contentEl) contentEl.textContent = !name
        ? 'Please choose a company first.'
        : (content || `${name} hasn't published its Terms & Conditions yet — please contact support.`);

    // Switching companies means switching documents — re-require scrolling and re-agreeing.
    tcRead = false;
    const cb = document.getElementById('tcCheckbox');
    if (cb) { cb.checked = false; cb.disabled = true; }
    const badge = document.getElementById('tcBadge');
    if (badge) badge.style.display = 'none';
    const scrollHint = document.getElementById('tcScrollHint');
    if (scrollHint) scrollHint.textContent = 'Scroll to the bottom to continue';

    renderHubOptions();
}

let currentSelectedHubId = null;
let tempModalHubId = null;
let activeProvHubs = [];

function renderHubOptions() {
    const hubSection = document.getElementById('hubSection');
    const container  = document.getElementById('hubSelectionContainer');
    const noticeEl   = document.getElementById('hubNoticeContainer');
    const selectedCompany = document.querySelector('input[name="business_name"]:checked')?.value;
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    document.getElementById('hubSelectError').style.display = 'none';

    if (!selectedCompany) {
        hubSection.style.display = 'none';
        container.innerHTML = '';
        if (noticeEl) noticeEl.style.display = 'none';
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
        return;
    }

    hubSection.style.display = 'block';
    container.innerHTML = '';

    const allHubs = COMPANY_HUBS[selectedCompany] || [];
    activeProvHubs = allHubs.filter(h => h.province.toLowerCase() === province.toLowerCase());

    if (activeProvHubs.length === 0) {
        if (noticeEl) noticeEl.style.display = 'none';
        container.innerHTML = `<div style="padding:12px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;color:#dc2626;font-size:12px">
            <strong>${selectedCompany}</strong> does not have any hubs registered in ${province}. Please choose another company.
        </div>`;
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
        return;
    }

    if (currentSelectedHubId && !activeProvHubs.some(h => h.id === currentSelectedHubId)) {
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
    }

    const directMatch = activeProvHubs.find(h => h.municipality.toLowerCase() === municipality.toLowerCase());

    // When the rider's own municipality really does have a hub here, and it's hiring,
    // surface it as the pre-picked choice right away instead of making them dig for
    // it in the "browse all hubs" modal — they can still switch via Change if they want.
    if (directMatch && directMatch.is_hiring && !currentSelectedHubId) {
        currentSelectedHubId = directMatch.id;
    }

    if (directMatch) {
        if (directMatch.is_hiring) {
            if (noticeEl) noticeEl.style.display = 'none';
        } else {
            if (noticeEl) {
                noticeEl.style.display = 'block';
                noticeEl.innerHTML = `
                <div class="hub-notice warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>
                        <strong>The ${directMatch.municipality} Hub is currently not hiring.</strong>
                        <div style="margin-top:2px;color:#c2410c">The admin has closed applications here. Please select an available nearby hub in <strong>${province}</strong> below.</div>
                    </div>
                </div>`;
            }
        }
    } else {
        if (noticeEl) {
            noticeEl.style.display = 'block';
            noticeEl.innerHTML = `
            <div class="hub-notice info">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>
                    <strong>${selectedCompany} has no hub in ${municipality}.</strong>
                    <div style="margin-top:2px;color:#64748b">Please select one of their available hubs in <strong>${province}</strong> below.</div>
                </div>
            </div>`;
        }
    }

    updateSelectedHubPreview();
}

function updateSelectedHubPreview() {
    const container = document.getElementById('hubSelectionContainer');
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    document.getElementById('selected_hub_id').value = currentSelectedHubId || '';
    updateVehicleTypeAvailability();

    if (!currentSelectedHubId) {
        container.innerHTML = `
        <div class="no-hub-selected-card">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="no-hub-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <strong style="font-size:13px;color:#1e293b">No hub selected yet</strong>
                    <p style="margin:2px 0 0;font-size:11.5px;color:var(--auth-muted)">Please choose an available hub in ${province}.</p>
                </div>
            </div>
            <button type="button" class="btn-browse-hubs" onclick="openHubModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Select Hub</span>
            </button>
        </div>`;
        return;
    }

    const hub = activeProvHubs.find(h => h.id === currentSelectedHubId);
    if (!hub) return;

    const isDirectMatch = hub.municipality.toLowerCase() === municipality.toLowerCase();

    container.innerHTML = `
    <div class="selected-hub-card ${isDirectMatch ? '' : 'nearby-chosen'}">
        <div class="selected-hub-main">
            <div class="selected-hub-icon">
                ${isDirectMatch
                    ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
                    : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`
                }
            </div>
            <div class="selected-hub-details">
                <div class="selected-hub-header">
                    <span class="selected-hub-name">${hub.municipality} Hub</span>
                    <span class="hub-badge ${hub.is_hiring ? 'hub-badge-hiring' : 'hub-badge-not-hiring'}">
                        ${hub.is_hiring ? (isDirectMatch ? 'Actively Hiring' : 'Hiring') : 'Not Hiring'}
                    </span>
                    ${hub.is_regional_hub ? '<span class="hub-badge hub-badge-regional"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Regional</span>' : ''}
                </div>
                <p class="selected-hub-desc">
                    ${isDirectMatch
                        ? 'Your local municipality hub. Your deliveries will be dispatched from here.'
                        : `Nearby hub in ${hub.province} (${activeProvHubs.length - 1} other hub${activeProvHubs.length > 2 ? 's' : ''} available)`
                    }
                </p>
            </div>
        </div>
        ${activeProvHubs.length > 1 ? `
        <button type="button" class="btn-change-hub" onclick="openHubModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Change</span>
        </button>` : ''}
    </div>`;
}

// ── Hubs Modal Implementation ──
function openHubModal() {
    tempModalHubId = currentSelectedHubId;

    const province = (document.getElementById('province')?.value || '').trim();
    const selectedCompany = document.querySelector('input[name="business_name"]:checked')?.value || '';

    const titleEl = document.getElementById('hubsModalTitle');
    const subEl   = document.getElementById('hubsModalSub');
    if (titleEl) titleEl.textContent = `Available Hubs in ${province}`;
    if (subEl)   subEl.textContent   = `Select a hub for ${selectedCompany} (${activeProvHubs.length} total)`;

    const searchInput = document.getElementById('hubsSearchInput');
    if (searchInput) searchInput.value = '';

    renderModalHubList('');

    const modal = document.getElementById('hubsModal');
    if (modal) modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeHubModal() {
    const modal = document.getElementById('hubsModal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

function renderModalHubList(searchTerm) {
    const container = document.getElementById('hubsModalList');
    if (!container) return;

    const municipality = (document.getElementById('municipality')?.value || '').trim();
    const term = searchTerm.toLowerCase();

    const filtered = activeProvHubs.filter(h =>
        h.municipality.toLowerCase().includes(term) ||
        h.province.toLowerCase().includes(term)
    );

    if (filtered.length === 0) {
        container.innerHTML = `<p style="text-align:center;color:var(--auth-muted);font-size:12px;margin:24px 0">No hubs match "${searchTerm}".</p>`;
        return;
    }

    let html = '';
    filtered.forEach(h => {
        const isDirectMatch = h.municipality.toLowerCase() === municipality.toLowerCase();
        const isSelected = h.id === tempModalHubId;

        html += `
        <div class="hub-choice-card ${isDirectMatch ? 'direct-match' : ''} ${isSelected ? 'selected' : ''} ${h.is_hiring ? '' : 'not-hiring'}"
             id="modalHubCard_${h.id}"
             onclick="${h.is_hiring ? `selectModalHub(${h.id})` : ''}">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                <input type="radio" name="modal_hub_choice" id="modalHubChoice_${h.id}" value="${h.id}"
                    ${h.is_hiring ? '' : 'disabled'}
                    ${isSelected ? 'checked' : ''}
                    style="accent-color:var(--auth-primary);width:16px;height:16px;cursor:pointer;flex-shrink:0"
                    onclick="event.stopPropagation(); selectModalHub(${h.id})">
                <div style="min-width:0">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <strong style="font-size:13px;color:#1e293b">${h.municipality} Hub</strong>
                        ${isDirectMatch ? '<span style="font-size:10px;font-weight:700;background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:999px;display:inline-flex;align-items:center;gap:3px"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Home Municipality</span>' : ''}
                        ${h.is_regional_hub ? '<span class="hub-badge hub-badge-regional"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Regional</span>' : ''}
                    </div>
                    <span style="font-size:11px;color:var(--auth-muted)">${h.province}</span>
                </div>
            </div>
            <div style="flex-shrink:0">
                ${h.is_hiring
                    ? '<span class="hub-badge hub-badge-hiring"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Hiring</span>'
                    : '<span class="hub-badge hub-badge-not-hiring">Not Hiring</span>'
                }
            </div>
        </div>`;
    });

    container.innerHTML = html;
    updateModalFooterSelection();
}

function selectModalHub(hubId) {
    tempModalHubId = hubId;
    document.querySelectorAll('#hubsModalList .hub-choice-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById('modalHubCard_' + hubId);
    if (card) card.classList.add('selected');
    const radio = document.getElementById('modalHubChoice_' + hubId);
    if (radio) radio.checked = true;
    updateModalFooterSelection();
}

function updateModalFooterSelection() {
    const textEl = document.getElementById('hubsModalSelectedText');
    const confirmBtn = document.getElementById('btnConfirmHubSelection');
    if (!textEl || !confirmBtn) return;
    const chosen = activeProvHubs.find(h => h.id === tempModalHubId);
    if (chosen) {
        textEl.textContent = `${chosen.municipality} Hub`;
        confirmBtn.disabled = false;
    } else {
        textEl.textContent = 'None';
        confirmBtn.disabled = true;
    }
}

function confirmHubModalSelection() {
    if (!tempModalHubId) return;
    currentSelectedHubId = tempModalHubId;
    updateSelectedHubPreview();
    document.getElementById('hubSelectError').style.display = 'none';
    closeHubModal();
}

function filterHubsModal(value) {
    renderModalHubList(value.trim());
}

function validateCompanyStep() {
    const company = document.querySelector('input[name="business_name"]:checked')?.value;
    if (!company) {
        document.getElementById('companyError').style.display = 'block';
        document.getElementById('companyGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    const hubId = document.getElementById('selected_hub_id')?.value;
    if (!hubId) {
        const errEl = document.getElementById('hubSelectError');
        if (errEl) {
            errEl.style.display = 'block';
            errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
    return true;
}

// Override nextStep for rider-specific flow: 5(Address)→9(Company)→2(Docs)→7(Vehicle)→3(Personal)→4(Contact)→6(Account)
const _origNextStep = nextStep;
nextStep = function(current) {
    if (current === 5) {
        if (!validateStep(5)) return;
        setStep(5, 9); return;
    }
    if (current === 9) {
        if (!validateCompanyStep()) return;
        setStep(9, 2); return;
    }
    if (current === 2) {
        if (!validateRiderStep2()) return;
        setStep(2, 7); return;
    }
    if (current === 7) {
        if (!validateRiderStep7()) return;
        setStep(7, 3); return;
    }
    if (current === 4) {
        // Address no longer follows Contact — jump straight to Account instead of
        // falling through to the generic current+1 (which would land back on Address).
        if (!validateStep(4)) return;
        setStep(4, 6); return;
    }
    _origNextStep(current);
};

const _origPrevStep = prevStep;
prevStep = function(current) {
    if (current === 9) { setStep(9, 5); return; }
    if (current === 2) { setStep(2, 9); return; }
    if (current === 7) { setStep(7, 2); return; }
    if (current === 3) { setStep(3, 7); return; }
    if (current === 6) { setStep(6, 4); return; }
    _origPrevStep(current);
};

function validateRiderStep2() {
    let valid = true;
    const ln = document.getElementById('license_number');
    const le = document.getElementById('license_expiry');
    [ln, le].forEach(el => clearError(el));
    // licenseNumberAvailable === null (check still pending, or failed to load — this DB
    // connection can take several seconds per round trip) is deliberately NOT blocking here.
    // A stale/never-resolved live check used to strand the user on this step indefinitely
    // with no way forward; the actual uniqueness rule is still fully enforced server-side
    // at final submit (RegisterController), so this is just an early, best-effort warning.
    if (!ln.value.trim()) { showError(ln, 'License number is required.'); valid = false; }
    else if (licenseNumberAvailable === false) { showError(ln, "This license number is already registered."); valid = false; }
    if (!le.value) { showError(le, 'Expiry date is required.'); valid = false; }
    if (!docBlobs.license) { document.getElementById('licenseError').style.display = 'block'; valid = false; }
    else { document.getElementById('licenseError').style.display = 'none'; }
    if (!selfieBlob) { document.getElementById('selfieError').style.display = 'block'; valid = false; }
    else { document.getElementById('selfieError').style.display = 'none'; }
    return valid;
}

function validateRiderStep7() {
    let valid = true;
    const ownershipChecked = document.querySelector('input[name="vehicle_ownership"]:checked');
    if (!ownershipChecked) { document.getElementById('ownershipError').style.display = 'block'; valid = false; }
    const vtChecked = document.querySelector('input[name="vehicle_type"]:checked');
    if (!vtChecked) { document.getElementById('vehicleTypeError').style.display = 'block'; valid = false; }
    if (ownershipChecked?.value === 'own') {
        ['vehicle_brand','vehicle_model','plate_number'].forEach(id => {
            const el = document.getElementById(id);
            clearError(el);
            if (!el.value.trim()) { showError(el, 'This field is required.'); valid = false; }
        });
        const pn = document.getElementById('plate_number');
        // Same reasoning as licenseNumberAvailable above — don't hard-block on a still-pending
        // or never-resolved live check; server-side validation is the real safety net.
        if (pn.value.trim() && plateNumberAvailable === false) {
            showError(pn, 'This plate number is already registered.'); valid = false;
        }
        if (!docBlobs.or)  { document.getElementById('orError').style.display  = 'block'; valid = false; }
        if (!docBlobs.cr)  { document.getElementById('crError').style.display  = 'block'; valid = false; }
    }
    return valid;
}

// Update step indicator for rider flow
function setStep(current, target) {
    document.getElementById('panel-' + current).classList.remove('active');
    document.getElementById('panel-' + target).classList.add('active');
    const stepMap = { 5:1, 9:2, 2:3, 7:4, 3:5, 4:6, 6:7 };
    const targetNum = stepMap[target] ?? target;
    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.querySelector('.step-circle').textContent);
        item.classList.remove('active','done');
        if (s === targetNum) item.classList.add('active');
        if (s < targetNum) item.classList.add('done');
    });
    document.querySelector('.auth-form-panel').scrollTop = 0;

    // Refresh hub preview whenever the rider navigates back to the company step.
    if (target === 9) refreshCompanyAndHubOptions();
}

// Reset company/hub selection if the rider goes back and changes their address —
// even within the same province, since the hub they'd been shown may no longer apply.
document.getElementById('province')?.addEventListener('change', () => {
    resetCompanyAndHubStep();
    setTimeout(refreshCompanyAndHubOptions, 200);
});
document.getElementById('municipality')?.addEventListener('change', () => {
    resetCompanyAndHubStep();
    setTimeout(refreshCompanyAndHubOptions, 200);
});
</script>
</body>
</html>
