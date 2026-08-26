<?php $__env->startSection('title', 'My Account'); ?>
<?php $__env->startSection('page-title', 'My Account'); ?>
<?php $__env->startSection('page-sub', 'Manage your seller profile and settings'); ?>

<?php $__env->startSection('content'); ?>
<?php $u = auth()->user(); ?>

<div class="dash-grid">
  <div class="stack">

    
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Update your personal details</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            <?php echo e(strtoupper(substr($u->given_names, 0, 1))); ?>

          </div>
          <div>
            <div style="font-size:16px;font-weight:700"><?php echo e($u->given_names); ?> <?php echo e($u->last_name); ?></div>
            <div style="font-size:12px;color:var(--muted)"><?php echo e($u->email); ?></div>
            <span class="stamp stamp-active" style="margin-top:4px"><?php echo e(ucfirst($u->account_type ?: 'Account')); ?></span>
          </div>
        </div>
        <?php if(session('profile_success')): ?>
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e(session('profile_success')); ?></div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('seller.account.profile')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-grid-2">
            <div class="form-row"><label>Given Names</label><input type="text" name="given_names" value="<?php echo e(old('given_names', $u->given_names)); ?>" required></div>
            <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="<?php echo e(old('last_name', $u->last_name)); ?>" required></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>Middle Name</label><input type="text" name="middle_name" value="<?php echo e(old('middle_name', $u->middle_name)); ?>"></div>
            <div class="form-row"><label>Phone Number</label><input type="text" name="contact_no" value="<?php echo e(old('contact_no', $u->contact_no)); ?>" placeholder="09XXXXXXXXX" maxlength="11"></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Sex</label>
              <select name="sex">
                <option value="male" <?php echo e($u->sex === 'male' ? 'selected' : ''); ?>>Male</option>
                <option value="female" <?php echo e($u->sex === 'female' ? 'selected' : ''); ?>>Female</option>
              </select>
            </div>
            <div class="form-row"><label>Birthday</label><input type="date" name="birthday" value="<?php echo e(old('birthday', $u->birthday?->format('Y-m-d'))); ?>"></div>
          </div>
          <div class="form-row"><label>Email Address</label><input type="email" value="<?php echo e($u->email); ?>" disabled style="background:var(--paper);color:var(--muted);cursor:not-allowed"></div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><div><h2>Address Information</h2><p>Your delivery and business address</p></div></div>
      <div class="card-pad">
        <?php if(session('address_success')): ?>
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e(session('address_success')); ?></div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('seller.account.address')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-row">
            <label>Province</label>
            <select name="province" id="acc-province" required>
              <option value="" disabled>Loading provinces…</option>
            </select>
          </div>
          <div class="form-row">
            <label>Municipality / City</label>
            <select name="municipality" id="acc-municipality" required disabled>
              <option value="" disabled>Select province first</option>
            </select>
          </div>
          <div class="form-row">
            <label>Barangay</label>
            <select name="barangay" id="acc-barangay" required disabled>
              <option value="" disabled>Select municipality first</option>
            </select>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>House No. / Unit</label><input type="text" name="house_no" value="<?php echo e(old('house_no', $u->house_no)); ?>" placeholder="e.g. 123"></div>
            <div class="form-row"><label>Street</label><input type="text" name="street" value="<?php echo e(old('street', $u->street)); ?>" placeholder="e.g. Rizal St."></div>
          </div>
          <button type="submit" class="btn btn-primary">Update Address</button>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><div><h2>Shop Information</h2><p>Your store details visible to buyers</p></div></div>
      <div class="card-pad">
        <?php if(session('shop_success')): ?>
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e(session('shop_success')); ?></div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('seller.account.shop')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-grid-2">
            <div class="form-row"><label>Business Name</label><input type="text" name="business_name" value="<?php echo e(old('business_name', $u->business_name)); ?>" placeholder="Your Shop Name"></div>
            <div class="form-row"><label>Username</label><input type="text" name="username" value="<?php echo e(old('username', $u->username)); ?>" placeholder="@yourshop"></div>
          </div>
          <div class="form-row">
            <label>Shop Category</label>
            <select name="category_id">
              <option value="">— Select category —</option>
              <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat->id); ?>" <?php echo e(old('category_id', $u->category_id) == $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Update Shop</button>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><div><h2>Documents & Verification</h2><p>Your ID and business permit on file</p></div></div>
      <div class="card-pad">

        
        <?php if($pendingRequest): ?>
          <div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
            <span style="flex:none;margin-top:1px"><?php echo $__env->make('seller.partials.icon',['name'=>'clock','size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
            <div>
              <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
              <div>Your document update request is awaiting admin review. You cannot submit a new request until this one is resolved.</div>
              <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px">
                <?php if($pendingRequest->id_file): ?>
                  <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                    data-src="<?php echo e(asset('storage/'.$pendingRequest->id_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($pendingRequest->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>">
                    <?php echo $__env->make('seller.partials.icon',['name'=>'file','size'=>13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Submitted ID
                  </button>
                <?php endif; ?>
                <?php if($pendingRequest->business_permit_file): ?>
                  <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                    data-src="<?php echo e(asset('storage/'.$pendingRequest->business_permit_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($pendingRequest->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>">
                    <?php echo $__env->make('seller.partials.icon',['name'=>'file','size'=>13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Submitted Permit
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php elseif($lastRequest && $lastRequest->status === 'rejected'): ?>
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
            <span style="flex:none;margin-top:1px"><?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
            <div>
              <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
              <div><?php echo e($lastRequest->note ?? 'Your previous document update request was rejected. You may submit a new one.'); ?></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if(session('docs_success')): ?>
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e(session('docs_success')); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('seller.account.documents')); ?>" enctype="multipart/form-data" <?php echo e($pendingRequest ? 'style=opacity:.5;pointer-events:none' : ''); ?>>
          <?php echo csrf_field(); ?>

          
          <div class="form-row">
            <label>ID Type</label>
            <select name="id_type_id">
              <option value="">— Select ID type —</option>
              <?php $__currentLoopData = $idTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($idType->id); ?>" <?php echo e(old('id_type_id', $u->id_type_id) == $idType->id ? 'selected' : ''); ?>><?php echo e($idType->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <?php if($u->id_file): ?>
            <?php $ext = strtolower(pathinfo($u->id_file, PATHINFO_EXTENSION)); $isImg = in_array($ext, ['jpg','jpeg','png']); ?>
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current ID on File</div>
              <button type="button" class="doc-preview-btn" data-src="<?php echo e(asset('storage/'.$u->id_file)); ?>" data-type="<?php echo e($isImg ? 'image' : 'pdf'); ?>"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                <?php echo $__env->make('seller.partials.icon',['name'=>'file','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View Current ID
              </button>
            </div>
          <?php endif; ?>
          <div class="form-row"><label>Replace ID File <span style="color:var(--muted);font-weight:400">(optional)</span></label><input type="file" name="id_file" accept="image/*,.pdf" style="padding:6px"></div>

          
          <?php if($u->business_permit_file): ?>
            <?php $bext = strtolower(pathinfo($u->business_permit_file, PATHINFO_EXTENSION)); $bIsImg = in_array($bext, ['jpg','jpeg','png']); ?>
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current Business Permit</div>
              <button type="button" class="doc-preview-btn" data-src="<?php echo e(asset('storage/'.$u->business_permit_file)); ?>" data-type="<?php echo e($bIsImg ? 'image' : 'pdf'); ?>"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                <?php echo $__env->make('seller.partials.icon',['name'=>'file','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View Current Permit
              </button>
            </div>
          <?php endif; ?>
          <div class="form-row"><label><?php echo e($u->business_permit_file ? 'Replace' : 'Upload'); ?> Business Permit <span style="color:var(--muted);font-weight:400">(optional)</span></label><input type="file" name="business_permit_file" accept="image/*,.pdf" style="padding:6px"></div>

          <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
            <?php echo $__env->make('seller.partials.icon',['name'=>'send','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Submit for Review
          </button>
        </form>
      </div>
    </div>

  </div>

  <div class="stack">

    
    <div class="card">
      <div class="card-head"><div><h2>Change Password</h2></div></div>
      <div class="card-pad">
        <?php if(session('password_success')): ?>
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e(session('password_success')); ?></div>
        <?php endif; ?>
        <?php $__errorArgs = ['current_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <form method="POST" action="<?php echo e(route('seller.account.password')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
          <button type="submit" class="btn btn-outline">Update Password</button>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column">
        <?php $__currentLoopData = [
          ['Account Type',   filled($u->account_type) ? ucfirst($u->account_type) : 'Not provided'],
          ['Status',         filled($u->status) ? ucfirst($u->status) : 'Not provided'],
          ['Auth Method',    filled($u->auth_method) ? ucfirst($u->auth_method) : 'Not provided'],
          ['Member Since',   $u->created_at?->format('M d, Y') ?? 'Not provided'],
          ['Given Names',    $u->given_names ?: 'Not provided'],
          ['Last Name',      $u->last_name ?: 'Not provided'],
          ['Middle Name',    $u->middle_name ?: 'Not provided'],
          ['Sex',            $u->sex ? ucfirst($u->sex) : 'Not provided'],
          ['Birthday',       $u->birthday?->format('M d, Y') ?? 'Not provided'],
          ['Age',            $u->age ?: 'Not provided'],
          ['Email',          $u->email ?: 'Not provided'],
          ['Contact No.',    $u->contact_no ?: 'Not provided'],
          ['Province',       '<span id="ov-province" data-code="'.e($u->province).'">'.e($u->province ?: 'Not provided').'</span>'],
          ['Municipality',   '<span id="ov-municipality" data-code="'.e($u->municipality).'">'.e($u->municipality ?: 'Not provided').'</span>'],
          ['Barangay',       '<span id="ov-barangay" data-code="'.e($u->barangay).'">'.e($u->barangay ?: 'Not provided').'</span>'],
          ['House No.',      $u->house_no ?: 'Not provided'],
          ['Street',         $u->street ?: 'Not provided'],
          ['Business Name',  $u->business_name ?: 'Not provided'],
          ['Username',       $u->username ?: 'Not provided'],
          ['Shop Category',  $u->category_id ? ($categories->firstWhere('id', $u->category_id)->name ?? 'Not provided') : 'Not provided'],
          ['ID Type',        $u->id_type_id ? ($idTypes->firstWhere('id', $u->id_type_id)->name ?? 'Not provided') : 'Not provided'],
          ['ID File',        $u->id_file ? '<button type="button" class="doc-preview-btn" data-src="'.asset('storage/'.$u->id_file).'" data-type="'.(in_array(strtolower(pathinfo($u->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png'])?'image':'pdf').'" style="background:none;border:none;padding:0;color:var(--pink);font-weight:600;cursor:pointer;font-size:13px">View File</button>' : 'Not provided'],
          ['Business Permit',$u->business_permit_file ? '<button type="button" class="doc-preview-btn" data-src="'.asset('storage/'.$u->business_permit_file).'" data-type="'.(in_array(strtolower(pathinfo($u->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png'])?'image':'pdf').'" style="background:none;border:none;padding:0;color:var(--pink);font-weight:600;cursor:pointer;font-size:13px">View File</button>' : 'Not provided'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $val]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted)"><?php echo e($label); ?></span>
          <span style="font-weight:600;text-align:right;max-width:60%;word-break:break-word"><?php echo $val; ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card" style="border-color:var(--danger-line)">
      <div class="card-head" style="border-color:var(--danger-line)"><div><h2 style="color:var(--danger)">Danger Zone</h2><p>Irreversible actions</p></div></div>
      <div class="card-pad">
        <button class="btn btn-danger btn-block" data-logout><?php echo $__env->make('seller.partials.icon', ['name' => 'logout', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Sign Out</button>
      </div>
    </div>

  </div>
</div>


<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.7);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(27,22,32,.3);overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span id="docModalTitle" style="font-weight:700;font-size:14px">Document Preview</span>
      <button id="docModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;display:grid;place-items:center">✕</button>
    </div>
    <div id="docModalBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
const PSGC = 'https://psgc.gitlab.io/api';
const savedProvince     = '<?php echo e($u->province); ?>';
const savedMunicipality = '<?php echo e($u->municipality); ?>';
const savedBarangay     = '<?php echo e($u->barangay); ?>';

async function fetchJSON(url) {
  const r = await fetch(url);
  return r.json();
}

function populate(sel, items, saved, placeholder) {
  sel.innerHTML = `<option value="" disabled>${placeholder}</option>`;
  [...items].sort((a,b) => a.name.localeCompare(b.name)).forEach(item => {
    const o = document.createElement('option');
    o.value = item.code;
    o.textContent = item.name;
    if (item.code === saved) o.selected = true;
    sel.appendChild(o);
  });
  sel.disabled = false;
  // update overview label
  const selected = items.find(i => i.code === saved);
  return selected ? selected.name : null;
}

async function initAddress() {
  const provSel = document.getElementById('acc-province');
  const munSel  = document.getElementById('acc-municipality');
  const barSel  = document.getElementById('acc-barangay');

  // Load provinces
  const provinces = await fetchJSON(`${PSGC}/provinces/`);
  const provName = populate(provSel, provinces, savedProvince, 'Select province');
  if (provName) document.getElementById('ov-province').textContent = provName;

  if (!savedProvince) return;

  // Load municipalities
  munSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  const muns = await fetchJSON(`${PSGC}/provinces/${savedProvince}/cities-municipalities/`);
  const munName = populate(munSel, muns, savedMunicipality, 'Select municipality');
  if (munName) document.getElementById('ov-municipality').textContent = munName;

  if (!savedMunicipality) return;

  // Load barangays
  barSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  const bars = await fetchJSON(`${PSGC}/cities-municipalities/${savedMunicipality}/barangays/`);
  const barName = populate(barSel, bars, savedBarangay, 'Select barangay');
  if (barName) document.getElementById('ov-barangay').textContent = barName;
}

// Cascade on change
document.getElementById('acc-province').addEventListener('change', async function () {
  const munSel = document.getElementById('acc-municipality');
  const barSel = document.getElementById('acc-barangay');
  munSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  munSel.disabled = true;
  barSel.innerHTML = '<option value="" disabled selected>Select municipality first</option>';
  barSel.disabled = true;
  const muns = await fetchJSON(`${PSGC}/provinces/${this.value}/cities-municipalities/`);
  populate(munSel, muns, '', 'Select municipality');
});

document.getElementById('acc-municipality').addEventListener('change', async function () {
  const barSel = document.getElementById('acc-barangay');
  barSel.innerHTML = '<option value="" disabled selected>Loading…</option>';
  barSel.disabled = true;
  const bars = await fetchJSON(`${PSGC}/cities-municipalities/${this.value}/barangays/`);
  populate(barSel, bars, '', 'Select barangay');
});

initAddress();

// Document lightbox
const docModal      = document.getElementById('docModal');
const docModalBody  = document.getElementById('docModalBody');
const docModalTitle = document.getElementById('docModalTitle');

document.getElementById('docModalClose').addEventListener('click', closeDocModal);
docModal.addEventListener('click', e => { if (e.target === docModal) closeDocModal(); });

function closeDocModal() {
  docModal.style.display = 'none';
  docModalBody.innerHTML = '';
}

document.querySelectorAll('.doc-preview-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const src  = btn.dataset.src;
    const type = btn.dataset.type;
    docModalTitle.textContent = type === 'pdf' ? 'Document Preview' : 'Image Preview';
    docModalBody.innerHTML = type === 'image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    docModal.style.display = 'flex';
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\account.blade.php ENDPATH**/ ?>