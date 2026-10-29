import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const root = new URL('../', import.meta.url);
const source = readFileSync(new URL('assets/js/workspace/editors.js', root), 'utf8');
const stripped = source.replace("import '@cb-core/modal';", '').replace(/^export /gm, '')
    + '\n;globalThis.workM2Runtime = { readFields, SUPPORTED };';
const environment = { Set, Map, URL, URLSearchParams, Number, Object };
runInNewContext(stripped, environment);
const { readFields, SUPPORTED } = environment.workM2Runtime;
assert.equal(SUPPORTED.has('service'), true);
assert.equal(SUPPORTED.has('work-item'), true);
assert.equal(SUPPORTED.has('project'), true);
assert.equal(SUPPORTED.has('unexpected'), false);

const controls = [
    { name: 'title', value: 'Maintenance', disabled: false, reportValidity: () => true },
    { name: 'amount', value: '95.00', disabled: false, reportValidity: () => true },
    { name: 'pricing_model', value: 'fixed', disabled: false, reportValidity: () => true },
    { name: 'recurring_period', value: 'monthly', disabled: true, reportValidity: () => true },
    { name: 'tax_mode', value: 'exempt', disabled: false, reportValidity: () => true },
    { name: 'tax_rate_id', value: '12', disabled: true, reportValidity: () => true },
];
const read = (list) => JSON.parse(JSON.stringify(readFields({
    querySelectorAll: () => list,
})));
assert.deepEqual(read(controls), {
    title: 'Maintenance',
    amount: '95.00',
    pricing_model: 'fixed',
    tax_mode: 'exempt',
});
const invalid = [ ...controls ];
invalid[0] = { ...controls[0], reportValidity: () => false };
assert.equal(readFields({ querySelectorAll: () => invalid }), null);
assert.match(source, /entity === 'service'/);
assert.match(source, /price\.pricing_model/);
assert.match(source, /data-cb-work-uwe-modal/);
assert.match(source, /'expected_version'/);
assert.doesNotMatch(source, /innerHTML\s*=/);
assert.doesNotMatch(source, /document\.createElement\('form'\)/);
console.log('Work M2 Service modal payload and disabled-field behavior: PASS.');
