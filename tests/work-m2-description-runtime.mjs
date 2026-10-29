import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const root = new URL('../', import.meta.url);
const source = readFileSync(new URL('assets/js/workspace/editors.js', root), 'utf8');
const code = source.replace(/^import .+;$/gm, '').replace(/^export /gm, '')
	+ '\n;globalThis.m2 = { readFields };';
const ctx = { URL, URLSearchParams, Set, Map, Number, Object };
runInNewContext(code, ctx);
const fields = ctx.m2.readFields;

const input = (name, value, initial, disabled = false) => ({
	name, value, disabled, dataset: { cbWorkM2Initial: initial },
	reportValidity: () => true,
});
const values = (list) => JSON.parse(JSON.stringify(fields({
	querySelectorAll: () => list,
})));
const title = input('title', 'Updated task', undefined);
const plain = input('description', 'Existing paragraph\nSecond line', 'Existing paragraph\nSecond line');
assert.deepEqual(values([title, plain]), { title: 'Updated task' }, 'Untouched body must not be submitted.');
plain.value = 'Changed description';
assert.deepEqual(values([title, plain]), {
	title: 'Updated task', description: 'Changed description',
}, 'Only explicit body edits may enter the mutation payload.');
plain.value = '';
assert.deepEqual(values([title, plain]), {
	title: 'Updated task', description: '',
}, 'Deliberately clearing plain text must be persisted.');
plain.disabled = true;
assert.deepEqual(values([title, plain]), { title: 'Updated task' }, 'Disabled rich field must not be submitted.');

const gridAppend = "if (entity === 'work-item' || entity === 'project') {\n\t\tform.appendChild(grid);";
assert.ok(source.includes(gridAppend), 'Work Item/Project grids must be part of the mounted modal DOM.');
assert.match(source, /result\.description_editor/);
assert.match(source, /descriptionInfo\.mode === 'plain'/);
assert.match(source, /textarea\.dataset\.cbWorkM2Initial = textarea\.value/);
assert.match(source, /root\.dataset\.cbWorkM2RichDescription/);
assert.doesNotMatch(source, /innerHTML\s*=/);
console.log('M2 plain-text description, dirty-field and mounted-grid runtime: PASS.');
