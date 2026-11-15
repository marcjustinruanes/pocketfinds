document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('team-member-modal');
  if (!dialog) return;
  dialog.showModal();
  dialog.addEventListener('cancel', event => { event.preventDefault(); location.assign(dialog.dataset.returnUrl); });
  const frame = dialog.querySelector('.personnel-edit-frame');
  window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== frame?.contentWindow || event.data?.type !== 'personnel-updated') return;
    location.reload();
  });
});
