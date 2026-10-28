document.querySelectorAll('[data-hero-crop]').forEach(editor => {
  const frame = editor.querySelector('[data-crop-frame]');
  const preview = editor.querySelector('[data-crop-preview]');
  const form = editor.closest('form');
  const size = editor.querySelector('[data-crop-size]');
  let image;
  let originalSrc;
  let previewUrl;
  const resize = () => {
    if (!image || !frame.clientWidth) return;
    const width = size.value === 'mobile' ? 390 : 1440;
    const hero = preview.contentDocument.querySelector('.pf-hero-banner');
    preview.style.width = `${width}px`;
    hero.style.height = size.value === 'intro' ? '824px' : '';
    const height = Math.ceil(hero.getBoundingClientRect().height);
    const scale = Math.min(1, frame.clientWidth / width);
    preview.style.height = `${height}px`;
    preview.style.transform = `scale(${scale})`;
    frame.style.height = `${height * scale}px`;
    editor.querySelector('[data-crop-dimensions]').textContent = `${width} × ${height} px banner · preview scaled to fit`;
  };
  const inputs = Object.fromEntries([...editor.querySelectorAll('[data-crop-axis]')].map(input => [input.dataset.cropAxis, input]));
  const update = () => {
    Object.entries(inputs).forEach(([axis, input]) => {
      editor.querySelector(`[data-crop-output="${axis}"]`).value = `${input.value}%`;
    });
    if (!image) return;
    const { x, y, zoom } = Object.fromEntries(Object.entries(inputs).map(([axis, input]) => [axis, Number(input.value)]));
    image.style.objectPosition = `${x}% ${y}%`;
    image.style.transformOrigin = `${x}% ${y}%`;
    image.style.transform = `scale(${zoom / 100})`;
    resize();
  };
  const updateText = () => {
    if (!image) return;
    const doc = preview.contentDocument;
    const headline = doc.querySelector('[data-crop-headline]');
    headline.replaceChildren();
    (form.elements.hero_tagline.value || 'Find It. Love It. Pocket It.').split(/(Pocket\s+It\.?)/i).forEach(part => {
      if (/^Pocket\s+It\.?$/i.test(part)) {
        const span = doc.createElement('span');
        span.className = 'pf-hero-pocket-it';
        span.textContent = part;
        headline.append(span);
      } else headline.append(doc.createTextNode(part));
    });
    headline.style.whiteSpace = 'pre-line';
    doc.querySelector('[data-crop-subtitle]').textContent = form.elements.hero_subtitle.value || 'Discover everyday finds that fit your needs and budget. Simple, convenient shopping—all in one place.';
    doc.querySelector('[data-crop-cta]').textContent = form.elements.hero_cta_text.value || 'Browse Products';
    doc.querySelector('.pf-hero-banner').className = `pf-hero-banner pf-overlay-${form.elements.hero_overlay.value}`;
    resize();
  };
  preview.addEventListener('load', () => {
    image = preview.contentDocument.querySelector('[data-crop-image]');
    originalSrc = image.src;
    image.src = previewUrl || originalSrc;
    updateText();
    update();
    preview.contentDocument.fonts?.ready.then(resize);
  });
  preview.srcdoc = editor.querySelector('[data-crop-template]').innerHTML;
  ['hero_tagline', 'hero_subtitle', 'hero_cta_text', 'hero_overlay'].forEach(name => form.elements[name].addEventListener('input', updateText));
  Object.values(inputs).forEach(input => input.addEventListener('input', update));
  editor.querySelector('[data-crop-reset]').addEventListener('click', () => {
    inputs.x.value = inputs.y.value = 50;
    inputs.zoom.value = 100;
    update();
  });
  size.addEventListener('change', resize);
  new ResizeObserver(resize).observe(frame);
  const upload = editor.closest('form').querySelector(`input[type="file"][name="${editor.dataset.upload}"]`);
  upload?.addEventListener('change', () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = upload.files[0] ? URL.createObjectURL(upload.files[0]) : undefined;
    if (image) image.src = previewUrl || originalSrc;
  });
  update();
});
