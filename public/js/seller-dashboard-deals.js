document.querySelectorAll('[data-dashboard-deals]').forEach(gallery => {
  const panels = Array.from(gallery.querySelectorAll('[data-dashboard-deal]'));
  const position = gallery.querySelector('[data-dashboard-deal-position]');
  let current = 0;
  const show = offset => {
    current = (current + offset + panels.length) % panels.length;
    panels.forEach((panel, index) => { panel.hidden = index !== current; });
    if (position) position.textContent = `${current + 1} / ${panels.length}`;
  };
  gallery.querySelector('[data-dashboard-deal-prev]')?.addEventListener('click', () => show(-1));
  gallery.querySelector('[data-dashboard-deal-next]')?.addEventListener('click', () => show(1));
});
