
<div class="modal-overlay" id="adminDocLightbox">
  <div class="modal" style="width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head">
      <div><h3 id="adminDocLightboxTitle">Document Preview</h3></div>
      <button class="modal-close" data-modal-close aria-label="Close"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></button>
    </div>
    <div id="adminDocLightboxBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
if (!window.__adminDocLightboxBound) {
  window.__adminDocLightboxBound = true;
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-doc-trigger]');
    if (!trigger) return;
    const src = trigger.dataset.src, type = trigger.dataset.type, title = trigger.dataset.title;
    const body = document.getElementById('adminDocLightboxBody');
    document.getElementById('adminDocLightboxTitle').textContent = title || (type === 'pdf' ? 'Document Preview' : 'Image Preview');
    body.innerHTML = type === 'image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain" alt="${title || 'Document'}">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    document.getElementById('adminDocLightbox').classList.add('open');
  });
}
</script>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/partials/doc-lightbox.blade.php ENDPATH**/ ?>