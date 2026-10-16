import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const origin = process.env.AUDIT_URL || 'http://127.0.0.1:8020';
assert.equal(origin, 'http://127.0.0.1:8020');
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/store-category-fixture.php', action], { encoding: 'utf8' }));
fixture('prepare');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage(); page.setDefaultTimeout(10000);
const buyerContext = await browser.newContext(), buyer = await buyerContext.newPage(); buyer.setDefaultTimeout(10000);
const results = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
const manage = () => page.goto(`${origin}/seller/store-categories`);
const formFor = category => page.locator(`section[data-category-id="${category.id}"]`);
const inspect = () => fixture('inspect');
const category = name => inspect().categories.find(item => item.name === name);
async function submit(form) { await Promise.all([page.waitForEvent('load'), form.locator('button[type=submit]').click()]); }
async function create(name, parent) {
  const form = page.locator('form[action$="/seller/store-categories"]');
  await form.locator('[name=name]').fill(name);
  await form.locator('[name=parent_id]').selectOption(parent ? String(parent.id) : '');
  await submit(form);
  return category(name);
}
async function assign(cat, names) {
  const state = inspect(), products = state.products.filter(product => names.includes(product.name));
  assert.equal(products.length, names.length);
  const form = formFor(cat).locator('form[action$="/products"]');
  await form.locator('select').selectOption(products.map(product => product.id)); await submit(form);
}
async function run(ids, task, evidence) {
  try { await task(); results.push({ cases: ids, status: 'PASS', evidence }); console.log(`${ids.join(', ')} PASS`); }
  catch (error) {
    results.push({ cases: ids, status: 'FAIL', evidence: error.message }); process.exitCode = 1;
    console.log(`${ids.join(', ')} FAIL: ${error.message}`);
    await page.screenshot({ path: `docs/teacher-test-audit/failure-${ids[0]}.png`, fullPage: true }).catch(() => {});
  }
}
async function store(cat) {
  return buyer.request.get(`${origin}/buyer/shop/store_category_audit_1${cat ? `?store_category=${cat.id}` : ''}`);
}
try {
  await page.goto(`${origin}/login`); await page.locator('#email').fill('category.audit1@example.com'); await page.locator('#password').fill('CategoryAudit@2026');
  await Promise.all([page.waitForURL('**/seller/dashboard'), page.locator('form.auth-form button[type=submit]').click()]);
  await buyer.goto(`${origin}/login`); await buyer.locator('#email').fill('buyer@example.com'); await buyer.locator('#password').fill('TeacherAudit@2026');
  await Promise.all([buyer.waitForURL('**/buyer/dashboard'), buyer.locator('form.auth-form button[type=submit]').click()]);
  await manage();
  await run(['SEL-SCAT-001'], async () => {
    const bags = await create('Bags'); await assign(bags, ['Abaca Tote']);
    assert.equal(category('Bags').products.length, 1);
    const response = await store(bags); assert.equal(response.status(), 200);
    const html = await response.text(); assert.match(html, /Abaca Tote/); assert.doesNotMatch(html, /Rattan Fruit Basket|Native Lamp|Buri Placemat/);
  }, 'Chrome/MySQL: created Bags and assigned Abaca Tote; management count one; store category HTTP 200 contains only that product.');
  await run(['SEL-SCAT-002', 'SEL-SCAT-003'], async () => {
    for (const name of ['', '   ', 'Bags', 'bags', ' BAGS ']) {
      const before = inspect().categories.length;
      const form = page.locator('form[action$="/seller/store-categories"]'); await form.locator('[name=name]').fill(name);
      if (await form.evaluate(form => form.checkValidity())) { await submit(form); assert.ok(await page.locator('[role=alert]').count()); }
      assert.equal(inspect().categories.length, before);
    }
    const token = await page.locator('input[name=_token]').first().inputValue();
    const response = await page.request.post(`${origin}/seller/store-categories`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { name: 'A'.repeat(51) } });
    assert.equal(response.status(), 422);
    await create('A'); await create('B'.repeat(50));
    assert.ok(category('A')); assert.ok(category('B'.repeat(50)));
    assert.equal(inspect().categories.filter(item => item.name.toLowerCase() === 'bags').length, 1);
  }, 'Chrome/MySQL: empty/space/duplicate-case/trimmed duplicate names rejected without writes; direct 51-character request rejected; one- and fifty-character names saved exactly.');
  await run(['SEL-SCAT-004'], async () => {
    const context = await browser.newContext(); const other = await context.newPage();
    try {
      await other.goto(`${origin}/login`); await other.locator('#email').fill('category.audit2@example.com'); await other.locator('#password').fill('CategoryAudit@2026');
      await Promise.all([other.waitForURL('**/seller/dashboard'), other.locator('form.auth-form button[type=submit]').click()]);
      await other.goto(`${origin}/seller/store-categories`);
      const form = other.locator('form[action$="/seller/store-categories"]'); await form.locator('[name=name]').fill('Bags');
      await Promise.all([other.waitForEvent('load'), form.locator('button[type=submit]').click()]);
      assert.match(await other.textContent('body'), /Store category created/);
      assert.equal(inspect().categories.filter(item => item.name === 'Bags').length, 1);
    } finally { await context.close(); }
  }, 'Two independent Chrome seller sessions: Seller 2 created Bags while Seller 1 retained exactly its own Bags unchanged.');
  await run(['SEL-SCAT-005', 'SEL-SCAT-006'], async () => {
    const dining = await create('Kitchen & Dining'); await assign(dining, ['Buri Placemat Set']);
    const edit = () => formFor(dining).locator('form').first();
    await edit().locator('[name=name]').fill('bags'); await submit(edit());
    assert.ok(category('Kitchen & Dining')); assert.match(await page.textContent('body'), /already exists/);
    await edit().locator('[name=name]').fill('Kitchen'); await submit(edit());
    while (inspect().categories[0].id !== dining.id) {
      await Promise.all([page.waitForEvent('load'), formFor(dining).locator('[data-move-category=up]').click()]);
    }
    await page.reload(); assert.equal(inspect().categories[0].name, 'Kitchen');
    assert.equal(category('Kitchen').products[0].name, 'Buri Placemat Set');
    const html = await (await store()).text(); assert.ok(html.indexOf('>Kitchen</a>') < html.indexOf('>Bags</a>'));
  }, 'Chrome/MySQL: duplicate rename blocked; renamed Kitchen, moved to first, refreshed and confirmed store navigation first with product membership unchanged.');
  await run(['SEL-SCAT-007', 'SEL-SCAT-008'], async () => {
    const bags = category('Bags'), before = bags.position;
    const edit = () => formFor(bags).locator('form').first();
    await edit().locator('[name=is_visible][type=checkbox]').uncheck(); await submit(edit());
    assert.equal((await store(bags)).status(), 404);
    const html = await (await store()).text();
    const navigation = html.match(/<nav aria-label="Store categories"[\s\S]*?<\/nav>/)?.[0]; assert.ok(navigation);
    assert.doesNotMatch(navigation, new RegExp(`store_category=${bags.id}["&]`)); assert.match(html, /Abaca Tote/);
    const search = await buyer.request.get(`${origin}/buyer/browse?q=Abaca%20Tote`);
    assert.equal(search.status(), 200); assert.match(await search.text(), /Abaca Tote/);
    const productId = category('Bags').products[0].id;
    assert.equal((await page.request.get(`${origin}/product/${productId}`)).status(), 200);
    await edit().locator('[name=is_visible][type=checkbox]').check(); await submit(edit());
    assert.equal(category('Bags').position, before); assert.equal((await store(bags)).status(), 200);
  }, 'Chrome/MySQL: hidden category absent from navigation and direct category URL 404; product still visible and product URL 200; showing category restores original position and product.');
  await run(['SEL-SCAT-011', 'SEL-SCAT-012'], async () => {
    const home = await create('Home Decor'), lamps = await create('Lamps', home), baskets = await create('Baskets', home);
    const wall = await create('Wall Decor', home); assert.ok(wall);
    assert.equal(await page.locator('#new-category-parent option').filter({ hasText: /^Lamps$/ }).count(), 0);
    const token = await page.locator('input[name=_token]').first().inputValue();
    const response = await page.request.post(`${origin}/seller/store-categories`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, data: { name: 'Third level', parent_id: lamps.id } }); assert.equal(response.status(), 422);
    await assign(lamps, ['Native Lamp']); await assign(home, ['Native Lamp']); await assign(category('Kitchen'), ['Buri Placemat Set', 'Native Lamp']);
    const form = formFor(baskets).locator('form[action$="/products"]');
    await form.locator('select').selectOption(inspect().products.find(product => product.name === 'Native Lamp').id); await submit(form);
    assert.match(await page.textContent('body'), /at most 3/); assert.equal(category('Baskets').products.length, 0);
    const lamp = inspect().products.find(product => product.name === 'Native Lamp'); assert.equal(lamp.category_id, 1);
    for (const cat of [lamps, home, category('Kitchen')]) {
      const html = await (await store(cat)).text(); assert.equal((html.match(/class="(?:pf-product-name|product-name)">Native Lamp/g) || []).length, 1);
    }
  }, 'Chrome/MySQL: level-2 child saved, level-3 unavailable/rejected; product assigned to three categories and fourth rejected; product displayed once in each accepted category; platform category unchanged.');
  await run(['SEL-SCAT-013'], async () => {
    const before = inspect().products.find(product => product.name === 'Abaca Tote');
    await assign(category('Bags'), []); await assign(category('Kitchen'), ['Buri Placemat Set', 'Native Lamp', 'Abaca Tote']);
    const after = inspect().products.find(product => product.name === 'Abaca Tote');
    assert.equal(after.category_id, before.category_id);
    const html = await (await store(category('Kitchen'))).text(); assert.match(html, /Abaca Tote/);
    const platform = await page.request.get(`${origin}/?category=${after.category_id}`);
    assert.equal(platform.status(), 200); assert.match(await platform.text(), /Abaca Tote/);
    const token = await page.locator('input[name=_token]').first().inputValue();
    const denied = await page.request.post(`${origin}/admin/settings/categories`, { headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, data: { name: 'Unauthorized Category' }, maxRedirects: 0 });
    assert.ok([302, 403, 404, 405].includes(denied.status()));
  }, 'Chrome/MySQL: moved Abaca Tote from Bags into Kitchen; platform category ID unchanged and product still listed under its platform category; seller denied access to platform-category creation.');
  await run(['SEL-SCAT-009', 'SEL-SCAT-010'], async () => {
    await assign(category('Baskets'), ['Rattan Fruit Basket']);
    const single = category('Baskets');
    let warning;
    page.once('dialog', async dialog => { warning = dialog.message(); await dialog.dismiss(); });
    await formFor(single).locator('[data-delete-category] button').click(); assert.ok(category('Baskets')); assert.match(warning, /1 product\(s\) affected/);
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForEvent('load'), formFor(single).locator('[data-delete-category] button').click()]);
    assert.ok(!category('Baskets')); assert.equal(inspect().products.find(product => product.name === 'Rattan Fruit Basket').status, 'active');
    const moved = await buyer.request.get(`${origin}/buyer/shop/store_category_audit_1?store_category=uncategorized`);
    assert.equal(moved.status(), 200); assert.match(await moved.text(), /Rattan Fruit Basket/);
    // Recreate the teacher's parent fixture with two children and exactly two products.
    const home = category('Home Decor'), lamps = category('Lamps'), baskets = await create('Baskets', home);
    const wall = category('Wall Decor');
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForEvent('load'), formFor(wall).locator('[data-delete-category] button').click()]);
    assert.equal(inspect().categories.filter(item => item.parent_id === home.id).length, 2);
    await assign(home, []); await assign(category('Kitchen'), ['Buri Placemat Set']); await assign(baskets, ['Rattan Fruit Basket']);
    page.once('dialog', async dialog => { warning = dialog.message(); await dialog.accept(); });
    await Promise.all([page.waitForEvent('load'), formFor(home).locator('[data-delete-category] button').click()]);
    assert.match(warning, /Lamps/); assert.match(warning, /Baskets/); assert.match(warning, /2 product\(s\) affected/);
    const state = inspect(); assert.ok(!state.categories.some(item => [home.id, lamps.id, baskets.id].includes(item.id)));
    for (const name of ['Native Lamp', 'Rattan Fruit Basket']) assert.equal(state.products.find(product => product.name === name).status, 'active');
    const uncategorized = await page.request.get(`${origin}/shop/store_category_audit_1?store_category=uncategorized`);
    const html = await uncategorized.text(); assert.match(html, /Native Lamp/); assert.match(html, /Rattan Fruit Basket/);
  }, 'Chrome/MySQL: deletion warning showed product count; Cancel kept category; confirmed single and parent deletion retained active products, removed children/memberships, and both products appeared in Uncategorized.');
  await page.screenshot({ path: 'docs/teacher-test-audit/store-categories-browser.png', fullPage: true });
} finally {
  if (errors.length) { results.push({ status: 'FAIL', evidence: errors.join('; ') }); process.exitCode = 1; }
  for (const row of results) { row.origin = origin; row.executed_at = new Date().toISOString(); }
  await writeFile('docs/teacher-test-audit/store-categories-browser.json', JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2)); await browser.close();
}
