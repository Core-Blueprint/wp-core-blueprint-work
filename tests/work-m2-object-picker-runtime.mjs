import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const src = readFileSync(new URL('../assets/js/workspace/editors.js', import.meta.url), 'utf8');
const js = src.replace(/^import .+;$/gm, '').replace(/^export /gm, '');
const ctx = { URL, URLSearchParams, JSON, Object, Number, Set, Map };
runInNewContext(js + '\n;globalThis.candidate = {objectPicker, readFields};', ctx);
const { objectPicker, readFields } = ctx.candidate;

class Element {
	constructor(tag) { this.tag = tag; this.dataset = {}; this.children = []; this.attributes = {}; }
	setAttribute(key, value) { this.attributes[key] = value; }
	append(...children) { this.children.push(...children); }
}
const document = { createElement: (tag) => new Element(tag) };
const root = { dataset: { cbWorkM2Instance: '1' } };
const nonce = 'private-nonce';
const selected = [{ id: 9, label: 'Contributor', meta: 'contributor' }];
const assignees = objectPicker(document, root, 'Assignees', 'assigned_user_ids',
	true, { nonce, selected }, 'cb_work_search_users', 'Search', 'Empty');
assert.equal(assignees.input.name, 'assigned_user_ids');
assert.equal(assignees.input.value, '9');
const picker = assignees.wrap.children[1];
assert.equal(picker.attributes['data-cb-core-object-picker'], '');
assert.equal(picker.dataset.searchNonce, nonce);
assert.equal(picker.dataset.searchAction, 'cb_work_search_users');
assert.equal(picker.dataset.multiple, '1');
assert.deepEqual(JSON.parse(picker.dataset.selected), selected);
assert.equal(picker.children[1].attributes['data-cb-core-object-picker-enhanced'], '');
const customers = objectPicker(document, root, 'Customer', 'customer',
	false, { nonce, selected: [{ id: 'crm:contact:13', label: 'Customer' }] },
	'cb_work_search_customers', 'Search', 'Empty');
assert.equal(customers.input.value, 'crm:contact:13');
assert.equal(customers.wrap.children[1].dataset.multiple, '0');

const controls = [
	{ name: 'work_context', value: 'customer', disabled: false, reportValidity: () => true },
	{ name: 'customer', value: 'crm:contact:13', disabled: false, reportValidity: () => true },
	{ name: 'assigned_user_ids', value: '4,9', disabled: false, reportValidity: () => true },
	{ name: 'disabled_customer', value: 'sensitive', disabled: true, reportValidity: () => false },
];
const fields = JSON.parse(JSON.stringify(readFields({querySelectorAll: () => controls})));
assert.deepEqual(fields, {
	work_context: 'customer', customer: 'crm:contact:13', assigned_user_ids: '4,9',
});
assert.match(src, /browser\.cbCore\.objectPicker\.init\(body\)/);
assert.doesNotMatch(src, /import\s*['"]@cb-core\/object-picker['"]/);
assert.match(src, /browser\.cbCore\?\.objectPicker\?\.init/);
assert.doesNotMatch(src, /innerHTML\s*=/);
console.log('Work M2 Base Object Picker modal contract: PASS.');
