import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { writeFile, unlink } from 'node:fs/promises';
import { createInterface } from 'node:readline';

const exec = promisify(execFile);
const fixture = JSON.parse((await exec('php', ['tests/Browser/mysql_checkout_fixture.php'])).stdout);
const workers = [];
const result = { database: fixture.database, case: 'SEL-INV-006', scope: 'Two concurrent Laravel HTTP requests on MySQL; fictional isolated fixtures.' };
try {
  const ready = [];
  const done = [];
  for (const index of [0, 1]) {
    let resolveReady;
    ready.push(new Promise(resolve => { resolveReady = resolve; }));
    const child = spawn('php', ['tests/Browser/mysql_checkout_worker.php', String(index)], { env: { ...process.env, APP_ENV: 'testing' }, windowsHide: true });
    workers.push(child);
    done.push(new Promise((resolve, reject) => {
      let output;
      const reader = createInterface({ input: child.stdout });
      reader.on('line', line => {
        try {
          const row = JSON.parse(line);
          if ('ready' in row) resolveReady();
          else output = row;
        } catch { /* A missing result fails this worker; never treat incidental output as success. */ }
      });
      child.on('error', reject);
      child.on('exit', code => code === 0 && output ? resolve(output) : reject(new Error(`Checkout worker ${index} exited ${code} without a result.`)));
    }));
  }
  const timeout = setTimeout(() => workers.forEach(worker => worker.kill()), 30000);
  try {
    await Promise.all(ready);
    await writeFile(fixture.barrier, 'go');
    result.workers = await Promise.all(done);
  } finally { clearTimeout(timeout); }
  Object.assign(result, JSON.parse((await exec('php', ['tests/Browser/mysql_checkout_fixture.php', 'inspect'])).stdout));
  assert.equal(result.orders, 1);
  assert.equal(result.remaining_stock, 0);
  assert.equal(result.remaining_cart_items, 1);
  assert.deepEqual(result.workers.map(row => row.orders).sort(), [0, 1]);
  assert.ok(result.workers.every(row => row.http_status === 302));
  assert.equal(result.workers.filter(row => row.error).length, 1);
  result.status = 'PASS';
} catch (error) {
  result.status = 'FAIL'; result.error = error.message; process.exitCode = 1;
} finally {
  workers.forEach(worker => worker.kill());
  await unlink(fixture.barrier).catch(() => {});
  await writeFile('docs/teacher-test-audit/mysql-checkout-concurrency.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result));
}
