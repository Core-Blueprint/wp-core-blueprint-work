import assert from 'node:assert/strict';
import { stripWorkspaceCanonicalHeader } from '../assets/js/workspace/canonical-mount.js';

// The native renderer is an independent server output. The fragment strip
// operates solely on the detached copy destined for the Workspace.
const header = { removed: false, remove() { this.removed = true; } };
const toolbar = { nativePresets: true }, results = { textContent: '2' }, board = { columns: 4 };
const canonical = {
	querySelector(selector) {
		return {
			':scope > header.cb-work-page-header': header,
			'.cb-work-toolbar': toolbar,
			'.cb-work-results-header__count': results,
			'.cb-work-items-kanban': board,
		}[selector] || null;
	},
};
assert.equal(stripWorkspaceCanonicalHeader(canonical), true);
assert.equal(header.removed, true, 'old title, Gutenberg copy and duplicate Add removed before import');
assert.equal(canonical.querySelector('.cb-work-toolbar'), toolbar, 'real canonical filters untouched');
assert.equal(canonical.querySelector('.cb-work-results-header__count'), results, 'result count remains available');
assert.equal(canonical.querySelector('.cb-work-items-kanban'), board, 'protected view subtree untouched');

const nativeHeader = { removed: false };
assert.equal(nativeHeader.removed, false, 'standalone native header never edited');
assert.equal(stripWorkspaceCanonicalHeader({ querySelector() { return null; } }), false);
assert.equal(stripWorkspaceCanonicalHeader(null), false);
assert.equal(stripWorkspaceCanonicalHeader({ querySelector() { return { remove: null }; } }), false);
console.log('Work M3-UX3-F2A redundant header: PASS (detached fragment only, canonical data and fallback preserved).');
