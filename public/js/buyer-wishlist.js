document.querySelectorAll('[data-wishlist-product]').forEach(button => {
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    button.disabled = true;
    const saved = button.getAttribute('aria-pressed') !== 'true';
    const status = document.getElementById('wishlistStatus');
    try {
      const response = await fetch(button.dataset.wishlistUrl, {
        method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ saved })
      });
      if (response.status === 401 || response.status === 419) { location.assign('/login'); return; }
      if (!response.ok) throw new Error('Could not update wishlist. Please retry.');
      const result = await response.json();
      button.setAttribute('aria-pressed', String(result.saved));
      button.textContent = result.saved ? '♥ Saved to wishlist' : '♡ Add to wishlist';
      if (status) status.textContent = result.saved ? 'Saved to wishlist.' : 'Removed from wishlist.';
      if (!result.saved && button.dataset.removeEntry === 'true') button.closest('[data-wishlist-entry]').remove();
    } catch (error) {
      if (status) status.textContent = error.message;
    } finally { button.disabled = false; }
  });
});
