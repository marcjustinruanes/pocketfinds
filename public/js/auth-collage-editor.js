document.querySelectorAll('[data-auth-collage-editor]').forEach((form) => {
    const preview = form.querySelector('[data-collage-preview]');
    const upload = form.querySelector('[data-collage-upload]');
    const error = form.querySelector('[data-collage-upload-error]');
    const axes = Object.fromEntries([...form.querySelectorAll('[data-collage-axis]')].map(input => [input.dataset.collageAxis, input]));
    let previewUrl;
    const update = () => {
        const {x, y, zoom} = Object.fromEntries(Object.entries(axes).map(([axis, input]) => [axis, Number(input.value)]));
        preview.style.objectPosition = `${x}% ${y}%`;
        preview.style.transformOrigin = `${x}% ${y}%`;
        preview.style.transform = `scale(${zoom / 100})`;
        Object.entries(axes).forEach(([axis, input]) => {
            form.querySelector(`[data-collage-output="${axis}"]`).textContent = `${input.value}%`;
        });
    };
    Object.values(axes).forEach(input => input.addEventListener('input', update));
    form.querySelector('[data-collage-reset]').addEventListener('click', () => {
        axes.x.value = 50;
        axes.y.value = 50;
        axes.zoom.value = 100;
        update();
    });
    upload.addEventListener('change', () => {
        error.hidden = true;
        const file = upload.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            upload.value = '';
            error.textContent = 'Choose a JPG, PNG or WebP image of at most 5 MB.';
            error.hidden = false;
            return;
        }
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        update();
    });
    update();
});
