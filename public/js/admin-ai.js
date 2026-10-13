(() => {
  const root = document.querySelector('.admin-ai');
  if (!root) return;
  const form = root.querySelector('#ai-task-form');
  const status = root.querySelector('#aiStatus');
  const result = root.querySelector('#aiResult');
  const draft = root.querySelector('#aiDraft');
  const productCopy = root.querySelector('#aiProductCopy');
  const empty = root.querySelector('#aiEmpty');
  const message = root.querySelector('#aiMessage');
  const tabs = [...root.querySelectorAll('[data-mode]')];
  const photo = root.querySelector('#aiProductPreview');
  let mode = 'dashboard';
  let busy = false;
  const questions = root.querySelector('.ai-question-suggestions');
  const aiQuestions = [...questions.querySelectorAll('[data-question]')].map(button => ({ label: button.textContent, message: button.dataset.question }));
  const normalQuestions = [{ label: 'Sales', message: 'Sales summary' }, { label: 'Low stock', message: 'Low stock products' }, { label: 'Best sellers', message: 'Best-selling products' }, { label: 'Pending reviews', message: 'Pending approvals' }, { label: 'Complaints', message: 'Open complaints' }, { label: 'Reports', message: 'Download reports' }];
  const engine = () => mode === 'chat' ? root.querySelector('[name="adminChatEngine"]:checked').value : 'ai';
  function renderQuestions(choices) {
    questions.replaceChildren(); choices.slice(0, 6).forEach(choice => {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = choice.label; button.dataset.question = choice.message; questions.append(button);
    });
  }
  function updateEngine() {
    const automated = engine() === 'automated';
    root.querySelector('.ai-consent').hidden = automated;
    root.querySelector('#aiConsent').required = !automated;
    root.querySelector('#aiEngineNote').textContent = automated ? 'Rule-based replies stay within PocketFinds. No AI provider is contacted.' : "Free-tier quotas apply. Don't include passwords, payment details or private documents.";
    renderQuestions(automated ? normalQuestions : aiQuestions);
  }
  const labels = { dashboard: 'Generate summary', analytics: 'Analyze sales', inventory: 'Review inventory', anomalies: 'Explain review alerts', reports: 'Summarize complaints', seller: 'Review application', product: 'Screen product', product_copy: 'Draft product copy', draft: 'Create draft', chat: 'Ask question' };
  function previewPhoto() {
    photo.hidden = true;
    const selected = root.querySelector('#aiProduct').value;
    if (['product', 'product_copy'].includes(mode) && selected) photo.src = root.dataset.photo.replace('__PRODUCT__', encodeURIComponent(selected));
    else photo.removeAttribute('src');
  }
  photo.addEventListener('load', () => { photo.hidden = false; });
  photo.addEventListener('error', () => { photo.hidden = true; });
  root.querySelector('#aiProduct').addEventListener('change', previewPhoto);
  root.querySelector('#aiDays').addEventListener('change', event => {
    const link = root.querySelector('#aiExport'); link.href = `${link.dataset.url}?days=${encodeURIComponent(event.target.value)}`;
  });
  function setMode(value, focus = false) {
    if (busy) return;
    mode = labels[value] ? value : 'dashboard';
    form.setAttribute('aria-labelledby', `ai-tab-${mode}`);
    tabs.forEach(tab => { tab.setAttribute('aria-selected', String(tab.dataset.mode === mode)); tab.tabIndex = tab.dataset.mode === mode ? 0 : -1; });
    root.querySelectorAll('[data-for]').forEach(el => el.hidden = !el.dataset.for.split(' ').includes(mode));
    root.querySelector('#ai-task-title').textContent = tabs.find(tab => tab.dataset.mode === mode).textContent;
    root.querySelector('#aiRun span').textContent = labels[mode];
    root.querySelector('#aiProduct').required = ['product', 'product_copy'].includes(mode);
    root.querySelector('#aiSeller').required = mode === 'seller';
    message.required = ['chat', 'draft'].includes(mode);
    root.querySelector('#aiMessageLabel').textContent = mode === 'chat' ? 'Question' : mode === 'draft' ? 'Announcement brief' : 'Focus (optional)';
    message.placeholder = mode === 'chat' ? 'Ask about sales, queues or low stock...' : mode === 'draft' ? 'Topic, confirmed dates and important details...' : 'What should the review focus on?';
    result.hidden = true; draft.hidden = true; productCopy.hidden = true; empty.hidden = false; status.textContent = '';
    previewPhoto();
    updateEngine();
    if (focus) tabs.find(tab => tab.dataset.mode === mode).focus();
  }
  function setBusy(value) {
    busy = value;
    form.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = value);
    tabs.forEach(el => el.disabled = value);
    root.querySelector('#aiClearChat').disabled = value;
    root.querySelector('.ai-output').setAttribute('aria-busy', String(value));
  }
  async function request(url, method, body) {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: body ? JSON.stringify(body) : undefined, signal: AbortSignal.timeout(35000) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      if ([401, 419].includes(response.status) || response.redirected) throw new Error('Session expired. Refresh and sign in again.');
      if (response.status === 422) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Check the selected fields.');
      if (response.status === 404) throw new Error('This record is no longer available. Refresh the page.');
      throw new Error(data.message || 'AI could not connect. Please try again.');
    }
    if (response.redirected) throw new Error('Session expired. Refresh and sign in again.');
    return data;
  }
  function append(parent, tag, text, className) {
    const el = document.createElement(tag); el.textContent = text;
    if (className) el.className = className;
    parent.append(el); return el;
  }
  function show(data) {
    empty.hidden = true; result.hidden = false;
    root.querySelector('#aiSummary').textContent = data.summary;
    root.querySelector('.ai-result-head h3').textContent = data.engine === 'automated' ? 'Automated reply' : 'Analysis';
    const evidence = root.querySelector('#aiEvidence'); evidence.replaceChildren();
    const insights = data.snapshot.business_insights;
    evidence.hidden = !insights;
    if (insights) {
      append(evidence, 'h4', 'Computed evidence');
      const forecast = insights.forecast;
      if (forecast) append(evidence, 'p', forecast.next_7_days_order_value === null ? 'Sales estimate unavailable: insufficient completed-order history.' : `Next 7 days estimated order value: ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(forecast.next_7_days_order_value)}. Historical-average estimate, not a guarantee.`);
      const rows = mode === 'inventory' ? insights.inventory_recommendations : mode === 'anomalies' ? insights.alerts.filter(row => ['order_burst', 'repeated_cancellations', 'unusual_order_value'].includes(row.type)) : insights.product_sales;
      const headers = mode === 'inventory' ? ['Product', 'Stock', 'Estimated days', 'Reorder estimate (30 days)'] : mode === 'anomalies' ? ['Review warning', 'Evidence', 'Next step', 'Review'] : ['Best sellers (units)', 'Units', 'Item value PHP'];
      if (rows?.length) {
        const wrap = append(evidence, 'div', '', 'ai-evidence-table'); const table = append(wrap, 'table', '');
        const head = append(table, 'thead', ''); const tr = append(head, 'tr', ''); headers.forEach(text => append(tr, 'th', text));
        const body = append(table, 'tbody', '');
        rows.slice(0, 10).forEach(row => {
          const values = mode === 'inventory' ? [row.name, row.stock, row.estimated_days_remaining ?? 'Insufficient history', row.estimated_reorder_units_for_30_days ?? 'Insufficient history'] : mode === 'anomalies' ? [row.title, row.evidence, row.action] : [row.name, row.units, row.item_value_before_order_discounts];
          const tr = append(body, 'tr', ''); values.forEach(text => append(tr, 'td', String(text)));
          if (mode === 'anomalies') {
            const cell = append(tr, 'td', '');
            (row.order_ids || []).slice(0, 3).forEach((id, index) => {
              if (typeof id !== 'string' && typeof id !== 'number') return;
              const link = append(cell, 'a', `Order ${index + 1}`); link.href = root.dataset.order.replace('__ORDER__', encodeURIComponent(id));
              append(cell, 'br', '');
            });
          }
        });
      } else append(evidence, 'p', 'No matching evidence for this period. This does not imply all activity is safe.');
    }
    const risk = root.querySelector('#aiRisk');
    risk.hidden = !['product', 'seller'].includes(mode);
    risk.dataset.risk = data.risk;
    risk.textContent = { no_obvious_concerns: 'No obvious concerns', needs_review: 'Needs review', potential_policy_violation: 'Potential policy violation', not_applicable: '' }[data.risk];
    root.querySelector('#aiScope').textContent = mode === 'product' ? `${data.snapshot.photo_note} This is not a safety certification.` : `Snapshot: ${data.snapshot.generated_at}. Human verification required.`;
    const findings = root.querySelector('#aiFindings'); findings.replaceChildren();
    data.findings.forEach(item => { const row = append(findings, 'section', '', 'ai-finding'); append(row, 'h4', item.title); append(row, 'p', item.evidence); append(row, 'p', item.next_step); });
    const limits = root.querySelector('#aiLimitations'); limits.replaceChildren();
    data.limitations.forEach(text => append(limits, 'li', text));
    root.querySelector('#aiLimitsSection').hidden = !data.limitations.length;
    const sources = root.querySelector('#aiSources'); sources.replaceChildren();
    data.sources.forEach(source => { const url = new URL(source.url, location.href); if (url.origin === location.origin && url.pathname.startsWith('/admin/')) { const link = append(sources, 'a', source.label); link.href = url.href; } });
    root.querySelector('#aiSnapshot').textContent = JSON.stringify(data.snapshot, null, 2);
    if (mode === 'draft') {
      draft.hidden = false;
      root.querySelector('#aiDraftTitle').value = data.draft.title;
      root.querySelector('#aiDraftBody').value = data.draft.body;
      root.querySelector('#aiDraftAudience').value = data.draft.audience;
    }
    if (mode === 'product_copy') {
      productCopy.hidden = false;
      root.querySelector('#aiCopyTitle').value = data.draft.title;
      root.querySelector('#aiCopyBody').value = data.draft.body;
      root.querySelector('#aiCopyTags').value = (data.draft.tags || []).join(', ');
      root.querySelector('#aiCopyCategory').value = data.draft.category || '';
    }
    if (mode === 'chat') {
      if (data.engine === 'automated' && data.suggestions) renderQuestions(data.suggestions);
      const chat = root.querySelector('#aiChat');
      append(chat, 'p', message.value.trim()).dataset.role = 'user';
      append(chat, 'p', data.summary).dataset.role = 'assistant';
      while (chat.children.length > 8) chat.firstElementChild.remove();
      chat.scrollTop = chat.scrollHeight;
    }
  }
  tabs.forEach(tab => tab.addEventListener('click', () => setMode(tab.dataset.mode)));
  root.querySelector('.ai-tabs').addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || busy) return;
    event.preventDefault(); const i = tabs.findIndex(tab => tab.dataset.mode === mode);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (i + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    setMode(tabs[next].dataset.mode, true);
  });
  questions.addEventListener('click', event => {
    const button = event.target.closest('[data-question]'); if (!button || busy) return;
    message.value = button.dataset.question; message.focus();
    if (engine() === 'automated') form.requestSubmit();
  });
  let previousEngine = root.querySelector('[name="adminChatEngine"]:checked').value;
  root.querySelectorAll('[name="adminChatEngine"]').forEach(radio => radio.addEventListener('change', async () => {
    if (busy || radio.value === previousEngine) return;
    const selected = radio.value; setBusy(true);
    try {
      await request(root.dataset.clear, 'DELETE'); previousEngine = selected;
      root.querySelector('#aiChat').replaceChildren(); root.querySelector('#aiConsent').checked = false;
      result.hidden = true; empty.hidden = false; status.textContent = ''; updateEngine();
    } catch (error) {
      root.querySelectorAll('[name="adminChatEngine"]').forEach(input => input.checked = input.value === previousEngine);
      status.textContent = error.message;
    } finally { setBusy(false); }
  }));
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    const data = { mode, engine: engine(), message: message.value.trim(), days: Number(root.querySelector('#aiDays').value), consent: root.querySelector('#aiConsent').checked };
    if (['product', 'product_copy'].includes(mode)) { data.product_id = root.querySelector('#aiProduct').value; data.include_photo = root.querySelector('#aiPhoto').checked; }
    if (mode === 'seller') data.seller_id = Number(root.querySelector('#aiSeller').value);
    if (mode === 'draft') data.audience = root.querySelector('#aiAudience').value;
    setBusy(true); status.textContent = data.engine === 'automated' ? 'Checking dashboard...' : 'Analyzing...'; result.hidden = true; draft.hidden = true; productCopy.hidden = true;
    try { show(await request(root.dataset.endpoint, 'POST', data)); status.textContent = ''; }
    catch (error) { status.textContent = error.name === 'TimeoutError' ? 'AI took too long. Please try again.' : error.message; }
    finally { setBusy(false); }
  });
  root.querySelector('#aiClearChat').addEventListener('click', async () => {
    if (busy) return; setBusy(true);
    try { await request(root.dataset.clear, 'DELETE'); root.querySelector('#aiChat').replaceChildren(); result.hidden = true; empty.hidden = false; status.textContent = ''; }
    catch (error) { status.textContent = error.message; }
    finally { setBusy(false); }
  });
  root.querySelector('#aiCopyDraft').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(`${root.querySelector('#aiDraftTitle').value}\n\n${root.querySelector('#aiDraftBody').value}`); status.textContent = 'Draft copied.'; }
    catch { status.textContent = 'Clipboard unavailable. Open the draft in announcements to review it.'; }
  });
  root.querySelector('#aiCopyProduct').addEventListener('click', async () => {
    const text = ['aiCopyTitle', 'aiCopyBody', 'aiCopyTags', 'aiCopyCategory'].map(id => root.querySelector(`#${id}`).value).join('\n\n');
    try { await navigator.clipboard.writeText(text); status.textContent = 'Reviewed draft copied. Nothing has been published.'; }
    catch { status.textContent = 'Clipboard unavailable. The draft remains available for review.'; }
  });
  setMode(form.dataset.initialMode);
})();
