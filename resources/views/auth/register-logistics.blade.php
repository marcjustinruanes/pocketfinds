<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Registration — PocketFinds</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <style>
        .prefilled-note {
            font-size: 11px;
            color: var(--auth-primary);
            margin: 0 0 14px;
            padding: 6px 12px;
            background: var(--auth-primary-soft);
            border-radius: 8px;
            border-left: 3px solid var(--auth-primary);
        }
        .img-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .img-lightbox.open { display: flex; }
        .img-lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
        .img-lightbox-close {
            position: absolute;
            top: 16px; right: 20px;
            color: #fff; font-size: 28px;
            cursor: pointer; line-height: 1;
            background: none; border: none;
        }
        .enlarge-btn {
            position: absolute; top: 6px; left: 6px;
            background: rgba(0,0,0,.45); color: #fff;
            border: none; border-radius: 6px;
            padding: 3px 6px; cursor: pointer;
            display: flex; align-items: center;
        }
        #panel-4 .file-upload-label {
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 480px) {
            #panel-4 .id-selfie-grid {
                grid-template-columns: 1fr !important;
            }
        }
        .ocr-result {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 11px;
            line-height: 1.5;
            display: none;
        }
        .ocr-result.checking {
            display: block;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .ocr-result.match {
            display: block;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .ocr-result.mismatch {
            display: block;
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }
        .vehicle-type-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 12px 8px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            background: #fff;
            transition: border-color .15s, background .15s, color .15s;
            text-align: center;
        }
        input[type="radio"]:checked + .vehicle-type-card {
            border-color: var(--auth-primary);
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
        }
        .upload-box {
            border: 2px dashed #e5e7eb;
            border-radius: 10px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: #f9f9f9;
            text-align: center;
            padding: 10px;
            transition: border-color .15s;
        }
        .upload-box:hover { border-color: var(--auth-primary); }
        .vehicle-details-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .vehicle-details-grid .full { grid-column:auto; }
        .document-upload { min-height:140px; }
        #vehicleDocsSection { margin-top:8px; }
        .license-layout { display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:stretch; }
        .license-fields { display:flex;flex-direction:column;gap:10px; }
        .document-upload .upload-info { display:block;flex-basis:100%;margin-top:6px;font-size:10px;color:var(--auth-muted);line-height:1.35; }
        .document-upload [id$="Idle"] > svg { color:#aaa; }
        /* Hub Coverage Cards & Modal */
        .hub-province-card {
            border: 1px solid var(--auth-border, #e5e7eb);
            border-radius: 12px;
            background: #fff;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: border-color .15s, box-shadow .15s;
        }
        .hub-province-card:hover {
            border-color: var(--auth-primary);
            box-shadow: 0 4px 14px rgba(0,0,0,.05);
        }
        .btn-view-muni {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--auth-primary);
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }
        .btn-view-muni:hover {
            background: var(--auth-primary);
            color: #fff;
        }
        .btn-del-prov {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #9ca3af;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all .15s;
        }
        .btn-del-prov:hover {
            color: #dc2626;
            background: #fef2f2;
            border-color: #fecaca;
        }
        .modal-tab-btn {
            padding: 5px 12px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s;
        }
        .modal-tab-btn:hover {
            border-color: var(--auth-primary);
            color: var(--auth-primary);
        }
        .modal-tab-btn.active {
            border-color: var(--auth-primary);
            background: var(--auth-primary);
            color: #fff;
        }
        .modal-muni-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 10px;
            border-radius: 9px;
            border: 1px solid #e2e8f0;
            background: #fff;
            transition: border-color .15s, background .15s;
        }
        .modal-muni-card.is-regional {
            border-color: #fde047;
            background: #fefce8;
        }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">Join the logistics team.</h1>
                <p class="auth-brand-text">
                    Coordinate pickups, dispatch couriers, and track deliveries from the sorting center. Fill in your details and your account will be reviewed by our admin team.
                </p>
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

                {{-- Step indicator --}}
                @php
                    $regType = 'logistics';
                    $typeIcons = [
                        'logistics' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><path d="M9 14l2 2 4-4"/></svg>',
                    ];
                @endphp
                <div class="steps" id="stepIndicator">
                    <div class="step-item active" data-step="7">
                        <div class="step-circle">1</div>
                        <span class="step-label">Company</span>
                    </div>
                    <div class="step-item" data-step="8">
                        <div class="step-circle">2</div>
                        <span class="step-label">Coverage</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle" id="idSelfieStepCircle">3</div>
                        <span class="step-label">ID & Selfie</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle" id="personalStepCircle">4</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle" id="contactStepCircle">5</div>
                        <span class="step-label">Contact</span>
                    </div>
                    <div class="step-item" data-step="5">
                        <div class="step-circle" id="addressStepCircle">6</div>
                        <span class="step-label">Address</span>
                    </div>
                    <div class="step-item" data-step="6">
                        <div class="step-circle" id="accountStepCircle">7</div>
                        <span class="step-label">Account</span>
                    </div>
                </div>

                <form id="buyerForm" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="account_type" value="logistics">
                    <input type="hidden" name="auth_method" value="{{ ($isGoogleSignup ?? false) ? 'google' : 'manual' }}">
                    @if($isGoogleSignup ?? false)
                        <input type="hidden" name="google_id" value="{{ $googleId }}">
                    @endif

                    {{-- ── STEP 7 (UI Step 1): Company Info ── --}}
                    <div class="step-panel active" id="panel-7">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Company Info</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] !!} Logistics</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 14px">Register your company name, logo, and business permit.</p>

                        <input type="hidden" name="logistics_mode" value="found">

                        <div class="auth-field" style="margin-bottom:10px">
                            <label class="auth-label" for="business_name">Company Name <span class="auth-required">*</span></label>
                            <div style="position:relative">
                                <input class="auth-input" id="business_name" name="business_name" type="text" placeholder="e.g. SwiftMove Logistics" required maxlength="150" autocomplete="off" style="padding-right:32px">
                                <span id="businessNameStatus" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none"></span>
                            </div>
                            <span id="businessNameError" style="display:none;color:red;font-size:11px">Company name is already registered.</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
                            <div>
                                <label class="auth-label" style="font-size:11px">Company Logo <span style="color:var(--auth-muted);font-weight:400">(optional)</span></label>
                                <div class="upload-box document-upload" id="logoBox" onclick="document.getElementById('logo_file').click()" style="cursor:pointer;min-height:110px">
                                    <div id="logoIdle" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px 6px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:5px"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M14 9h4M14 13h2"/></svg>
                                        <span style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:7px;font-size:10.5px;margin-top:4px">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Upload
                                        </span>
                                        <span class="upload-info">JPG, PNG, WEBP · 2 MB</span>
                                    </div>
                                    <div id="logoPreview" style="display:none;position:relative">
                                        <img id="logoImg" style="width:100%;max-height:110px;object-fit:cover;border-radius:6px" alt="Company logo">
                                        <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openDocLightbox('logo')" aria-label="Enlarge company logo"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
                                        <button type="button" onclick="event.stopPropagation();clearUpload('logo')" style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer">✕</button>
                                    </div>
                                </div>
                                <input type="file" id="logo_file" name="company_logo" accept="image/*" style="display:none" onchange="handleDocUpload('logo', this)">
                            </div>
                            <div id="businessPermitField">
                                <label class="auth-label" style="font-size:11px">Business / DTI Permit <span class="auth-required">*</span></label>
                                <div class="upload-box document-upload" id="businessPermitBox" style="cursor:pointer;min-height:110px">
                                    <div id="businessPermitIdle" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px 6px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:5px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <label for="business_permit_file" style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:7px;font-size:10.5px;cursor:pointer;margin-top:4px">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Upload
                                        </label>
                                        <span class="upload-info">JPG, PNG, PDF · 5 MB</span>
                                    </div>
                                    <div id="businessPermitPreview" style="display:none;position:relative">
                                        <img id="businessPermitImg" style="width:100%;max-height:110px;object-fit:cover;border-radius:6px" alt="Business permit">
                                        <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openLightbox('businessPermitImg')" aria-label="Enlarge business permit">⤢</button>
                                        <button type="button" onclick="event.stopPropagation();clearBusinessPermit()" style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer">✕</button>
                                    </div>
                                </div>
                                <input type="file" id="business_permit_file" name="business_permit_file" accept="image/*,.pdf" style="display:none" required>
                                <span id="businessPermitError" style="display:none;color:red;font-size:11px">Please upload your business permit.</span>
                            </div>
                        </div>

                        <div class="step-nav" style="margin-top:16px">
                            <span></span>
                            <button type="button" class="btn-next" onclick="nextStep(7)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 8 (UI Step 2): Coverage Areas ── --}}
                    <div class="step-panel" id="panel-8">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Coverage Areas</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] !!} Logistics</span>
                        </div>
                        <p class="auth-subtitle" style="margin:0 0 12px">Pick provinces and select the cities/municipalities your company will cover. Each one becomes a hub in your network.</p>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;align-items:end;margin-bottom:8px">
                            <div>
                                <label class="auth-label" style="font-size:11px" for="hub_province_input">Province</label>
                                <select class="auth-input auth-select" id="hub_province_input"><option value="" disabled selected>Select province</option></select>
                            </div>
                            <div style="position:relative">
                                <label class="auth-label" style="font-size:11px">City / Municipality</label>
                                <button type="button" id="hubMuniDropdownBtn" disabled class="auth-input" style="width:100%;box-sizing:border-box;text-align:left;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:6px">
                                    <span id="hubMuniDropdownLabel" style="font-size:13px">Select province first</span>
                                    <span style="color:var(--auth-muted);font-size:11px;flex-shrink:0">▾</span>
                                </button>
                                <div id="hubMuniDropdownPanel" style="display:none;position:absolute;z-index:30;top:calc(100% + 4px);left:0;right:0;max-height:260px;overflow-y:auto;background:#fff;border:1px solid var(--auth-border,#ddd);border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.1);padding:8px">
                                    <input type="text" id="hubMuniFilter" placeholder="Search…" style="width:100%;padding:7px 9px;border:1px solid var(--auth-border,#ddd);border-radius:6px;font-size:12px;margin-bottom:6px;box-sizing:border-box">
                                    <label style="display:flex;align-items:center;gap:8px;padding:6px 4px;border-bottom:1px solid #f0f0f0;margin-bottom:4px;font-weight:700;font-size:12px;cursor:pointer">
                                        <input type="checkbox" id="hubMuniSelectAll"> Select all
                                    </label>
                                    <div id="hubMuniCheckboxes"></div>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="addHubArea()" style="width:100%;padding:8px;border:1.5px dashed var(--auth-primary);background:#fff;color:var(--auth-primary);border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer">+ Add Selected Municipalities</button>

                        <p style="margin:10px 0 4px;font-size:11px;color:var(--auth-muted)">Click <strong>View Hubs</strong> on any province to inspect, search, or change its gateway hub (★).</p>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                            <div style="display:flex;align-items:center;gap:6px">
                                <span style="font-size:11.5px;font-weight:700;color:#374151">Added coverage hubs</span>
                                <span id="hubCountBadge" style="display:none;font-size:11px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:2px 8px;border-radius:999px"></span>
                            </div>
                            <button type="button" id="hubViewAllBtn" onclick="openCoverageModal()" style="display:none;background:none;border:none;color:var(--auth-primary);font-size:11px;font-weight:700;cursor:pointer;padding:0;text-decoration:underline">View All Hubs Modal ↗</button>
                        </div>
                        <div id="hubAreaList" style="display:flex;flex-direction:column;gap:6px"></div>
                        <input type="hidden" id="hub_areas" name="hub_areas" value="[]">
                        <span id="hubAreasError" style="display:none;color:red;font-size:11px;margin-top:4px">Add at least one coverage area.</span>

                        <div class="step-nav" style="margin-top:14px">
                            <button type="button" class="btn-prev" onclick="prevStep(8)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(8)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 2 (UI Step 3): ID & Selfie ── --}}
                    <div class="step-panel" id="panel-2">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">ID & Selfie</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] ?? $typeIcons['buyer'] !!} {{ ucfirst($regType) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Upload your ID — we'll pre-fill your details automatically.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        {{-- ID Type --}}
                        <div class="auth-field" style="margin-bottom:12px">
                            <label class="auth-label" for="id_type_id">ID Type <span class="auth-required">*</span></label>
                            <select class="auth-input auth-select" id="id_type_id" name="id_type_id" required>
                                <option value="" disabled selected>Select ID type</option>
                                @foreach(DB::table('id_types')->orderBy('name')->get() as $idType)
                                    <option value="{{ $idType->id }}">{{ $idType->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ID Photo + Selfie side by side --}}
                        <div class="id-selfie-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px" id="idSelfieGrid">

                            {{-- ID Photo --}}
                            <div>
                                <label class="auth-label">ID Photo <span class="auth-required">*</span></label>
                                <div id="idPhotoBox" class="document-upload" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:140px;display:flex;flex-direction:column;justify-content:center;opacity:0.4;pointer-events:none">
                                    {{-- Idle: upload or camera --}}
                                    <div id="idPhotoIdle" style="padding:12px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:6px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L6 7h12l-2-4z"/><circle cx="12" cy="14" r="3"/></svg>
                                        <p style="margin:0 0 8px;font-size:11px;color:#888">Upload your ID document</p>
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                                            <label for="id_file" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--auth-primary,#e74c3c);color:var(--auth-primary,#e74c3c);background:#fff;border-radius:8px;font-size:11px;cursor:pointer">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                                Upload
                                            </label>
                                            <span class="upload-info">Clear photo or scan · JPG, PNG, or PDF · max 5 MB</span>
                                            <button type="button" id="idCameraBtn" onclick="startIdCamera()" style="display:none">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                                Camera
                                            </button>
                                        </div>
                                    </div>
                                    {{-- Camera view --}}
                                    <div id="idCamera" style="display:none;position:relative">
                                        <video id="idVideo" autoplay playsinline style="width:100%;max-height:140px;object-fit:cover;display:block"></video>
                                        <button type="button" onclick="snapIdPhoto()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--auth-primary,#e74c3c);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </div>
                                    {{-- Preview --}}
                                    <div id="idPhotoPreview" style="display:none;position:relative">
                                        <img id="idPhotoImg" style="width:100%;max-height:140px;object-fit:cover;display:block" alt="ID Photo">
                                        <button type="button" class="enlarge-btn" onclick="openLightbox('idPhotoImg')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                                        </button>
                                        <button type="button" onclick="retakeIdPhoto()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                                            Retake
                                        </button>
                                    </div>
                                </div>
                                <input type="file" id="id_file" name="id_file" accept="image/*,.pdf" style="display:none">
                                <canvas id="idCanvas" style="display:none"></canvas>
                                <span id="idPhotoError" style="display:none;color:red;font-size:11px">Please upload or take a photo of your ID.</span>
                            </div>

                            {{-- Selfie --}}
                            <div>
                                <label class="auth-label">Selfie <span class="auth-required">*</span></label>
                                <div id="selfieBox" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:140px;display:flex;flex-direction:column;justify-content:center;opacity:0.4;pointer-events:none">
                                    <div id="selfieCamera" style="display:none;position:relative">
                                        <video id="selfieVideo" autoplay playsinline style="width:100%;max-height:140px;object-fit:cover;display:block"></video>
                                        <button type="button" onclick="snapSelfie()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--auth-primary,#e74c3c);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </div>
                                    <div id="selfiePreview" style="display:none;position:relative">
                                        <img id="selfieImg" style="width:100%;max-height:140px;object-fit:cover;display:block" alt="Selfie">
                                        <button type="button" class="enlarge-btn" onclick="openLightbox('selfieImg')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                                        </button>
                                        <button type="button" onclick="retakeSelfie()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                                            Retake
                                        </button>
                                    </div>
                                    <div id="selfieIdle" style="padding:16px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:6px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        <p style="margin:0 0 8px;font-size:11px;color:#888">Take a selfie</p>
                                        <button type="button" id="openCameraBtn" onclick="startCamera()" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid var(--auth-primary,#e74c3c);color:var(--auth-primary,#e74c3c);background:#fff;border-radius:8px;font-size:12px;cursor:pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                            Open Camera
                                        </button>
                                    </div>
                                </div>
                                <canvas id="selfieCanvas" style="display:none"></canvas>
                                <span id="selfieError" style="display:none;color:red;font-size:11px">Please take a selfie.</span>
                            </div>
                        </div>

                        {{-- OCR status --}}
                        <div id="ocrResult" class="ocr-result"></div>

                        <div class="step-nav" style="margin-top:12px">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn-next" id="btnStep2Next" onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 3: Personal Info ── --}}
                    <div class="step-panel" id="panel-3">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Personal Information</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] ?? $typeIcons['buyer'] !!} {{ ucfirst($regType) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Review and correct your details if needed.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

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
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] ?? $typeIcons['buyer'] !!} {{ ucfirst($regType) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">How can we reach you?</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

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

                    {{-- ── STEP 5 (UI Step 6): Company Address ── --}}
                    <div class="step-panel" id="panel-5">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Company Address</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] ?? $typeIcons['buyer'] !!} {{ ucfirst($regType) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Enter your company's official business address.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>


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
                            <button type="button" class="btn-prev" onclick="prevStep(5)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(5)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 6 (UI Step 7): Account / Credentials ── --}}
                    <div class="step-panel" id="panel-6">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Account Setup</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcons[$regType] ?? $typeIcons['buyer'] !!} {{ ucfirst($regType) }}</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Create your username and set a password.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        <div class="auth-form-grid" style="margin-top:14px">
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
                            <div class="auth-field">
                                <label class="auth-label" for="password">Password <span class="auth-required">*</span></label>
                                <input class="auth-input" id="password" name="password" type="password" placeholder="Min. 8 characters" required minlength="8">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="password_confirmation">Confirm password <span class="auth-required">*</span></label>
                                <input class="auth-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="Re-enter password" required>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;margin:14px 0 4px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa">
                            <input type="checkbox" id="tcCheckbox" name="agreed_to_terms" value="1" style="width:16px;height:16px;accent-color:var(--auth-primary);flex-shrink:0;cursor:pointer" disabled>
                            <label for="tcCheckbox" style="font-size:12px;color:#374151;cursor:pointer;line-height:1.5;flex:1">
                                I have read and agree to the <button type="button" id="tcOpenBtn" onclick="openTc()" style="background:none;border:none;padding:0;color:var(--auth-primary);font-weight:700;font-size:12px;cursor:pointer;text-decoration:underline">Terms &amp; Conditions</button>
                            </label>
                            <span id="tcBadge" style="display:none;flex-shrink:0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
                            </span>
                        </div>
                        <span id="tcError" style="display:none;color:red;font-size:11px">Please read and agree to the Terms &amp; Conditions.</span>
                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(6)">← Back</button>
                            <button type="submit" class="btn-submit" id="btnStep6Submit">Submit Registration</button>
                        </div>
                    </div>

                </form>

                {{-- Coverage Hubs & Municipalities Modal --}}
                <div id="coverageModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
                    <div style="background:#fff;border-radius:18px;width:min(640px,96vw);max-height:88vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25)">
                        {{-- Modal Head --}}
                        <div style="padding:18px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
                            <div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--auth-primary-soft);color:var(--auth-primary)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </span>
                                    <h3 style="margin:0;font-size:16px;font-weight:800;color:#1e293b">Covered Hubs &amp; Municipalities</h3>
                                </div>
                                <p style="margin:4px 0 0;font-size:11.5px;color:var(--auth-muted)">Review covered areas, set regional gateway hubs (★), or remove hubs.</p>
                            </div>
                            <button type="button" onclick="closeCoverageModal()" style="border:none;background:none;font-size:22px;color:#94a3b8;cursor:pointer;line-height:1;padding:4px">&times;</button>
                        </div>

                        {{-- Modal Filter & Search --}}
                        <div style="padding:12px 22px 10px;border-bottom:1px solid #f1f5f9;background:#f8fafc;flex-shrink:0">
                            <div id="coverageModalTabs" style="display:flex;gap:6px;overflow-x:auto;padding-bottom:6px;margin-bottom:8px"></div>
                            <div style="position:relative">
                                <input type="text" id="coverageModalSearch" placeholder="Search municipalities in covered areas…" style="width:100%;padding:8px 12px 8px 32px;border:1px solid var(--auth-border,#ddd);border-radius:8px;font-size:12px;box-sizing:border-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>
                        </div>

                        {{-- Modal Body --}}
                        <div id="coverageModalBody" style="flex:1;overflow-y:auto;padding:16px 22px;background:#fcfcfd"></div>

                        {{-- Modal Foot --}}
                        <div style="padding:12px 22px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;flex-shrink:0">
                            <span id="coverageModalSummaryText" style="font-size:12px;color:var(--auth-muted);font-weight:600"></span>
                            <button type="button" onclick="closeCoverageModal()" style="padding:8px 22px;background:var(--auth-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Done</button>
                        </div>
                    </div>
                </div>

                {{-- Terms & Conditions Modal — content is admin-authored (see admin/settings.blade.php),
                     not hardcoded here; #tcContent below just renders whatever Policy record holds. --}}
                <div id="tcModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:18px;width:min(520px,94vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25)">
                        <div style="padding:20px 22px;border-bottom:1px solid #f1f5f9;flex-shrink:0">
                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--auth-primary)">{{ $terms->title ?? 'Terms & Conditions' }}</span>
                        </div>
                        <div id="tcContent" style="flex:1;overflow-y:auto;padding:18px 22px;font-size:13px;line-height:1.7;color:#374151;white-space:pre-wrap">{{ $terms->content ?? 'Terms & Conditions are not available right now — please contact support.' }}</div>
                        <div style="padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0">
                            <span id="tcScrollHint" style="font-size:11px;color:var(--auth-muted)">Scroll to the bottom to continue</span>
                            <button type="button" id="tcCloseBtn" onclick="closeTc()" style="padding:8px 20px;background:var(--auth-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0">Close</button>
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
                    <p>
                        Thank you for registering. Please wait for the administrator's approval — a confirmation will be sent to your email.
                    </p>
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

<script>
const IS_GOOGLE_SIGNUP = @json($isGoogleSignup ?? false);
</script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
<script>
// ── Business name live check ──
businessNameAvailable = null;
businessNameTimer = null;
const bnInput  = document.getElementById('business_name');
const bnStatus = document.getElementById('businessNameStatus');
const bnError  = document.getElementById('businessNameError');

const ICON_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
const ICON_CROSS = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
const ICON_CLOCK = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

if (bnInput) {
    bnInput.addEventListener('input', function () {
        businessNameAvailable = null;
        bnStatus.innerHTML = '';
        bnError.style.display = 'none';
        clearTimeout(businessNameTimer);
        const value = this.value.trim();
        if (value.length < 2) return;
        bnStatus.innerHTML = ICON_CLOCK;
        businessNameTimer = setTimeout(() => {
            fetch(`/register/check-business-name?business_name=${encodeURIComponent(value)}`)
                .then(r => r.json())
                .then(data => {
                    if (bnInput.value.trim() !== value) return;
                    businessNameAvailable = data.available;
                    if (data.available) {
                        bnStatus.innerHTML = ICON_CHECK;
                        bnError.style.display = 'none';
                    } else {
                        bnStatus.innerHTML = ICON_CROSS;
                        bnError.style.display = 'block';
                    }
                })
                .catch(() => { businessNameAvailable = null; bnStatus.innerHTML = ''; });
        }, 500);
    });
}

// ── Coverage Areas / Hubs picker ──
let hubAreas = [];
let hubMunicipalities = [];
const hubProvinceSelect = document.getElementById('hub_province_input');
const hubMuniBtn        = document.getElementById('hubMuniDropdownBtn');
const hubMuniLabel      = document.getElementById('hubMuniDropdownLabel');
const hubMuniPanel      = document.getElementById('hubMuniDropdownPanel');
const hubMuniCheckboxes = document.getElementById('hubMuniCheckboxes');
const hubMuniSelectAll  = document.getElementById('hubMuniSelectAll');
const hubMuniFilter     = document.getElementById('hubMuniFilter');

let hubMuniRequestId = 0;

hubProvinceSelect?.addEventListener('change', function () {
    const requestId = ++hubMuniRequestId;
    hubMuniPanel.style.display = 'none';
    hubMuniBtn.disabled = true;
    hubMuniLabel.textContent = 'Loading…';
    hubMuniCheckboxes.innerHTML = '';
    hubMuniSelectAll.checked = false;
    hubMuniFilter.value = '';
    const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
    fetchJSON(`${PSGC}/provinces/${code}/cities-municipalities/`)
        .then(data => {
            if (requestId !== hubMuniRequestId) return;
            hubMunicipalities = [...data].sort((a, b) => a.name.localeCompare(b.name));
            renderHubMuniCheckboxes();
            hubMuniBtn.disabled = false;
            updateHubMuniLabel();
        })
        .catch(() => { if (requestId === hubMuniRequestId) hubMuniLabel.textContent = 'Failed to load'; });
});

function renderHubMuniCheckboxes(filter = '') {
    const term = filter.trim().toLowerCase();
    hubMuniCheckboxes.innerHTML = '';
    hubMunicipalities
        .filter(item => !term || item.name.toLowerCase().includes(term))
        .forEach(item => {
            const label = document.createElement('label');
            label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:5px 4px;font-size:12.5px;cursor:pointer';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'hub-muni-checkbox';
            cb.value = item.name;
            cb.checked = hubAreas.some(a => a.province === hubProvinceSelect.value && a.municipality === item.name);
            cb.addEventListener('change', updateHubMuniLabel);
            label.append(cb, document.createTextNode(item.name));
            hubMuniCheckboxes.appendChild(label);
        });
}

function updateHubMuniLabel() {
    const all     = hubMuniCheckboxes.querySelectorAll('.hub-muni-checkbox');
    const checked = hubMuniCheckboxes.querySelectorAll('.hub-muni-checkbox:checked');
    hubMuniLabel.textContent = checked.length ? `${checked.length} selected` : 'Select municipalities';
    hubMuniSelectAll.checked = all.length > 0 && checked.length === all.length;
}

hubMuniFilter?.addEventListener('input', () => renderHubMuniCheckboxes(hubMuniFilter.value));
hubMuniSelectAll?.addEventListener('change', function () {
    hubMuniCheckboxes.querySelectorAll('.hub-muni-checkbox').forEach(cb => cb.checked = this.checked);
    updateHubMuniLabel();
});
hubMuniBtn?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (hubMuniBtn.disabled) return;
    hubMuniPanel.style.display = hubMuniPanel.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', (event) => {
    if (hubMuniPanel.style.display !== 'none' && !hubMuniPanel.contains(event.target)) {
        hubMuniPanel.style.display = 'none';
    }
});

// ── Coverage Modal & Hubs List ──
let activeCoverageProvince = 'ALL';
let coverageModalFilter = '';

function openCoverageModal(province = null) {
    const provinces = [...new Set(hubAreas.map(a => a.province))];
    if (province) {
        activeCoverageProvince = province;
    } else if (provinces.length === 1) {
        activeCoverageProvince = provinces[0];
    } else {
        activeCoverageProvince = 'ALL';
    }
    coverageModalFilter = '';
    const searchInput = document.getElementById('coverageModalSearch');
    if (searchInput) searchInput.value = '';

    const modal = document.getElementById('coverageModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        renderCoverageModalContent();
    }
}

function closeCoverageModal() {
    const modal = document.getElementById('coverageModal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('coverageModalSearch')?.addEventListener('input', function () {
    coverageModalFilter = this.value;
    renderCoverageModalContent();
});

document.getElementById('coverageModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeCoverageModal();
});

function removeProvinceHubs(province) {
    if (!confirm(`Remove all coverage hubs for ${province}?`)) return;
    hubAreas = hubAreas.filter(a => a.province !== province);
    syncHubAreas();
    updateHubMuniLabel();
    renderHubMuniCheckboxes(hubMuniFilter?.value || '');
    if (document.getElementById('coverageModal')?.style.display === 'flex') {
        renderCoverageModalContent();
    }
}

function renderCoverageModalContent() {
    const tabsContainer = document.getElementById('coverageModalTabs');
    const bodyContainer = document.getElementById('coverageModalBody');
    const summaryText   = document.getElementById('coverageModalSummaryText');
    if (!bodyContainer) return;

    const provinces = [...new Set(hubAreas.map(a => a.province))];

    if (activeCoverageProvince !== 'ALL' && !provinces.includes(activeCoverageProvince)) {
        activeCoverageProvince = provinces[0] || 'ALL';
    }

    if (tabsContainer) {
        tabsContainer.innerHTML = '';
        if (provinces.length > 1) {
            tabsContainer.style.display = 'flex';

            const allBtn = document.createElement('button');
            allBtn.type = 'button';
            allBtn.className = 'modal-tab-btn' + (activeCoverageProvince === 'ALL' ? ' active' : '');
            allBtn.textContent = `All Provinces (${hubAreas.length})`;
            allBtn.onclick = () => {
                activeCoverageProvince = 'ALL';
                renderCoverageModalContent();
            };
            tabsContainer.appendChild(allBtn);

            provinces.forEach(prov => {
                const count = hubAreas.filter(a => a.province === prov).length;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'modal-tab-btn' + (activeCoverageProvince === prov ? ' active' : '');
                btn.textContent = `${prov} (${count})`;
                btn.onclick = () => {
                    activeCoverageProvince = prov;
                    renderCoverageModalContent();
                };
                tabsContainer.appendChild(btn);
            });
        } else {
            tabsContainer.style.display = 'none';
        }
    }

    bodyContainer.innerHTML = '';

    if (hubAreas.length === 0) {
        bodyContainer.innerHTML = `
            <div style="text-align:center;padding:36px 12px;color:#94a3b8">
                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px;color:#cbd5e1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <p style="margin:0;font-size:13px;font-weight:600;color:#64748b">No coverage hubs added yet</p>
                <p style="margin:4px 0 0;font-size:11.5px">Select a province and municipalities to add hubs to your coverage.</p>
            </div>`;
        if (summaryText) summaryText.textContent = '0 hubs added';
        return;
    }

    const term = coverageModalFilter.trim().toLowerCase();
    const visibleProvinces = activeCoverageProvince === 'ALL'
        ? provinces
        : [activeCoverageProvince];

    let totalVisibleMunis = 0;

    visibleProvinces.forEach(prov => {
        const provAreas = hubAreas.filter(a => a.province === prov);
        const filteredMunis = provAreas.filter(a => !term || a.municipality.toLowerCase().includes(term));
        totalVisibleMunis += filteredMunis.length;

        if (term && filteredMunis.length === 0) return;

        const groupCard = document.createElement('div');
        groupCard.style.cssText = 'border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin-bottom:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.03)';

        const grpHead = document.createElement('div');
        grpHead.style.cssText = 'padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px';

        const grpTitle = document.createElement('div');
        grpTitle.style.cssText = 'display:flex;align-items:center;gap:8px';
        grpTitle.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--auth-primary)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span style="font-size:13px;font-weight:800;color:#1e293b">${prov}</span>
            <span style="font-size:11px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:1px 7px;border-radius:999px">${provAreas.length} hub${provAreas.length !== 1 ? 's' : ''}</span>
        `;

        const grpActions = document.createElement('div');
        grpActions.style.cssText = 'display:flex;align-items:center;gap:8px';
        const delProvBtn = document.createElement('button');
        delProvBtn.type = 'button';
        delProvBtn.textContent = 'Remove Province';
        delProvBtn.style.cssText = 'border:none;background:none;color:#ef4444;font-size:11px;font-weight:700;cursor:pointer;padding:2px 6px';
        delProvBtn.onclick = () => removeProvinceHubs(prov);
        grpActions.appendChild(delProvBtn);

        grpHead.append(grpTitle, grpActions);
        groupCard.appendChild(grpHead);

        const grid = document.createElement('div');
        grid.style.cssText = 'padding:12px;display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:8px';

        filteredMunis.forEach(area => {
            const globalIndex = hubAreas.indexOf(area);
            const muniCard = document.createElement('div');
            muniCard.className = 'modal-muni-card' + (area.is_regional ? ' is-regional' : '');

            const left = document.createElement('div');
            left.style.cssText = 'display:flex;align-items:center;gap:7px;min-width:0';

            const star = document.createElement('button');
            star.type = 'button';
            star.title = area.is_regional ? `${area.municipality} is the Provincial Gateway Hub` : `Set as Provincial Gateway Hub for ${prov}`;
            star.innerHTML = area.is_regional ? '★' : '☆';
            star.style.cssText = 'border:none;background:none;font-size:15px;cursor:pointer;line-height:1;padding:0;color:' + (area.is_regional ? '#ca8a04' : '#cbd5e1');
            star.onclick = (e) => {
                e.stopPropagation();
                hubAreas.forEach(a => { if (a.province === prov) a.is_regional = false; });
                area.is_regional = true;
                syncHubAreas();
                renderCoverageModalContent();
            };
            left.appendChild(star);

            const labelWrap = document.createElement('div');
            labelWrap.style.cssText = 'min-width:0';
            const nameEl = document.createElement('div');
            nameEl.style.cssText = 'font-size:12px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
            nameEl.textContent = area.municipality;
            labelWrap.appendChild(nameEl);

            if (area.is_regional) {
                const badge = document.createElement('span');
                badge.style.cssText = 'font-size:9.5px;font-weight:800;color:#854d0e;background:#fef08a;padding:1px 5px;border-radius:4px;display:inline-block;margin-top:1px';
                badge.textContent = 'Gateway Hub';
                labelWrap.appendChild(badge);
            }
            left.appendChild(labelWrap);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.title = `Remove ${area.municipality}`;
            removeBtn.innerHTML = '×';
            removeBtn.style.cssText = 'border:none;background:#f1f5f9;color:#64748b;width:20px;height:20px;border-radius:4px;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;line-height:1;flex-shrink:0';
            removeBtn.onmouseenter = () => { removeBtn.style.background = '#fecaca'; removeBtn.style.color = '#dc2626'; };
            removeBtn.onmouseleave = () => { removeBtn.style.background = '#f1f5f9'; removeBtn.style.color = '#64748b'; };
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                const wasRegional = area.is_regional;
                hubAreas.splice(globalIndex, 1);
                if (wasRegional) {
                    const next = hubAreas.find(a => a.province === prov);
                    if (next) next.is_regional = true;
                }
                syncHubAreas();
                updateHubMuniLabel();
                renderHubMuniCheckboxes(hubMuniFilter?.value || '');
                renderCoverageModalContent();
            };

            muniCard.append(left, removeBtn);
            grid.appendChild(muniCard);
        });

        groupCard.appendChild(grid);
        bodyContainer.appendChild(groupCard);
    });

    if (term && totalVisibleMunis === 0) {
        bodyContainer.innerHTML = `
            <div style="text-align:center;padding:28px 12px;color:#94a3b8">
                <p style="margin:0;font-size:12.5px">No municipalities matching "<strong>${coverageModalFilter}</strong>"</p>
            </div>`;
    }

    if (summaryText) {
        const provCount = provinces.length;
        summaryText.textContent = `${provCount} province${provCount !== 1 ? 's' : ''} · ${hubAreas.length} total hub${hubAreas.length !== 1 ? 's' : ''}`;
    }
}

// ── Province-grouped hub area list on main form ──
function renderHubAreaList() {
    const list = document.getElementById('hubAreaList');
    if (!list) return;
    list.innerHTML = '';

    const byProvince = {};
    hubAreas.forEach(area => { (byProvince[area.province] ??= []).push(area); });
    const provinceCount = Object.keys(byProvince).length;

    const countBadge = document.getElementById('hubCountBadge');
    const viewAllBtn = document.getElementById('hubViewAllBtn');

    if (countBadge) {
        if (hubAreas.length > 0) {
            countBadge.textContent = `${provinceCount} province${provinceCount !== 1 ? 's' : ''} · ${hubAreas.length} hub${hubAreas.length !== 1 ? 's' : ''}`;
            countBadge.style.display = '';
        } else {
            countBadge.style.display = 'none';
        }
    }
    if (viewAllBtn) {
        viewAllBtn.style.display = hubAreas.length > 0 ? '' : 'none';
    }

    Object.entries(byProvince).forEach(([province, areas]) => {
        const regional = areas.find(a => a.is_regional) || areas[0];
        const otherCount = areas.length - 1;

        const card = document.createElement('div');
        card.className = 'hub-province-card';

        const left = document.createElement('div');
        left.style.cssText = 'display:flex;align-items:center;gap:10px;min-width:0';

        const iconBox = document.createElement('div');
        iconBox.style.cssText = 'width:34px;height:34px;border-radius:10px;background:var(--auth-primary-soft);color:var(--auth-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0';
        iconBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
        left.appendChild(iconBox);

        const info = document.createElement('div');
        info.style.cssText = 'min-width:0';

        const titleRow = document.createElement('div');
        titleRow.style.cssText = 'display:flex;align-items:center;gap:6px;flex-wrap:wrap';

        const nameSpan = document.createElement('span');
        nameSpan.style.cssText = 'font-size:13px;font-weight:700;color:#1e293b';
        nameSpan.textContent = province;

        const badge = document.createElement('span');
        badge.style.cssText = 'font-size:10.5px;font-weight:700;color:var(--auth-primary);background:var(--auth-primary-soft);padding:1px 7px;border-radius:999px';
        badge.textContent = `${areas.length} hub${areas.length !== 1 ? 's' : ''}`;

        titleRow.append(nameSpan, badge);
        info.appendChild(titleRow);

        const subtitle = document.createElement('div');
        subtitle.style.cssText = 'font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
        if (regional) {
            subtitle.innerHTML = `★ Gateway: <strong>${regional.municipality}</strong>${otherCount > 0 ? ` <span style="color:#94a3b8">· +${otherCount} more</span>` : ''}`;
        } else {
            subtitle.textContent = `${areas.length} municipalities covered`;
        }
        info.appendChild(subtitle);
        left.appendChild(info);

        const actions = document.createElement('div');
        actions.style.cssText = 'display:flex;align-items:center;gap:6px;flex-shrink:0';

        const viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'btn-view-muni';
        viewBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            View Hubs
        `;
        viewBtn.onclick = () => openCoverageModal(province);

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn-del-prov';
        delBtn.title = `Remove ${province}`;
        delBtn.textContent = '×';
        delBtn.onclick = () => removeProvinceHubs(province);

        actions.append(viewBtn, delBtn);

        card.append(left, actions);
        list.appendChild(card);
    });
}

function syncHubAreas() {
    document.getElementById('hub_areas').value = JSON.stringify(hubAreas);
    renderHubAreaList();
    if (hubAreas.length) document.getElementById('hubAreasError').style.display = 'none';
}

function addHubArea() {
    const province = hubProvinceSelect.value;
    const checked  = [...hubMuniCheckboxes.querySelectorAll('.hub-muni-checkbox:checked')];
    if (!province || checked.length === 0) return;
    checked.forEach(cb => {
        const municipality = cb.value;
        if (!hubAreas.some(a => a.province === province && a.municipality === municipality)) {
            hubAreas.push({ province, municipality, is_regional: !hubAreas.some(a => a.province === province) });
        }
    });
    syncHubAreas();
    hubMuniPanel.style.display = 'none';
    hubMuniCheckboxes.querySelectorAll('.hub-muni-checkbox').forEach(cb => { cb.checked = false; });
    hubMuniSelectAll.checked = false;
    updateHubMuniLabel();

    // Automatically open modal showing the added municipalities for this province
    openCoverageModal(province);
}

function validateHubAreas() {
    if (hubAreas.length === 0) {
        document.getElementById('hubAreasError').style.display = 'block';
        document.getElementById('hubAreaList').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

// ── Logistics step flow: 7 → 8 → 2 → 3 → 4 → 5 → 6 ──
const LOGISTICS_STEPS = [7, 8, 2, 3, 4, 5, 6];

// Grab indicator items once, in DOM order (7 items).
const logisticsStepItems = Array.from(document.querySelectorAll('#stepIndicator .step-item'));

function logisticsSetStep(from, to) {
    document.getElementById('panel-' + from)?.classList.remove('active');
    document.getElementById('panel-' + to)?.classList.add('active');

    const toIdx = LOGISTICS_STEPS.indexOf(to); // 0-based
    logisticsStepItems.forEach((item, i) => {
        item.classList.remove('active', 'done');
        if (i === toIdx) item.classList.add('active');
        if (i <  toIdx) item.classList.add('done');
    });

    document.querySelector('.auth-form-panel')?.scrollTo(0, 0);
    if (to === 8) ensureHubProvincesLoaded();
}

// Province loading: reuse psgcProvinces if already loaded by register.js, or fetch directly.
let hubProvincesLoaded = false;
function populateHubProvinceSelect(data) {
    if (!hubProvinceSelect) return;
    hubProvinceSelect.innerHTML = '<option value="" disabled selected>Select province</option>';
    [...data].sort((a, b) => a.name.localeCompare(b.name)).forEach(item => {
        const o = document.createElement('option');
        o.value = item.name;
        o.dataset.code = item.code;
        o.textContent = item.name;
        hubProvinceSelect.appendChild(o);
    });
    hubProvinceSelect.disabled = false;
}

function ensureHubProvincesLoaded() {
    if (hubProvinceSelect && hubProvinceSelect.options.length > 1 && !hubProvinceSelect.disabled) {
        hubProvincesLoaded = true;
        return;
    }
    if (typeof psgcProvinces !== 'undefined' && Array.isArray(psgcProvinces) && psgcProvinces.length > 0) {
        populateHubProvinceSelect(psgcProvinces);
        hubProvincesLoaded = true;
        return;
    }
    if (hubProvincesLoaded) return;
    hubProvincesLoaded = true;
    if (hubProvinceSelect) {
        hubProvinceSelect.innerHTML = '<option value="" disabled selected>Loading provinces…</option>';
        hubProvinceSelect.disabled = true;
    }
    fetch(`${PSGC}/provinces/`)
        .then(r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(data => {
            populateHubProvinceSelect(data);
        })
        .catch(() => {
            if (hubProvinceSelect) {
                hubProvinceSelect.innerHTML = '<option value="" disabled selected>Failed — click to retry</option>';
                hubProvinceSelect.disabled = false;
            }
            hubProvincesLoaded = false;
        });
}

// Pre-load provinces immediately so they are ready before reaching Step 2
ensureHubProvincesLoaded();
hubProvinceSelect?.addEventListener('focus', () => {
    if (hubProvinceSelect.options.length <= 1) ensureHubProvincesLoaded();
});

// Keep a reference to register.js's original validateStep for steps it handles (2–5).
const _origValidateStep = validateStep;

// Replace register.js's nextStep/prevStep for logistics.
// These are function declarations on window, so assignment replaces them globally.
nextStep = function (current) {
    if (current === 7) {
        const bn = document.getElementById('business_name');
        if (!bn?.value.trim()) { showError(bn, 'Company name is required.'); return; }
        if (businessNameAvailable === false) { showError(bn, 'This company name is already registered.'); return; }
        if (businessNameAvailable === null && bn.value.trim().length >= 2) { showError(bn, 'Please wait for name check to complete.'); return; }
        if (!businessPermitBlob) { document.getElementById('businessPermitError').style.display = 'block'; return; }
    }
    if (current === 8) {
        if (!validateHubAreas()) return;
    }
    // Steps 2–5: delegate field validation to the original register.js validateStep.
    if (current >= 2 && current <= 5) {
        if (!_origValidateStep(current)) return;
    }

    const idx = LOGISTICS_STEPS.indexOf(current);
    if (idx === -1 || idx === LOGISTICS_STEPS.length - 1) return;
    logisticsSetStep(current, LOGISTICS_STEPS[idx + 1]);
};

prevStep = function (current) {
    const idx = LOGISTICS_STEPS.indexOf(current);
    if (idx <= 0) return;
    logisticsSetStep(current, LOGISTICS_STEPS[idx - 1]);
};

// For the account step (6): only T&C + credential fields — hub areas done in step 8.
// Override validateStep so the submit handler works correctly.
validateStep = function (step) {
    if (step !== 6) return _origValidateStep(step);
    return _origValidateStep(step); // credentials + T&C check is the same
};
</script>
</body>
</html>
