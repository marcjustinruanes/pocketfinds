(() => {
  const root = document.querySelector('.pf-assistant');
  if (!root) return;
  const panel = root.querySelector('.pf-assistant-panel');
  const launch = root.querySelector('.pf-assistant-launch');
  const form = root.querySelector('.pf-assistant-form');
  const input = form.querySelector('textarea');
  const feed = root.querySelector('.pf-assistant-feed');
  const status = root.querySelector('.pf-assistant-status');
  const consent = root.querySelector('[data-share-orders]');
  const productConsent = root.querySelector('[data-share-product]');
  const clear = root.querySelector('[data-clear]');
  let busy = false;
  let loaded = false;
  const persistent = !!root.dataset.conversations;
  let engine = persistent ? 'smart' : 'automated';
  let conversation = null;
  let before = null;
  let nextPage = null;
  let editingChat = null;
  const historyPanel = root.querySelector('.pf-assistant-history');
  const older = root.querySelector('[data-older]');
  let aiAvailable = true;
  const prompts = root.querySelector('.pf-assistant-prompts');
  const initialAiPrompts = [...prompts.querySelectorAll('[data-prompt]')].map(button => ({ label: button.textContent, message: button.dataset.prompt }));
  const automatedPrompts = [{ label: 'Products', message: 'Browse products' }, { label: 'Orders', message: 'My order status' }, { label: 'Shipping', message: 'Shipping fees' }, { label: 'Payments', message: 'Payment methods' }, { label: 'Returns', message: 'Returns policy' }];
  function renderPrompts(choices) {
    prompts.replaceChildren();
    choices.slice(0, 5).forEach(choice => {
      if (typeof choice.label !== 'string' || typeof choice.message !== 'string') return;
      const button = document.createElement('button'); button.type = 'button'; button.textContent = choice.label;
      button.dataset.prompt = choice.message;
      if (root.dataset.guest !== 'true' && /\border(s)?\b/i.test(choice.message)) button.dataset.orders = '';
      prompts.append(button);
    });
  }
  function updateEngine() {
    root.querySelectorAll('[name="pfAssistantEngine"]').forEach(radio => radio.checked = radio.value === engine);
    root.querySelector('[data-engine-title]').textContent = engine === 'ai' ? 'AI shopping help' : engine === 'smart' ? 'Smart help' : 'Automated replies';
    const provider = persistent ? 'Google Gemini' : 'Groq AI';
    root.querySelector('[data-engine-note]').textContent = (engine === 'smart' ? `Common questions stay here; unmatched questions go to ${provider}. ` : engine === 'ai' ? `Messages and selected product context go to ${provider}. ` : 'Automated replies stay within PocketFinds. ') + (persistent ? 'Orders stay local. ' + (engine !== 'automated' ? 'Google free-tier messages may improve its products. ' : '') + 'Chats stay saved until deleted. ' : '') + 'Never share private or payment details.';
    renderPrompts(engine === 'ai' ? initialAiPrompts : automatedPrompts);
  }

  function fitKeyboard() {
    const viewport = window.visualViewport;
    if (!viewport) return;
    const inset = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
    if (inset > 100) {
      panel.style.top = 'auto';
      panel.style.bottom = `${inset + 12}px`;
      panel.style.height = `${Math.max(180, viewport.height - 24)}px`;
      panel.style.maxHeight = panel.style.height;
    } else {
      ['top', 'bottom', 'height', 'max-height'].forEach(property => panel.style.removeProperty(property));
    }
  }
  window.visualViewport?.addEventListener('resize', fitKeyboard);

  function message(role, text, result = {}) {
    const bubble = document.createElement('div');
    bubble.className = 'pf-assistant-message';
    bubble.dataset.role = role;
    bubble.textContent = text;
    const links = [ ...(result.products || []).map(p => ({ ...p, label: p.name })), ...(result.links || []) ];
    links.forEach(item => {
      const url = new URL(item.url, location.href);
      if (url.origin !== location.origin || !(url.pathname.startsWith('/buyer/') || url.pathname.startsWith('/product/') || ['/', '/login'].includes(url.pathname))) return;
      const link = document.createElement('a');
      link.href = url.href;
      const label = document.createElement('strong');
      label.textContent = item.label;
      link.append(label);
      if (typeof item.price === 'number') {
        const price = document.createElement('small');
        price.textContent = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(item.price);
        link.append(price);
      }
      bubble.append(link);
    });
    feed.append(bubble);
    if (result.engine && role === 'assistant' && persistent) {
      const label = document.createElement('small'); label.className = 'pf-reply-mode';
      label.textContent = result.engine === 'ai' ? 'AI response' : result.engine === 'fallback' ? 'Support fallback' : 'Automated response';
      bubble.append(label);
    }
    if (!persistent) while (feed.children.length > 24) feed.firstElementChild.remove();
    feed.scrollTop = feed.scrollHeight;
    return bubble;
  }

  function welcome() {
    feed.replaceChildren();
    message('assistant', engine === 'ai' ? 'Hi! What are you looking for today? Tell me your budget, or ask about a product.' : 'Hi! How can I help? Choose a topic below or type your question.');
  }
  function setBusy(value) {
    busy = value;
    root.querySelectorAll('[name="pfAssistantEngine"], [data-prompt], [data-clear], [data-share-orders], [data-share-product], [data-history], .pf-assistant-history button, .pf-assistant-history input, [data-older], .pf-assistant-form button[type="submit"]').forEach(b => b.disabled = value);
    input.disabled = value;
  }
  function close() {
    panel.hidden = true;
    launch.hidden = false;
    launch.setAttribute('aria-expanded', 'false');
    launch.focus();
  }
  function resize() {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 100)}px`;
  }
  async function request(method, data, endpoint = root.dataset.endpoint) {
    const response = await fetch(endpoint, {
      method, credentials: 'same-origin', headers: {
        'Content-Type': 'application/json', Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      }, body: data ? JSON.stringify(data) : undefined,
      signal: AbortSignal.timeout(35000),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 429) throw new Error(result.message || 'Too many messages. Please wait before trying again.');
      if ([401, 419].includes(response.status)) throw new Error('Your session expired. Refresh the page and sign in again.');
      throw new Error(response.status === 422 ? 'Please enter a message of 1 to 1,500 characters.' : result.message || 'Could not connect. Please try again.');
    }
    return result;
  }
  function chatUrl(id = conversation?.id) { return `${root.dataset.conversations}/${encodeURIComponent(id)}`; }
  function syncChat(chat) {
    conversation = chat; engine = chat.engine; updateEngine();
    if (consent) consent.checked = chat.orders_shared;
  }
  async function ensureChat() {
    if (!conversation) syncChat((await request('POST', { engine }, root.dataset.conversations)).conversation);
  }
  async function openChat(id) {
    const state = await request('GET', null, chatUrl(id));
    syncChat(state.conversation); welcome();
    if (productConsent) productConsent.checked = false;
    if (state.history.length) {
      feed.replaceChildren(); state.history.forEach(turn => message(turn.role, turn.content, turn.result));
    }
    before = state.before; older.hidden = !before;
    historyPanel.hidden = true; input.value = ''; resize();
  }
  function iconButton(name, title) {
    const button = document.createElement('button'); button.type = 'button'; button.title = title; button.setAttribute('aria-label', title);
    const template = root.querySelector(`[data-icon-${name}]`);
    if (template) button.append(template.content.cloneNode(true));
    else button.textContent = title;
    return button;
  }
  async function listChats(page = 1) {
    const state = await request('GET', null, `${root.dataset.conversations}?page=${page}`);
    aiAvailable = state.available; nextPage = state.next_page;
    const list = root.querySelector('[data-conversation-list]'); if (page === 1) list.replaceChildren();
    state.conversations.forEach(chat => {
      const row = document.createElement('div'); row.className = 'pf-chat-row'; row.setAttribute('aria-current', String(chat.id === conversation?.id));
      const open = document.createElement('button'); open.type = 'button'; open.textContent = chat.title;
      const date = document.createElement('small'); date.textContent = new Date(chat.updated_at).toLocaleDateString(); open.append(date);
      open.addEventListener('click', () => historyAction(() => openChat(chat.id)));
      const rename = iconButton('rename', 'Rename conversation');
      rename.addEventListener('click', () => { editingChat = chat; root.querySelector('[data-delete-form]').hidden = true; root.querySelector('[data-rename-form]').hidden = false; const title = root.querySelector('#pfChatTitle'); title.value = chat.title; title.focus(); });
      const remove = iconButton('delete', 'Delete conversation');
      remove.addEventListener('click', () => { editingChat = chat; root.querySelector('[data-rename-form]').hidden = true; root.querySelector('[data-delete-form]').hidden = false; root.querySelector('[data-delete-form] button').focus(); });
      row.append(open, rename, remove); list.append(row);
    });
    if (!list.children.length) list.textContent = 'No saved conversations yet.';
    root.querySelector('[data-more-chats]').hidden = !nextPage;
    return state;
  }
  async function historyAction(action) {
    if (busy) return; setBusy(true); status.textContent = '';
    try { await action(); } catch (error) { status.textContent = error.message; }
    finally { setBusy(false); }
  }
  async function send(text) {
    if (busy || !text.trim()) return;
    const draft = text.trim();
    if (draft.length > 1500) { status.textContent = 'Please keep your message under 1,500 characters.'; return; }
    status.textContent = '';
    setBusy(true);
    const sent = message('user', draft);
    const pending = message('assistant', engine === 'ai' ? 'Thinking...' : 'Checking...');
    try {
      if (persistent) await ensureChat();
      const result = await request('POST', { engine, message: draft, include_orders: consent?.checked || false, product_id: productConsent?.checked ? productConsent.value : null }, persistent ? `${chatUrl()}/messages` : root.dataset.endpoint);
      if (persistent) syncChat(result.conversation);
      pending.remove();
      message('assistant', result.reply, result);
      if (result.engine === 'automated' && result.suggestions) renderPrompts(result.suggestions);
      input.value = '';
    } catch (error) {
      pending.remove();
      sent.remove();
      input.value = draft;
      status.textContent = error.name === 'TimeoutError' ? 'The assistant took too long. Please try again.' : error.message;
    } finally {
      setBusy(false);
      resize();
      if (!panel.hidden) input.focus();
    }
  }
  launch.addEventListener('click', async () => {
    document.getElementById('messagesModal')?.classList.remove('open');
    panel.hidden = false;
    fitKeyboard();
    launch.hidden = true;
    launch.setAttribute('aria-expanded', 'true');
    input.focus();
    if (!loaded) {
      setBusy(true);
      try {
        if (persistent) {
          const state = await listChats();
          if (state.conversations.length) await openChat(state.conversations[0].id);
          loaded = true; return;
        }
        const state = await request('GET');
        engine = state.engine === 'ai' ? 'ai' : 'automated'; aiAvailable = state.available;
        updateEngine(); welcome();
        if (consent) consent.checked = state.orders_shared;
        if (state.history.length) {
          feed.replaceChildren();
          state.history.forEach(turn => message(turn.role, turn.content));
        }
        if (!state.available && engine === 'ai') status.textContent = 'AI is not connected. Choose Automated for quick help without AI.';
        loaded = true;
      } catch { status.textContent = 'Could not load your previous chat. Please refresh and try again.'; }
      finally { setBusy(false); if (!panel.hidden) input.focus(); }
    }
  });
  root.querySelector('[data-close]').addEventListener('click', close);
  panel.addEventListener('keydown', e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });
  form.addEventListener('submit', e => { e.preventDefault(); send(input.value); });
  input.addEventListener('input', resize);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); }
  });
  prompts.addEventListener('click', event => {
    const button = event.target.closest('[data-prompt]'); if (!button || busy) return;
    // Order sharing must be explicitly enabled by the buyer before a request.
    if (button.hasAttribute('data-orders') && !consent.checked) {
      input.value = button.dataset.prompt;
      status.textContent = 'Enable recent order statuses below, or open Orders to view them privately.';
      input.focus(); resize(); return;
    }
    send(button.dataset.prompt);
  });
  root.querySelectorAll('[name="pfAssistantEngine"]').forEach(radio => radio.addEventListener('change', async () => {
    if (busy || radio.value === engine) return;
    const selected = radio.value; setBusy(true); status.textContent = '';
    try {
      if (persistent) {
        await ensureChat(); syncChat((await request('PATCH', { engine: selected, orders_shared: false }, chatUrl())).conversation);
        if (productConsent) productConsent.checked = false;
        return;
      }
      await request('DELETE'); engine = selected;
      if (consent) consent.checked = false;
      if (productConsent) productConsent.checked = false;
      updateEngine(); welcome();
      if (engine === 'ai' && !aiAvailable) status.textContent = 'AI is not connected. Automated replies are still available.';
    } catch { updateEngine(); status.textContent = 'Could not switch modes. Please try again.'; }
    finally { setBusy(false); input.focus(); }
  }));
  clear.addEventListener('click', async () => {
    if (busy) return;
    setBusy(true); status.textContent = '';
    try {
      if (persistent) {
        conversation = null; before = null; older.hidden = true; historyPanel.hidden = true;
        consent.checked = false; if (productConsent) productConsent.checked = false;
      } else await request('DELETE');
      welcome(); input.value = '';
    }
    catch { status.textContent = 'Could not clear this chat. Please try again.'; }
    finally { setBusy(false); resize(); input.focus(); }
  });
  consent?.addEventListener('change', async () => {
    const selected = consent.checked;
    setBusy(true); status.textContent = '';
    try {
      if (persistent) { await ensureChat(); syncChat((await request('PATCH', { orders_shared: selected }, chatUrl())).conversation); }
      else { await request('DELETE'); welcome(); }
    }
    catch {
      consent.checked = !selected;
      status.textContent = 'Could not update order sharing. Please try again.';
    } finally { setBusy(false); }
  });
  if (persistent) {
    root.querySelector('[data-history]').addEventListener('click', () => historyAction(async () => {
      await listChats(); historyPanel.hidden = false;
      root.querySelector('[data-rename-form]').hidden = true; root.querySelector('[data-delete-form]').hidden = true;
      root.querySelector('[data-history-close]').focus();
    }));
    root.querySelector('[data-history-close]').addEventListener('click', () => { historyPanel.hidden = true; input.focus(); });
    root.querySelector('[data-more-chats]').addEventListener('click', () => historyAction(() => listChats(nextPage)));
    root.querySelector('[data-rename-cancel]').addEventListener('click', () => root.querySelector('[data-rename-form]').hidden = true);
    root.querySelector('[data-delete-cancel]').addEventListener('click', () => root.querySelector('[data-delete-form]').hidden = true);
    root.querySelector('[data-rename-form]').addEventListener('submit', event => {
      event.preventDefault(); historyAction(async () => {
        const state = await request('PATCH', { title: root.querySelector('#pfChatTitle').value }, chatUrl(editingChat.id));
        if (conversation?.id === editingChat.id) syncChat(state.conversation);
        root.querySelector('[data-rename-form]').hidden = true; await listChats();
      });
    });
    root.querySelector('[data-delete-form]').addEventListener('submit', event => {
      event.preventDefault(); historyAction(async () => {
        await request('DELETE', null, chatUrl(editingChat.id));
        if (conversation?.id === editingChat.id) { conversation = null; before = null; older.hidden = true; consent.checked = false; welcome(); }
        root.querySelector('[data-delete-form]').hidden = true; await listChats();
      });
    });
    older.addEventListener('click', () => historyAction(async () => {
      const state = await request('GET', null, `${chatUrl()}?before=${before}`);
      const previousFirst = feed.firstElementChild;
      state.history.forEach(turn => { const bubble = message(turn.role, turn.content, turn.result); feed.insertBefore(bubble, previousFirst); });
      before = state.before; older.hidden = !before; previousFirst?.scrollIntoView({ block: 'nearest' });
    }));
  }
  const support = root.querySelector('.pf-assistant-support');
  const supportStatus = root.querySelector('[data-support-status]');
  root.querySelector('[data-support-open]').addEventListener('click', () => {
    support.hidden = false; form.hidden = true;
    root.querySelector('#pfSupportEmail').focus();
  });
  root.querySelector('[data-support-close]').addEventListener('click', () => { support.hidden = true; form.hidden = false; input.focus(); });
  support.addEventListener('submit', async event => {
    event.preventDefault(); const button = support.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true; supportStatus.textContent = 'Sending...';
    try {
      const response = await fetch(root.dataset.support, { method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ email: root.querySelector('#pfSupportEmail').value, message: root.querySelector('#pfSupportMessage').value, context: 'Shopping assistant escalation' }), signal: AbortSignal.timeout(35000) });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) throw new Error(data.message || 'Could not send. Please try later.');
      support.reset(); supportStatus.textContent = 'Sent to PocketFinds support. Please watch your email for a reply.';
    } catch (error) { supportStatus.textContent = error.message; }
    finally { button.disabled = false; }
  });
  updateEngine(); welcome();
})();
