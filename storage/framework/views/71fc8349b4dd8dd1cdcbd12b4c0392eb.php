<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Registration</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/register.css')); ?>">
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
        .document-upload .upload-info { display:block;flex-basis:100%;margin-top:6px;font-size:10px;color:var(--auth-muted);line-height:1.35; }
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
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <main class="auth-card">

        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <a class="auth-logo" href="<?php echo e(url('/')); ?>">
                    <span class="auth-logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span>PocketFinds</span>
                </a>
                <h1 class="auth-brand-title">Almost there.</h1>
                <p class="auth-brand-text">
                    Your Google account has been connected. Just fill in a few more details to complete your
                    <?php echo e(ucfirst(request('type', 'buyer'))); ?> registration.
                </p>
                <ul class="auth-brand-points">
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg> Google account connected</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg> Email pre-filled from Google</li>
                    <li><span class="auth-check"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span> <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Set a password for direct login</li>
                </ul>
            </div>
            <div class="auth-brand-footer">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">

                
                <?php
                    $regType = session('oauth_account_type', request('type', 'buyer'));
                    $typeIcons = [
                        'buyer'  => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.57l1.65-8.42H6"/></svg>',
                        'seller' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>',
                        'rider'  => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 6h11l-3-6z"/></svg>',
                    ];
                ?>
                <div class="steps" id="stepIndicator">
                    <div class="step-item seller-only" data-step="0" style="display:none">
                        <div class="step-circle">1</div>
                        <span class="step-label">Category</span>
                    </div>
                    <div class="step-item active" data-step="1" id="idSelfieStepItem">
                        <div class="step-circle" id="idSelfieStepCircle">1</div>
                        <span class="step-label">ID & Selfie</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle" id="personalStepCircle">2</div>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle" id="contactStepCircle">3</div>
                        <span class="step-label">Contact</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle" id="addressStepCircle">4</div>
                        <span class="step-label">Address</span>
                    </div>
                    <div class="step-item" data-step="5">
                        <div class="step-circle" id="accountStepCircle">5</div>
                        <span class="step-label">Account</span>
                    </div>
                </div>

                <form id="googleRegForm" method="POST" action="<?php echo e(route('register.store')); ?>" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="account_type" value="<?php echo e(session('oauth_account_type', request('type', 'buyer'))); ?>">
                    <input type="hidden" name="auth_method" value="google">
                    <input type="hidden" name="google_id" value="<?php echo e(session('google_id')); ?>">

                    
                    <div class="step-panel" id="panel-0">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Shop Category</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Add your business details and choose the category your products belong to.</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>
                        <input type="hidden" name="category_id" id="category_id_input">
                        <div class="auth-field" id="businessNameField" style="margin:14px 0 12px">
                            <label class="auth-label" for="business_name">Business Name <span class="auth-required">*</span></label>
                            <input class="auth-input" id="business_name" name="business_name" type="text" placeholder="e.g. Dela Cruz Trading" required maxlength="150">
                        </div>
                        <div class="auth-field" id="businessPermitField" style="margin-bottom:12px">
                            <label class="auth-label">Business Permit <span class="auth-required">*</span></label>
                            <div class="upload-box document-upload" id="businessPermitBox" style="cursor:pointer">
                                <div id="businessPermitIdle" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 8px;text-align:center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:6px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    <p style="margin:0 0 7px;font-size:11px;color:#888">Upload business permit</p>
                                    <label for="business_permit_file" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--auth-primary);color:var(--auth-primary);background:#fff;border-radius:8px;font-size:11px;cursor:pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        Upload
                                    </label>
                                    <span class="upload-info">Clear photo or scan · JPG, PNG, or PDF · max 5 MB</span>
                                </div>
                                <div id="businessPermitPreview" style="display:none;position:relative">
                                    <img id="businessPermitImg" style="width:100%;max-height:120px;object-fit:cover;border-radius:6px" alt="Business permit">
                                    <button type="button" class="enlarge-btn" onclick="event.stopPropagation();openLightbox('businessPermitImg')" aria-label="Enlarge business permit">⤢</button>
                                    <button type="button" onclick="event.stopPropagation();clearBusinessPermit()" style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer">✕</button>
                                </div>
                            </div>
                            <input type="file" id="business_permit_file" name="business_permit_file" accept="image/*,.pdf" style="display:none" required>
                            <span id="businessPermitError" style="display:none;color:red;font-size:11px">Please upload your business permit.</span>
                        </div>
                        <div class="category-scroll-wrap">
                            <div class="category-box-grid" id="categoryGrid"><p class="auth-muted-text">Loading categories…</p></div>
                        </div>
                        <div class="step-nav" style="margin-top:16px">
                            <button type="button" class="btn-next" id="categoryNextBtn" onclick="nextStep(0)">Continue →</button>
                        </div>
                    </div>

                    
                    <div class="step-panel" id="panel-1">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">ID & Selfie</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Upload your ID — we'll pre-fill your details automatically.</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        
                        <div class="auth-field" style="margin-bottom:12px">
                            <label class="auth-label" for="id_type_id">ID Type <span class="auth-required">*</span></label>
                            <select class="auth-input auth-select" id="id_type_id" name="id_type_id" required>
                                <option value="" disabled selected>Select ID type</option>
                                <?php $__currentLoopData = DB::table('id_types')->orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($idType->id); ?>"><?php echo e($idType->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        
                        <div class="id-selfie-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px" id="idSelfieGrid">

                            
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

                        
                        <div id="ocrResult" class="ocr-result"></div>

                        <div class="step-nav" style="margin-top:12px">
                            <button type="button" class="btn-prev seller-only" style="display:none" onclick="prevStep(1)">← Back</button>
                            <button type="button" class="btn-next" id="btnStep1Next" onclick="nextStep(1)">Continue →</button>
                        </div>
                    </div>

                    
                    <div class="step-panel" id="panel-2">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Personal Information</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Review and correct your details if needed.</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
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
                            <button type="button" class="btn-prev" onclick="prevStep(2)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(2)">Continue →</button>
                        </div>
                    </div>

                    
                    <div class="step-panel" id="panel-3">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Contact Details</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">How can we reach you?</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="email">Email address <span class="auth-required">*</span></label>
                                <input class="auth-input input-readonly" id="email" name="email" type="email" readonly required>
                            </div>
                            <div class="auth-field full">
                                <label class="auth-label" for="contact_no">Contact number <span class="auth-required">*</span></label>
                                <input class="auth-input" id="contact_no" name="contact_no" type="tel" placeholder="09XXXXXXXXX" maxlength="11" required>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-prev" onclick="prevStep(3)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(3)">Continue →</button>
                        </div>
                    </div>

                    
                    <div class="step-panel" id="panel-4">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Address</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Where are you located?</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
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
                            <button type="button" class="btn-prev" onclick="prevStep(4)">← Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(4)">Continue →</button>
                        </div>
                    </div>

                    
                    <div class="step-panel" id="panel-5">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                            <h2 class="auth-title" style="margin:0">Account Setup</h2>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;background:var(--auth-primary-soft);border:1px solid rgba(217,70,143,.2);color:var(--auth-primary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em"><?php echo $typeIcons[$regType] ?? $typeIcons['buyer']; ?> <?php echo e(ucfirst($regType)); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <p class="auth-subtitle" style="margin:0">Create your username and a password for direct login.</p>
                            <a href="<?php echo e(route('register.type')); ?>" title="Change account type" style="flex-shrink:0;margin-left:8px;color:var(--auth-muted);text-decoration:none;line-height:1" onmouseover="this.style.color='var(--auth-primary)'" onmouseout="this.style.color='var(--auth-muted)'"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></a>
                        </div>

                        <div class="auth-form-grid">
                            <div class="auth-field full">
                                <label class="auth-label" for="username">Username <span class="auth-required">*</span></label>
                                <div style="position:relative">
                                    <input class="auth-input" id="username" name="username" type="text" placeholder="e.g. juandelacruz" required minlength="8" maxlength="30" autocomplete="off">
                                    <span id="usernameStatus" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px"></span>
                                </div>
                                <div id="usernameSuggestions" style="display:none;margin-top:6px;font-size:12px;color:var(--auth-muted)"></div>
                            </div>
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
                            <button type="button" class="btn-prev" onclick="prevStep(5)">← Back</button>
                            <button type="submit" class="btn-submit">Submit Registration</button>
                        </div>
                    </div>
                </form>

                
                <div id="tcModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,25,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:18px;width:min(480px,94vw);max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25)">
                        <div style="padding:20px 22px 0;flex-shrink:0">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--auth-primary)">Terms &amp; Conditions</span>
                                <span id="tcProgress" style="font-size:11px;color:var(--auth-muted)">Question 1 of 5</span>
                            </div>
                            <div style="height:4px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin-bottom:16px">
                                <div id="tcBar" style="height:100%;width:20%;background:var(--auth-primary);border-radius:99px;transition:width .35s ease"></div>
                            </div>
                        </div>
                        <div id="tcSlides" style="flex:1;overflow:hidden;position:relative;min-height:260px">
                            <div class="tc-slide" style="position:absolute;inset:0;padding:0 22px 22px;overflow-y:auto;transition:transform .3s ease,opacity .3s ease">
                                <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
                                    <strong style="display:block;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Account Use</strong>
                                    Your PocketFinds account is for personal use only. You may not share, sell, or transfer your account to another person. You are responsible for all activity that occurs under your account.
                                </div>
                                <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">Quick check — who is responsible for activity on your account?</p>
                                <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">PocketFinds support team</button>
                                    <button type="button" class="tc-opt" data-correct="true" onclick="tcAnswer(this)">You, the account holder</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Anyone who uses your device</button>
                                </div>
                                <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>
                            </div>
                            <div class="tc-slide" style="position:absolute;inset:0;padding:0 22px 22px;overflow-y:auto;transform:translateX(100%);opacity:0;transition:transform .3s ease,opacity .3s ease">
                                <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
                                    <strong style="display:block;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Privacy &amp; Data</strong>
                                    We collect only the information needed to verify your identity and operate your account. Your data is never sold to third parties. You may request deletion of your account and data at any time.
                                </div>
                                <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">Does PocketFinds sell your personal data to third parties?</p>
                                <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Yes, to improve ads</button>
                                    <button type="button" class="tc-opt" data-correct="true" onclick="tcAnswer(this)">No, never</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Only with your password</button>
                                </div>
                                <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>
                            </div>
                            <div class="tc-slide" style="position:absolute;inset:0;padding:0 22px 22px;overflow-y:auto;transform:translateX(100%);opacity:0;transition:transform .3s ease,opacity .3s ease">
                                <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
                                    <strong style="display:block;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Prohibited Content</strong>
                                    You may not list counterfeit, illegal, or prohibited items on PocketFinds. Violations may result in immediate account suspension and may be reported to relevant authorities.
                                </div>
                                <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">What happens if you list prohibited items?</p>
                                <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">You get a warning email only</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Nothing, it's allowed</button>
                                    <button type="button" class="tc-opt" data-correct="true" onclick="tcAnswer(this)">Account suspension and possible reporting</button>
                                </div>
                                <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>
                            </div>
                            <div class="tc-slide" style="position:absolute;inset:0;padding:0 22px 22px;overflow-y:auto;transform:translateX(100%);opacity:0;transition:transform .3s ease,opacity .3s ease">
                                <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
                                    <strong style="display:block;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Reviews &amp; Ratings</strong>
                                    Reviews must be honest and based on real transactions. Fake reviews, review manipulation, or incentivized reviews are strictly prohibited and will result in removal of the review and possible account action.
                                </div>
                                <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">Are you allowed to pay someone to leave you a good review?</p>
                                <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
                                    <button type="button" class="tc-opt" data-correct="true" onclick="tcAnswer(this)">No, that's strictly prohibited</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Yes, if it's a small amount</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">Only for your first 10 reviews</button>
                                </div>
                                <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>
                            </div>
                            <div class="tc-slide" style="position:absolute;inset:0;padding:0 22px 22px;overflow-y:auto;transform:translateX(100%);opacity:0;transition:transform .3s ease,opacity .3s ease">
                                <div style="background:var(--auth-primary-soft);border-left:3px solid var(--auth-primary);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.7;color:#374151">
                                    <strong style="display:block;margin-bottom:4px;display:flex;align-items:center;gap:6px"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Account Changes</strong>
                                    PocketFinds reserves the right to update these terms at any time. Continued use of the platform after changes are posted means you accept the updated terms. You will be notified of major changes via email.
                                </div>
                                <p style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px">If PocketFinds updates the terms and you keep using the app, what does that mean?</p>
                                <div class="tc-options" style="display:flex;flex-direction:column;gap:8px">
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">You need to re-register</button>
                                    <button type="button" class="tc-opt" data-correct="true" onclick="tcAnswer(this)">You accept the updated terms</button>
                                    <button type="button" class="tc-opt" data-correct="false" onclick="tcAnswer(this)">The old terms still apply to you</button>
                                </div>
                                <p class="tc-feedback" style="display:none;margin:10px 0 0;font-size:12px;border-radius:8px;padding:8px 12px"></p>
                            </div>
                        </div>
                        <div style="padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
                            <button type="button" id="tcCloseBtn" onclick="closeTc()" style="font-size:12px;color:var(--auth-muted);background:none;border:none;cursor:pointer;padding:0">✕ Close</button>
                            <button type="button" id="tcNextBtn" onclick="tcNext()" disabled style="padding:8px 20px;background:var(--auth-primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;opacity:.4;transition:opacity .2s">Next →</button>
                        </div>
                    </div>
                </div>

                
                <div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()">
                    <button class="img-lightbox-close" onclick="closeLightbox()">&times;</button>
                    <img id="lightboxImg" src="" alt="Preview" style="display:none">
                    <div id="lightboxPdf" style="display:none;flex-direction:column;align-items:center;gap:16px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <p id="lightboxPdfName" style="color:#fff;font-size:14px;margin:0"></p>
                        <a id="lightboxPdfLink" href="" target="_blank" onclick="event.stopPropagation()" style="padding:10px 22px;background:var(--auth-primary);color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none">Open PDF</a>
                    </div>
                </div>

                
                <div class="success-screen" id="successScreen">
                    <div class="success-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                    <h3>Registration Submitted!</h3>
                    <p>
                        Thank you for registering with Google. Please wait for the administrator's approval — a confirmation will be sent to your email.
                    </p>
                    <a class="success-btn" href="<?php echo e(url('/')); ?>">Back to Homepage</a>
                </div>

                <p class="auth-bottom" id="signinLink">
                    Already have an account?
                    <a class="auth-link" href="<?php echo e(url('/login')); ?>">Sign in</a>
                    &nbsp;·&nbsp;
                    <a class="auth-link" href="<?php echo e(url('/register')); ?>?type=<?php echo e(session('oauth_account_type', request('type', 'buyer'))); ?>">Sign up manually instead</a>
                </p>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="<?php echo e(asset('js/auth.js')); ?>"></script>
<script src="<?php echo e(asset('js/register.js')); ?>"></script>
<script>
    const googleData = {
        name:   '<?php echo e(session("google_name", "")); ?>',
        email:  '<?php echo e(session("google_email", "")); ?>',
        avatar: '<?php echo e(session("google_avatar", "")); ?>',
    };

    if (googleData.email) {
        document.getElementById('email').value = googleData.email;
    }

    document.getElementById('googleRegForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validateStep(accountStep)) return;

        const form = this;
        const btn  = form.querySelector('.btn-submit');
        btn.disabled    = true;
        btn.textContent = 'Submitting…';

        const fd = new FormData(form);
        if (selfieBlob)   fd.set('selfie_file', selfieBlob, 'selfie.jpg');
        if (idPhotoBlob) {
            const idExt = idPhotoBlob.type === 'application/pdf' ? 'pdf' : 'jpg';
            fd.set('id_file', idPhotoBlob, `id_photo.${idExt}`);
        }
        if (businessPermitBlob) {
            const bpExt = businessPermitBlob.type === 'application/pdf' ? 'pdf' : 'jpg';
            fd.set('business_permit_file', businessPermitBlob, `business_permit.${bpExt}`);
        }

        fetch('<?php echo e(route("register.store")); ?>', {
            method:  'POST',
            body:    fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                form.style.display = 'none';
                document.getElementById('stepIndicator').style.display = 'none';
                document.getElementById('signinLink').style.display    = 'none';
                document.getElementById('successScreen').classList.add('active');
            } else {
                btn.disabled    = false;
                btn.textContent = 'Submit Registration';
                alert(data.errors ? Object.entries(data.errors).map(([k,v]) => `${k}: ${v}`).join('\n') : (data.message ?? 'Something went wrong.'));
            }
        })
        .catch(() => {
            btn.disabled    = false;
            btn.textContent = 'Submit Registration';
            alert('Network error. Please try again.');
        });
    });

</script>
</body>
</html>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/auth/register-google.blade.php ENDPATH**/ ?>