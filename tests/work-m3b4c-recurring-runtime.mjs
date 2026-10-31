import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

// M3-B4-B3 source & transport regression: a fresh rule must submit the full
// default schema, while an existing rule sends only edited fields.
const script = readFileSync(new URL('../assets/js/workspace/recurring.js', import.meta.url), 'utf8');
const helper = script.match(/function readChanges\(form, original, quick = false\) \{[\s\S]*?\n\}/)?.[0];
assert.ok(helper, 'readChanges helper exists');
const readChanges = new Function('return (' + helper.replace(/^function readChanges/, 'function readChanges') + ')')();
const field = (name, value) => ({ name, value, disabled:false, reportValidity:()=>true });
const controls = [field('title','Maintenance'), field('work_context','internal'),
  field('frequency','weekly'), field('interval_count','1'), field('start_on','2026-10-31')];
const form = {querySelectorAll:()=>controls};
const complete = readChanges(form, null);
assert.deepEqual(complete, {
  title:'Maintenance', work_context:'internal', frequency:'weekly',
  interval_count:1, start_on:'2026-10-31',
}, 'complete create values retained');
const unchanged = readChanges(form, complete);
assert.deepEqual(unchanged, {}, 'unchanged edit is a no-op');
controls[0].value='New maintenance';
const partial = readChanges(form, complete);
assert.deepEqual(partial, {title:'New maintenance'}, 'only edited field emitted');
assert.match(script, /readChanges\(form, id > 0 \? original : null, quick\)/,
 'create must not compare against initial values');
assert.match(script, /X-WP-Nonce/, 'nonce sent in REST requests');
assert.match(script, /cbCore\.modal\.show/, 'public Base modal used');
assert.doesNotMatch(script, /innerHTML|\.showModal\(/, 'no private modal or unsafe HTML');
console.log('Work M3-B4-B3 recurrence edit payload and Base-contract source: PASS');
