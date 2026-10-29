import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const root = new URL('../', import.meta.url);
const source = readFileSync(new URL('assets/js/workspace/editors.js', root), 'utf8');
const code = source.replace("import '@cb-core/modal';", '')
	.replace(/^export /gm, '') + '\n;globalThis.exposed = { nativeWorkItemTarget };';
const context = { URL, Number, Set, Map };
runInNewContext(code, context);
const native = context.exposed.nativeWorkItemTarget;
const base = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace';
const read = (href) => {
	const result = native(href, base);
	return result ? JSON.parse(JSON.stringify(result)) : null;
};
assert.deepEqual(read('/wp-admin/post-new.php?post_type=cb_work_item'), { entity: 'work-item', id: 0 });
assert.deepEqual(read('/wp-admin/post.php?post=17&action=edit'), { entity: 'work-item', id: 17 });
assert.equal(read('/wp-admin/post.php?post=0&action=edit'), null);
assert.equal(read('/wp-admin/post.php?post=12&action=view'), null);
assert.equal(read('/wp-admin/post-new.php?post_type=cb_work_project'), null);
assert.equal(read('https://evil.test/wp-admin/post.php?post=17&action=edit'), null);
assert.equal(read('/wp-admin/post.php?post=17.2&action=edit'), null);
assert.equal(read('/wp-admin/post.php?post=9999999999999999999999&action=edit'), null);
assert.match(source, /modal\.show\(\{/);
assert.match(source, /onConfirm: async/);
assert.match(source, /control\.reportValidity\(\)/);
assert.doesNotMatch(source, /document\.createElement\('form'\)/);
assert.match(source, /root\.addEventListener\('click'/);
assert.match(source, /event\.stopPropagation\(\)/);
assert.match(source, /'@cb-core\\/modal'/);
assert.match(source, /postWorkType\\('cb_work_toggle_work_type'/);
assert.match(source, /postWorkType\\('cb_work_create_work_type'/);
assert.match(source, /data-cb-work-uwe-modal/);
assert.doesNotMatch(source, /window\.location\.reload\(/);
assert.doesNotMatch(source, /innerHTML\s*=/);
console.log('Work M2 Unified Editors URL, modal and safe DOM source: PASS.');
