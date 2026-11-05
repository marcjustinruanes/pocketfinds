const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const scope = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/js/registration-flow.js'), 'utf8'), scope);
const { registrationStages, advancePanels } = scope.module.exports;
const list = value => JSON.parse(JSON.stringify(value));

test('each role keeps every required section in its grouped flow', () => {
  const expected = {
    buyer: [2, 3, 4, 5, 6], seller: [1, 10, 2, 3, 4, 5, 6],
    rider: [5, 9, 2, 7, 3, 4, 6],
    'logistics-staff': [5, 8, 2, 3, 4, 6],
    logistics: [7, 5, 8, 3, 2, 4, 6],
  };
  for (const [role, panels] of Object.entries(expected)) {
    const stages = registrationStages(role);
    assert.deepEqual(list(stages.flatMap(stage => stage.panels)), panels);
    assert.equal(stages.at(-1).title, 'Account & Confirmation');
    assert.ok(stages.some(stage => stage.panels.includes(3)));
    assert.ok(stages.some(stage => stage.panels.length === 1 && stage.panels[0] === 4));
    assert.equal(new Set(panels).size, panels.length);
  }
});

test('combined stages stop at the first failed validation', () => {
  const visited = [];
  let active;
  const passed = advancePanels([3, 4], id => { active = id; }, id => {
    visited.push(id);
    if (id === 3) active = 4;
  }, id => active === id);
  assert.equal(passed, false);
  assert.deepEqual(visited, [3, 4]);
  visited.length = 0;
  assert.equal(advancePanels([3, 4], id => { active = id; }, id => visited.push(id), id => active === id), false);
  assert.deepEqual(visited, [3]);
});

test('combined stages advance only after all original validators succeed', () => {
  let active;
  const visited = [];
  assert.equal(advancePanels([7, 5], id => { active = id; }, id => {
    visited.push(id); active = id + 1;
  }, id => active === id), true);
  assert.deepEqual(visited, [7, 5]);
});

test('legacy Google forms retain their different panel identifiers', () => {
  assert.deepEqual(list(registrationStages('buyer', true).flatMap(stage => stage.panels)), [1, 2, 3, 4, 5]);
  assert.deepEqual(list(registrationStages('seller', true)[0].panels), [0]);
});

test('seller business and identity documents have separate steps',()=>{
  const stages=registrationStages('seller');
  assert.equal(stages.length,7);
  assert.deepEqual(list(stages[1].panels),[10]);
  assert.equal(stages[1].title,'Business Details');
  assert.deepEqual(list(stages[2].panels),[2]);
  assert.equal(stages[2].title,'Identity Verification');
});
