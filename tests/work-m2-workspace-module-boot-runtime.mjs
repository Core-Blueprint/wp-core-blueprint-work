import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createContext, SourceTextModule, SyntheticModule } from 'node:vm';

// Reproduce WordPress's import-map behavior: Base Object Picker is an
// independently enqueued root module, NOT an import-map entry. The frozen
// M1 deps still provide @cb-core/modal as an import-map dependency.
const dir = resolve(dirname(fileURLToPath(import.meta.url)), '../assets/js/workspace');
const src = resolve(dir, 'app.js');
const registered = new Set(['@cb-core/modal']);
const source = (path) => readFileSync(path, 'utf8');
const editor = source(resolve(dir, 'editors.js'));
const php = source(resolve(dir, '../../../src/Admin/Assets.php'));
assert.match(php, /Assets::enqueue_object_picker\(\)/);
assert.match(php, /self::VIEW_PREFERENCES_SCRIPT_HANDLE \],/);
assert.match(php, /\[ '@cb-core\/modal', '@cb-core\/reorder' \]/);
assert.doesNotMatch(editor, /import\s*['"]@cb-core\/object-picker['"]/);
assert.match(editor, /browser\.cbCore\?\.objectPicker\?\.init/);

const context = createContext({ URL, URLSearchParams, console });
const modules = new Map();
const bare = new Set();
function load(path) {
	if (modules.has(path)) return modules.get(path);
	const module = new SourceTextModule(source(path), { context, identifier: path });
	modules.set(path, module);
	return module;
}
const entry = load(src);
await entry.link((specifier, referencing) => {
	if (specifier.startsWith('@')) {
		bare.add(specifier);
		if (!registered.has(specifier)) {
			throw new Error('Unresolved WordPress import-map specifier: ' + specifier);
		}
		const id = 'mapped:' + specifier;
		if (!modules.has(id)) {
			modules.set(id, new SyntheticModule([], function () {}, { context, identifier: id }));
		}
		return modules.get(id);
	}
	if (!specifier.startsWith('./') && !specifier.startsWith('../')) {
		throw new Error('Unexpected import ' + specifier);
	}
	const path = resolve(dirname(referencing.identifier), specifier);
	if (!path.startsWith(dir + sep)) throw new Error('Import escaped workspace modules.');
	return load(path);
});
await entry.evaluate();
assert.deepEqual([...bare].sort(), ['@cb-core/modal']);
assert.ok(modules.has(resolve(dir, 'editors.js')));
assert.equal(typeof entry.namespace.mountWorkspace, 'function');

// Verify actual entrypoint hydration executes without the picker importmap
// key. In this pure VM mock, no network exists, so it must retain SSR but
// must still attach Workspace event listeners and route its own sections.
const sections = ['work-items', 'projects', 'services', 'recurring'];
const listeners = new Map();
const tabs = sections.map((key) => ({
	dataset: { cbWorkUweTab: key },
	setAttribute(name, value) { this[name] = value; },
	classList: { toggle() {} },
	addEventListener(name, handler) { listeners.set('tab:' + key + ':' + name, handler); },
}));
const panels = sections.map((key) => ({
	dataset: { cbWorkUwePanel: key },
	querySelector() { return null; },
	hidden: false,
}));
const root = {
	dataset: {},
	ownerDocument: { activeElement: null },
	querySelectorAll(selector) {
		if (selector === '[data-cb-work-uwe-tab]') return tabs;
		if (selector === '[data-cb-work-uwe-panel]') return panels;
		return [];
	},
	querySelector() { return null; },
	addEventListener() {},
};
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	scrollY: 0,
	history: {
		state: null,
		pushState(_data, _title, target) {
			browser.location.href = new URL(target, browser.location.href).href;
		},
		replaceState(_data, _title, target) {
			browser.location.href = new URL(target, browser.location.href).href;
		},
	},
	addEventListener(name, handler) { listeners.set('browser:' + name, handler); },
};
const router = entry.namespace.mountWorkspace(root, browser);
assert.ok(router, 'Workspace must boot without Object Picker in importmap');
assert.equal(root.dataset.cbWorkUweMounted, '1');
assert.equal(typeof listeners.get('tab:work-items:click'), 'function');
assert.equal(router.navigate('projects'), true);
assert.equal(panels[0].hidden, true);
assert.equal(panels[1].hidden, false);
assert.equal(new URL(browser.location.href).searchParams.get('page'), 'core-blueprint-work-workspace');
console.log('M2 WordPress import-map module graph and Workspace boot: PASS.');
