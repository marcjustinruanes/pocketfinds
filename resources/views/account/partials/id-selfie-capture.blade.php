                        <div class="id-selfie-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px" id="idSelfieGrid">

                            {{-- ID Photo --}}
                            <div>
                                <label class="auth-label">ID Photo <span class="auth-required">*</span></label>
                                <div id="idPhotoBox" class="document-upload" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:140px;display:flex;flex-direction:column;justify-content:center">
                                    {{-- Idle: upload or camera --}}
                                    <div id="idPhotoIdle" style="padding:12px;text-align:center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#aaa;margin-bottom:6px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L6 7h12l-2-4z"/><circle cx="12" cy="14" r="3"/></svg>
                                        <p style="margin:0 0 8px;font-size:11px;color:#888">Upload your ID document</p>
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                                            <label for="id_file" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:1px solid var(--pink,#bc387f);color:var(--pink,#bc387f);background:#fff;border-radius:8px;font-size:11px;cursor:pointer">
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
                                        <button type="button" onclick="snapIdPhoto()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--pink,#bc387f);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </div>
                                    {{-- Preview --}}
                                    <div id="idPhotoPreview" style="display:none;position:relative">
                                        <img id="idPhotoImg" style="width:100%;max-height:140px;object-fit:cover;display:block" alt="ID Photo">
                                        <button type="button" class="enlarge-btn" onclick="openAccountCapturePreview('idPhotoImg')">
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
                                <div id="selfieBox" style="border:2px dashed var(--auth-border,#ddd);border-radius:10px;overflow:hidden;background:#f9f9f9;min-height:140px;display:flex;flex-direction:column;justify-content:center">
                                    <div id="selfieCamera" style="display:none;position:relative">
                                        <video id="selfieVideo" autoplay playsinline style="width:100%;max-height:140px;object-fit:cover;display:block"></video>
                                        <button type="button" onclick="snapSelfie()" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:var(--pink,#bc387f);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </div>
                                    <div id="selfiePreview" style="display:none;position:relative">
                                        <img id="selfieImg" style="width:100%;max-height:140px;object-fit:cover;display:block" alt="Selfie">
                                        <button type="button" class="enlarge-btn" onclick="openAccountCapturePreview('selfieImg')">
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
                                        <button type="button" id="openCameraBtn" onclick="startCamera()" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid var(--pink,#bc387f);color:var(--pink,#bc387f);background:#fff;border-radius:8px;font-size:12px;cursor:pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                            Open Camera
                                        </button>
                                    </div>
                                </div>
                                <input type="file" name="selfie_file" id="accountSelfieFile" accept="image/jpeg,image/png" hidden><canvas id="selfieCanvas" style="display:none"></canvas>
                                <span id="selfieError" style="display:none;color:red;font-size:11px">Please take a selfie.</span>
                            </div>
                        </div>

