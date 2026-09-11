<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Staff Registration — PocketFinds</title>
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
        #panel-2 .file-upload-label {
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 480px) {
            #panel-2 .id-selfie-grid {
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
        .document-upload { min-height:140px; }
        .document-upload .upload-info { display:block;flex-basis:100%;margin-top:6px;font-size:10px;color:var(--auth-muted);line-height:1.35; }
        .document-upload [id$="Idle"] > svg { color:#aaa; }

        /* ── Hub & Company Step CSS ── */
        .address-summary-pill {
            margin: 12px 0 16px;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .address-summary-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .address-summary-left .pin-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .location-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .location-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--auth-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            line-height: 1.2;
        }
        .location-value {
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
        }
        .change-address-btn {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: var(--auth-primary);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 10px;
            flex-shrink: 0;
            transition: all .15s ease;
        }
        .change-address-btn:hover {
            background: var(--auth-primary-soft);
            border-color: var(--auth-primary);
        }

        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            margin-top: 6px;
        }
        .company-option-label {
            display: block;
            margin: 0;
            cursor: pointer;
        }
        .company-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 14px 10px;
            min-height: 118px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            background: #fff;
            transition: all .18s ease;
            text-align: center;
            box-sizing: border-box;
            user-select: none;
        }
        .company-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.04);
        }
        .company-card img {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
        }
        .company-card svg {
            color: #94a3b8;
        }
        .company-card .company-title {
            line-height: 1.25;
            word-break: break-word;
        }
        .company-card.selected,
        input[type="radio"]:checked + .company-card {
            border-color: var(--auth-primary) !important;
            background: var(--auth-primary-soft) !important;
            color: var(--auth-primary) !important;
            box-shadow: 0 0 0 1.5px var(--auth-primary) !important;
        }
        .company-card.disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            background: #f8fafc !important;
            border: 1.5px dashed #cbd5e1 !important;
            color: #94a3b8 !important;
            pointer-events: none !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .company-card.disabled img {
            filter: grayscale(1);
            opacity: 0.35;
        }
        .company-tag {
            font-size: 9.5px;
            font-weight: 700;
            padding: 2.5px 7px;
            border-radius: 6px;
            line-height: 1.2;
            text-align: center;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .company-tag.tag-hiring {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .company-tag.tag-not-hiring {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .company-tag.tag-no-hubs {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .hub-section-wrapper {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        /* ── Hub Display Card in Step 2 ── */
        .selected-hub-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1.5px solid #86efac;
            border-radius: 12px;
            background: #f0fdf4;
            transition: all .2s ease;
        }
        .selected-hub-card.nearby-chosen {
            border-color: var(--auth-primary);
            background: var(--auth-primary-soft);
        }
        .selected-hub-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .selected-hub-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #dcfce7;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .selected-hub-card.nearby-chosen .selected-hub-icon {
            background: #fff;
            color: var(--auth-primary);
            box-shadow: 0 2px 6px rgba(217,70,143,.12);
        }
        .selected-hub-details {
            min-width: 0;
        }
        .selected-hub-header {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .selected-hub-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #166534;
            line-height: 1.25;
        }
        .selected-hub-card.nearby-chosen .selected-hub-name {
            color: var(--auth-primary);
        }
        .selected-hub-desc {
            margin: 2px 0 0;
            font-size: 11.5px;
            color: #15803d;
            line-height: 1.3;
        }
        .selected-hub-card.nearby-chosen .selected-hub-desc {
            color: #475569;
        }
        .btn-change-hub {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #334155;
            font-size: 11.5px;
            font-weight: 700;
            padding: 6px 12px;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .15s ease;
        }
        .btn-change-hub:hover {
            border-color: var(--auth-primary);
            color: var(--auth-primary);
            background: #fff;
        }
        .no-hub-selected-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
        }
        .no-hub-icon {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: var(--auth-primary-soft);
            color: var(--auth-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .btn-browse-hubs {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--auth-primary);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 14px;
            cursor: pointer;
            flex-shrink: 0;
            transition: opacity .15s ease;
        }
        .btn-browse-hubs:hover {
            opacity: 0.9;
        }

        /* ── Resume Upload Card ── */
        .resume-upload-card {
            border: 1.5px dashed var(--auth-border, #cbd5e1);
            border-radius: 10px;
            padding: 12px 14px;
            background: #fafafa;
            margin-top: 10px;
            margin-bottom: 8px;
            transition: border-color .15s ease;
        }
        .resume-upload-card:hover {
            border-color: #94a3b8;
        }
        .resume-idle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .resume-idle-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .resume-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .btn-resume-browse {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border: 1px solid var(--auth-primary);
            color: var(--auth-primary);
            background: #fff;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .15s ease;
        }
        .btn-resume-browse:hover {
            background: var(--auth-primary-soft);
        }
        .resume-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .resume-file-info {
            min-width: 0;
        }
        .resume-file-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .resume-file-size {
            font-size: 11px;
            color: var(--auth-muted);
        }
        .btn-resume-remove {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #ef4444;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .btn-resume-remove:hover {
            background: #fecaca;
        }
        .hub-notice {
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 11.5px;
            line-height: 1.4;
        }
        .hub-notice.warning {
            background: #fff7ed;
            border: 1.5px solid #fed7aa;
            color: #9a3412;
        }
        .hub-notice.info {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            color: #334155;
        }

        /* ── Hubs Modal CSS ── */
        .hubs-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 10000;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
            animation: hubsModalFadeIn .15s ease-out;
        }
        @keyframes hubsModalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .hubs-modal-dialog {
            background: #fff;
            border-radius: 16px;
            width: min(540px, 100%);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: hubsModalSlideUp .18s ease-out;
        }
        @keyframes hubsModalSlideUp {
            from { transform: translateY(12px) scale(0.98); }
            to { transform: translateY(0) scale(1); }
        }
        .hubs-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }
        .hubs-modal-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .hubs-modal-sub {
            margin: 3px 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .hubs-modal-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            transition: color .15s ease;
        }
        .hubs-modal-close:hover {
            color: #0f172a;
        }
        .hubs-modal-search {
            padding: 10px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafafa;
            flex-shrink: 0;
        }
        .hubs-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .hubs-search-wrapper svg {
            position: absolute;
            left: 11px;
            color: #94a3b8;
            pointer-events: none;
        }
        .hubs-search-input {
            width: 100%;
            padding: 8px 32px 8px 34px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12.5px;
            background: #fff;
            outline: none;
            transition: border-color .15s ease;
            box-sizing: border-box;
        }
        .hubs-search-input:focus {
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 2px var(--auth-primary-soft);
        }
        .hubs-modal-body {
            padding: 14px 20px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 120px;
            max-height: 52vh;
        }
        .hubs-modal-footer {
            padding: 12px 20px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }
        .hubs-footer-left {
            font-size: 12px;
            color: #475569;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .hubs-selection-val {
            color: #0f172a;
            margin-left: 4px;
        }
        .hubs-footer-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .btn-hubs-cancel {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            transition: all .15s ease;
        }
        .btn-hubs-cancel:hover {
            background: #f1f5f9;
        }
        .btn-hubs-confirm {
            background: var(--auth-primary);
            border: none;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            transition: opacity .15s ease;
        }
        .btn-hubs-confirm:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-hubs-confirm:not(:disabled):hover {
            opacity: 0.9;
        }

        .hub-choice-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: all .15s ease;
            box-sizing: border-box;
            user-select: none;
        }
        .hub-choice-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }
        .hub-choice-card.selected {
            border-color: var(--auth-primary) !important;
            background: var(--auth-primary-soft) !important;
            box-shadow: 0 0 0 1.5px var(--auth-primary) !important;
        }
        .hub-choice-card.direct-match {
            border-color: #86efac;
            background: #fff;
        }
        .hub-choice-card.direct-match.selected {
            border-color: #16a34a !important;
            background: #f0fdf4 !important;
            box-shadow: 0 0 0 1.5px #16a34a !important;
        }
        .hub-choice-card.not-hiring {
            opacity: 0.55 !important;
            background: #f8fafc !important;
            border-style: dashed !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .hub-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 999px;
            line-height: 1;
            white-space: nowrap;
        }
        .hub-badge-hiring {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .hub-badge-not-hiring {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .hub-badge-regional {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }
        @media (max-width: 480px) {
            .company-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .company-card {
                padding: 10px 6px;
                min-height: 105px;
                font-size: 11px;
            }
            .company-card img {
                width: 36px;
                height: 36px;
            }
            .hub-choice-card {
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="{{ url('/') }}">
                    <span class="auth-logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></span>
                    <span><span class="auth-logo-pocket">Pocket</span><span class="auth-logo-finds">Finds</span></span>
                </a>
                <h1 class="auth-brand-title">Join a hub team.</h1>
                <p class="auth-brand-text">
                    Work at a logistics hub — sort parcels, process pickups, and keep deliveries moving. Fill in your details and your account will be reviewed by our admin team.
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

                {{-- Step indicator (6 Steps: Address -> Company & Hub -> ID & Selfie -> Personal -> Contact -> Account) --}}
                @php
                    $regType = 'hub_staff';
                    $typeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>';
                @endphp
                <div class="steps" id="stepIndicator">
                    <div class="step-item active" data-step="5">
                        <div class="step-circle">1</div>
                        <span class="step-label">Address</span>
                    </div>
                    <div class="step-item" data-step="8">
                        <div class="step-circle">2</div>
                        <span class="step-label">Company &amp; Hub</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle">3</div>
                        <span class="step-label">Documents</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle">4</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle">5</div>
                        <span class="step-label">Contact</span>
                    </div>
                    <div class="step-item" data-step="6">
                        <div class="step-circle">6</div>
                        <span class="step-label">Account</span>
                    </div>
                </div>

                <form id="buyerForm" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="account_type" value="logistics">
                    <input type="hidden" name="logistics_mode" value="join">
                    <input type="hidden" name="auth_method" value="{{ ($isGoogleSignup ?? false) ? 'google' : 'manual' }}">
                    @if($isGoogleSignup ?? false)
                        <input type="hidden" name="google_id" value="{{ $googleId }}">
                    @endif
                    <input type="hidden" name="logistics_hub_id" id="selected_hub_id" value="">

                    {{-- ── STEP 1: Address (Panel 5) ── --}}
                    <div class="step-panel active" id="panel-5">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Your Address</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Tell us where you live so we can find hubs in your area.</p>
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
                                <label class="auth-label" for="house_no">House / Unit No.</label>
                                <input class="auth-input" id="house_no" name="house_no" type="text" placeholder="e.g. Blk 1 Lot 2">
                            </div>
                            <div class="auth-field">
                                <label class="auth-label" for="street">Street Address</label>
                                <input class="auth-input" id="street" name="street" type="text" placeholder="e.g. Rizal St.">
                            </div>
                        </div>

                        <div class="step-nav" style="margin-top:14px">
                            <span></span>
                            <button type="button" class="btn-next" onclick="nextStep(5)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 2: Company & Hub (Panel 8) ── --}}
                    <div class="step-panel" id="panel-8">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Company &amp; Hub</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Select a logistics company and the hub you want to join.</p>
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
                            <button type="button" class="change-address-btn" onclick="prevStep(8)">Change</button>
                        </div>

                        {{-- 1. Company Picker --}}
                        <div class="auth-field full" style="margin-bottom:14px">
                            <label class="auth-label">1. Which company do you want to join? <span class="auth-required">*</span></label>
                            <div class="company-grid" id="joinCompanyGrid">
                                @forelse($logisticsCompanies ?? [] as $i => $lc)
                                <label class="company-option-label" id="companyLabel_{{ $i }}">
                                    <input type="radio" name="business_name" value="{{ $lc->business_name }}" id="jlc_{{ $i }}" style="display:none" onchange="onCompanyChange()">
                                    <div class="company-card" data-for="jlc_{{ $i }}" id="companyCard_{{ $i }}">
                                        @if($lc->company_logo)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($lc->company_logo) }}" alt="{{ $lc->business_name }}">
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                        @endif
                                        <span class="company-title">{{ $lc->business_name }}</span>
                                        <span class="company-tag" id="companyTag_{{ $i }}" style="display:none"></span>
                                    </div>
                                </label>
                                @empty
                                <p style="grid-column:1/-1;color:var(--auth-muted);font-size:13px">No logistics companies are open to join yet — please check back later or contact support.</p>
                                @endforelse
                            </div>
                            <span id="companyError" style="display:none;color:red;font-size:11px;margin-top:8px">Please choose an available company.</span>
                        </div>

                        {{-- 2. Hub Selection & Hiring Status Section --}}
                        <div id="hubSection" class="hub-section-wrapper" style="display:none">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                                <label class="auth-label" style="margin:0">2. Hub to Join <span class="auth-required">*</span></label>
                            </div>
                            <div id="hubNoticeContainer" style="display:none;margin-bottom:10px"></div>
                            <div id="hubSelectionContainer"></div>
                            <span id="hubSelectError" style="display:none;color:red;font-size:11px;margin-top:8px">Please select an available hiring hub to continue.</span>
                        </div>

                        <div class="step-nav" style="margin-top:16px">
                            <button type="button" class="btn-prev" onclick="prevStep(8)">← Back</button>
                            <button type="button" class="btn-next" id="btnStep8Next" onclick="nextStep(8)">Continue →</button>
                        </div>
                    </div>

                    {{-- Company Terms & Conditions json --}}
                    <script type="application/json" id="companyTermsData">{!! str_replace('</script', '<\/script', json_encode(
                        collect($companyPolicies ?? [])->mapWithKeys(fn ($p) => [$p->company_name => $p->content])
                    )) !!}</script>

                    {{-- ── STEP 3: ID, Selfie & Resume (Panel 2) ── --}}
                    <div class="step-panel" id="panel-2">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">ID, Selfie &amp; Resume</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Upload your ID, take a selfie, and attach your resume.</p>
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
                                    <div id="idCamera" style="display:none;position:relative">
                                        <video id="idVideo" autoplay playsinline style="width:100%;max-height:140px;object-fit:cover;display:block"></video>
                                        <button type="button" onclick="snapIdPhoto()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--auth-primary,#e74c3c);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </div>
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

                        {{-- OCR status --}}
                        <div id="ocrResult" class="ocr-result"></div>

                        <div class="step-nav" style="margin-top:12px">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn-next" id="btnStep2Next" onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>

                    {{-- ── STEP 4: Personal Info (Panel 3) ── --}}
                    <div class="step-panel" id="panel-3">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Personal Information</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
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

                    {{-- ── STEP 5: Contact Details (Panel 4) ── --}}
                    <div class="step-panel" id="panel-4">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Contact Details</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
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

                    {{-- ── STEP 6: Account Credentials (Panel 6) ── --}}
                    <div class="step-panel" id="panel-6">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Account Setup</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">{!! $typeIcon !!} Hub Staff</span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Create your username and password for signing in.</p>
                            <a href="{{ route('register.delivery-team') }}" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        <div class="auth-form-grid">
                            {{-- Username --}}
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

                {{-- Terms & Conditions Modal --}}
                <div id="tcModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:18px;width:min(520px,94vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25)">
                        <div style="padding:20px 22px;border-bottom:1px solid #f1f5f9;flex-shrink:0">
                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--auth-primary)" id="tcModalTitle">{{ $terms->title ?? 'Terms & Conditions' }}</span>
                        </div>
                        <div id="tcContent" style="flex:1;overflow-y:auto;padding:18px 22px;font-size:13px;line-height:1.7;color:#374151;white-space:pre-wrap">{{ $terms->content ?? 'Terms & Conditions are not available right now — please contact support.' }}</div>
                        <div style="padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0">
                            <span id="tcScrollHint" style="font-size:11px;color:var(--auth-muted)">Scroll to the bottom to continue</span>
                            <button type="button" id="tcCloseBtn" onclick="closeTc()" style="padding:8px 20px;background:var(--auth-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;flex-shrink:0">Close</button>
                        </div>
                    </div>
                </div>

                {{-- Nearby Hubs Selection Modal --}}
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

                        {{-- Search input --}}
                        <div class="hubs-modal-search" id="hubsModalSearchBox">
                            <div class="hubs-search-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="hubsSearchInput" placeholder="Search municipality or hub..." oninput="filterHubsModal(this.value)">
                            </div>
                        </div>

                        {{-- List of hubs --}}
                        <div class="hubs-modal-body" id="hubsModalList">
                            {{-- Hub cards injected dynamically --}}
                        </div>

                        {{-- Modal Footer --}}
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

<script>
const IS_GOOGLE_SIGNUP = @json($isGoogleSignup ?? false);
const COMPANY_HUBS = @json($companyHubs ?? (object) []);
const COMPANY_TERMS = JSON.parse(document.getElementById('companyTermsData')?.textContent || '{}');

// Step sequence: 5 (Address) -> 8 (Company & Hub) -> 2 (ID & Selfie) -> 3 (Personal) -> 4 (Contact) -> 6 (Account)
const STAFF_STEPS = [5, 8, 2, 3, 4, 6];
</script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>
<script>
function staffSetStep(from, to) {
    document.getElementById('panel-' + from)?.classList.remove('active');
    document.getElementById('panel-' + to)?.classList.add('active');

    if (from === 2) stopCamera();

    const toIdx = STAFF_STEPS.indexOf(to);
    document.querySelectorAll('#stepIndicator .step-item').forEach((item, i) => {
        item.classList.remove('active', 'done');
        if (i === toIdx) item.classList.add('active');
        if (i < toIdx)  item.classList.add('done');
    });

    document.querySelector('.auth-form-panel')?.scrollTo(0, 0);

    if (to === 8) {
        refreshCompanyAndHubOptions();
    }
}

let lastProcessedAddressKey = '';

// ── Reset company and hub selection when address changes ──
function resetCompanyAndHubStep() {
    // 1. Uncheck all company radios & remove selection classes
    document.querySelectorAll('input[name="business_name"]').forEach(r => {
        r.checked = false;
    });
    document.querySelectorAll('#joinCompanyGrid .company-card').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = '';
        card.style.background  = '';
        card.style.color       = '';
    });
    lastSelectedCompany = null;
    const coErr = document.getElementById('companyError');
    if (coErr) coErr.style.display = 'none';

    // 2. Clear hub selection and reset preview/modal state
    currentSelectedHubId = null;
    tempModalHubId = null;
    const hubInput = document.getElementById('selected_hub_id');
    if (hubInput) hubInput.value = '';

    const hubSection = document.getElementById('hubSection');
    if (hubSection) hubSection.style.display = 'none';

    const hubContainer = document.getElementById('hubSelectionContainer');
    if (hubContainer) hubContainer.innerHTML = '';

    const noticeEl = document.getElementById('hubNoticeContainer');
    if (noticeEl) {
        noticeEl.style.display = 'none';
        noticeEl.innerHTML = '';
    }

    const hubErr = document.getElementById('hubSelectError');
    if (hubErr) hubErr.style.display = 'none';
}

// ── Refresh company cards and hub availability based on staff address ──
function refreshCompanyAndHubOptions() {
    const province     = (document.getElementById('province')?.value || '').trim();
    const municipality = (document.getElementById('municipality')?.value || '').trim();

    const pillText = document.getElementById('pillAddressText');
    if (pillText) {
        pillText.textContent = (municipality ? municipality + ', ' : '') + (province || 'Not specified');
    }

    // Check each company against the staff's province
    let anyEnabled = false;
    document.querySelectorAll('#joinCompanyGrid .company-card').forEach((card, idx) => {
        const radio = document.getElementById(card.dataset.for);
        if (!radio) return;
        const companyName = radio.value;
        const hubs = COMPANY_HUBS[companyName] || [];
        const provHubs = hubs.filter(h => h.province.toLowerCase() === province.toLowerCase());
        const tag = document.getElementById('companyTag_' + idx);

        if (provHubs.length === 0) {
            // No hubs in this province -> disable company
            card.classList.add('disabled');
            card.classList.remove('selected');
            radio.disabled = true;
            if (radio.checked) {
                radio.checked = false;
            }
            if (tag) {
                tag.style.display = 'block';
                tag.className = 'company-tag tag-no-hubs';
                tag.textContent = province ? 'No hubs in ' + province : 'No hubs nearby';
            }
        } else {
            // Has hubs in this province -> enabled!
            card.classList.remove('disabled');
            radio.disabled = false;
            anyEnabled = true;
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

    document.querySelectorAll('#joinCompanyGrid .company-card').forEach(card => {
        const radio = document.getElementById(card.dataset.for);
        if (radio?.disabled) {
            card.classList.add('disabled');
            card.classList.remove('selected');
        } else {
            card.classList.remove('disabled');
            if (radio?.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }
        card.style.borderColor = '';
        card.style.background  = '';
        card.style.color       = '';
    });

    document.getElementById('companyError').style.display = 'none';

    // Update Company T&C if custom terms exist
    if (selectedCompany && COMPANY_TERMS[selectedCompany]) {
        const titleEl = document.getElementById('tcModalTitle');
        const contentEl = document.getElementById('tcContent');
        if (titleEl) titleEl.textContent = `${selectedCompany} — Terms & Conditions`;
        if (contentEl) contentEl.textContent = COMPANY_TERMS[selectedCompany];
    }

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

    // Retain selection only if still valid for this company
    if (currentSelectedHubId && !activeProvHubs.some(h => h.id === currentSelectedHubId)) {
        currentSelectedHubId = null;
        document.getElementById('selected_hub_id').value = '';
    }

    // Direct municipality match check
    const directMatch = activeProvHubs.find(h => h.municipality.toLowerCase() === municipality.toLowerCase());

    // When the staff's own municipality really does have a hub here, and it's hiring,
    // surface it as the pre-picked choice right away instead of making them dig for
    // it in the "browse all hubs" modal — they can still switch via Change if they want.
    if (directMatch && directMatch.is_hiring && !currentSelectedHubId) {
        currentSelectedHubId = directMatch.id;
    }

    // Setup Notice banners
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
                        <strong>The ${directMatch.municipality} Hub is currently not hiring staff.</strong>
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

    if (!currentSelectedHubId) {
        container.innerHTML = `
        <div class="no-hub-selected-card">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="no-hub-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <strong style="font-size:13px;color:#1e293b">No hub selected yet</strong>
                    <p style="margin:2px 0 0;font-size:11.5px;color:var(--auth-muted)">Please choose an available hiring hub in ${province} to apply.</p>
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
                        ? 'Your local municipality hub. You will be assigned to work here.'
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
    if (subEl)   subEl.textContent   = `Select a hub to join for ${selectedCompany} (${activeProvHubs.length} total)`;

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

// ── Resume Upload Handlers ──
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

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeHubModal();
        if (typeof closeTc === 'function') closeTc();
    }
});

// ── Step Navigation ──
nextStep = function (current) {
    if (current === 5) {
        // Step 1: Address
        const prov = document.getElementById('province');
        const muni = document.getElementById('municipality');
        const brgy = document.getElementById('barangay');
        let valid = true;
        clearError(prov); clearError(muni); clearError(brgy);
        if (!prov?.value) { showError(prov, 'Please select your province.'); valid = false; }
        if (!muni?.value) { showError(muni, 'Please select your city / municipality.'); valid = false; }
        if (!brgy?.value) { showError(brgy, 'Please select your barangay.'); valid = false; }
        if (!valid) return;

        const currentAddrKey = `${(prov?.value || '').trim().toLowerCase()}|${(muni?.value || '').trim().toLowerCase()}|${(brgy?.value || '').trim().toLowerCase()}`;
        if (lastProcessedAddressKey && lastProcessedAddressKey !== currentAddrKey) {
            resetCompanyAndHubStep();
        }
        lastProcessedAddressKey = currentAddrKey;
    } else if (current === 8) {
        // Step 2: Company & Hub
        const company = document.querySelector('input[name="business_name"]:checked')?.value;
        if (!company) {
            document.getElementById('companyError').style.display = 'block';
            document.getElementById('joinCompanyGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const hubId = document.getElementById('selected_hub_id')?.value;
        if (!hubId) {
            const errEl = document.getElementById('hubSelectError');
            if (errEl) {
                errEl.style.display = 'block';
                errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
    } else if (current === 2) {
        // Step 3: ID & Selfie
        if (!idPhotoBlob) {
            document.getElementById('idPhotoError').style.display = 'block';
            return;
        }
        if (!selfieBlob) {
            document.getElementById('selfieError').style.display = 'block';
            return;
        }
    } else if (current === 3) {
        // Step 4: Personal
        const panel = document.getElementById('panel-3');
        let valid = true;
        panel.querySelectorAll('input, select').forEach(el => {
            clearError(el);
            const val = el.value.trim();
            if (rules[el.id]) { const msg = rules[el.id](val); if (msg) { showError(el, msg); valid = false; return; } }
            if (el.hasAttribute('required') && !val) { showError(el, 'This field is required.'); valid = false; }
        });
        if (!valid) return;
    } else if (current === 4) {
        // Step 5: Contact
        if (!IS_GOOGLE_SIGNUP && !emailVerified) {
            showError(document.getElementById('email'), 'Please verify your email address first.');
            return;
        }
        const phone = document.getElementById('contact_no');
        if (!phone?.value.trim() || phone.value.trim().length !== 11) {
            showError(phone, 'Enter a valid 11-digit mobile number.');
            return;
        }
    }

    const idx = STAFF_STEPS.indexOf(current);
    if (idx === -1 || idx === STAFF_STEPS.length - 1) return;
    staffSetStep(current, STAFF_STEPS[idx + 1]);
};

prevStep = function (current) {
    const idx = STAFF_STEPS.indexOf(current);
    if (idx <= 0) return;
    staffSetStep(current, STAFF_STEPS[idx - 1]);
};

// Override validateStep so final submission passes without requiring a company business permit
validateStep = function (step) {
    if (step === 6) {
        let valid = true;
        const panel = document.getElementById('panel-6');
        const tcCb = document.getElementById('tcCheckbox');
        if (tcCb && !tcCb.checked) {
            document.getElementById('tcError').style.display = 'block';
            valid = false;
        } else if (tcCb) {
            document.getElementById('tcError').style.display = 'none';
        }
        const uEl = document.getElementById('username');
        if (uEl) {
            if (!uEl.value.trim()) { showError(uEl, 'Username is required.'); valid = false; }
            else if (uEl.value.trim().length < 8) { showError(uEl, 'Username must be at least 8 characters.'); valid = false; }
            else if (!/^[a-zA-Z0-9_-]+$/.test(uEl.value.trim())) { showError(uEl, 'Only letters, numbers, underscores and dashes.'); valid = false; }
            else if (usernameAvailable === false) { showError(uEl, 'Username is already taken.'); valid = false; }
            else if (usernameAvailable === null) { showError(uEl, 'Please wait for username check to complete.'); valid = false; }
        }
        const pw = document.getElementById('password');
        const pc = document.getElementById('password_confirmation');
        if (pw && pc && pc.value !== pw.value) { showError(pc, 'Passwords do not match.'); valid = false; }
        if (pw && !pw.value.trim()) { showError(pw, 'Password is required.'); valid = false; }

        const company = document.querySelector('input[name="business_name"]:checked')?.value;
        const hubId   = document.getElementById('selected_hub_id')?.value;
        if (!company || !hubId) {
            showRegisterErrorModal('Please ensure you have chosen a company and an available hiring hub.');
            valid = false;
        }

        return valid;
    }
    return true;
};

// Re-check options and reset company/hub if address fields change
document.getElementById('province')?.addEventListener('change', () => {
    resetCompanyAndHubStep();
    setTimeout(refreshCompanyAndHubOptions, 200);
});
document.getElementById('municipality')?.addEventListener('change', () => {
    resetCompanyAndHubStep();
    setTimeout(refreshCompanyAndHubOptions, 200);
});
document.getElementById('barangay')?.addEventListener('change', () => {
    resetCompanyAndHubStep();
});
</script>
</body>
</html>
