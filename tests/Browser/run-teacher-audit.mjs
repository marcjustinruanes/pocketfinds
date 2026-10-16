import { spawnSync } from 'node:child_process';
import { writeFileSync, readFileSync, existsSync } from 'node:fs';
const allScripts = ['teacher-smoke.mjs', 'teacher-registration.mjs', 'teacher-rider-invitation.mjs',
  'teacher-buyer-account.mjs', 'teacher-store-categories.mjs', 'teacher-store-profile.mjs', 'teacher-vacation.mjs', 'teacher-product-publishing.mjs', 'teacher-search.mjs', 'teacher-cart.mjs', 'teacher-wishlist.mjs', 'teacher-cart-basics.mjs', 'teacher-buyer-registration-full.mjs', 'teacher-product-validation.mjs', 'mysql-checkout-concurrency.mjs'];
const requested = process.argv.slice(2);
if (requested.some(script => !allScripts.includes(script))) throw new Error('Unknown audit script.');
const scripts = requested.length ? allScripts.filter(script => requested.includes(script)) : allScripts;
const summaryPath = 'docs/teacher-test-audit/browser-run-summary.json';
const results = requested.length && existsSync(summaryPath) ? JSON.parse(readFileSync(summaryPath, 'utf8')) : [];
for (const script of scripts) {
  const started = new Date().toISOString();
  console.log(`Running ${script}`);
  const result = spawnSync(process.execPath, [`tests/Browser/${script}`], { encoding: 'utf8', timeout: ['teacher-buyer-registration-full.mjs', 'teacher-product-validation.mjs'].includes(script) ? 720000 : 300000 });
  const entry = { script, started, finished: new Date().toISOString(), exit_code: result.status,
    status: result.status === 0 ? 'PASS' : 'FAIL', error: result.error?.message || null };
  const index = results.findIndex(row => row.script === script);
  if (index === -1) results.push(entry); else results[index] = entry;
  writeFileSync(`docs/teacher-test-audit/${script.replace('.mjs', '-run.log')}`, `${result.stdout || ''}\n${result.stderr || ''}`);
  writeFileSync(summaryPath, JSON.stringify(results, null, 2));
  console.log(`${script}: ${entry.status}`);
}
if (results.some(result => result.status !== 'PASS')) process.exitCode = 1;
