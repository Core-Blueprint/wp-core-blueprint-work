import assert from 'node:assert/strict';
import { composeCanonicalToolbar } from '../assets/js/workspace/canonical-mount.js';

// Do not clone or re-render the canonical controls. Their original classes,
// hrefs, form ownership and M1 delegated events must survive the move.
const presets = { kind: 'focus-presets', href: 'native-canonical-link' };
const primary = { kind: 'filters', action: 'native-canonical-filters' };
const children = [primary];
const row = {
	querySelector(selector) { return selector === '.cb-work-toolbar__primary' ? primary : null; },
	insertBefore(node, sibling) {
		assert.equal(sibling, primary);
		children.splice(children.indexOf(sibling), 0, node);
	},
};
const canonical = {querySelector(selector) {
	return {
		'.cb-work-fast-paths': presets,
		'.cb-work-toolbar__row': row,
	}[selector] || null;
}};
assert.equal(composeCanonicalToolbar(canonical), true);
assert.deepEqual(children, [presets, primary], 'real preset and filter nodes stay intact');
assert.equal(presets.href, 'native-canonical-link');
assert.equal(primary.action, 'native-canonical-filters');
assert.equal(composeCanonicalToolbar({querySelector() { return null; }}), false, 'graceful on invalid/empty markup');
console.log('Work M3-UX3-F1 toolbar runtime: PASS (reparent, native identity, missing-node fallback).');
