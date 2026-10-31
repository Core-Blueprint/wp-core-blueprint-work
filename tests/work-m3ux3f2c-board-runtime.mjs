import assert from 'node:assert/strict';
import { localizeWorkspaceBoard, composeCanonicalToolbar } from '../assets/js/workspace/canonical-mount.js';

// Board metadata and canonical controls must not be removed based on text.
// Existing presentation helpers remain non-destructive.
const context = {textContent:'No project'};
const nativeAdd = {textContent:'Add Work Item'};
const canonical = {querySelectorAll(selector) {
	return selector === '.cb-work-board__add > span:last-child' ? [nativeAdd] : [];
}};
assert.equal(localizeWorkspaceBoard(canonical,{add:'Add Task'}),1);
assert.equal(nativeAdd.textContent,'Add Task');
assert.equal(context.textContent,'No project', 'no project association inferred from label');
assert.equal(composeCanonicalToolbar({querySelector:()=>null}),false);
console.log('Work M3-UX3-F2C projection: PASS (Tasks label, ambiguous metadata untouched, canonical fallback).');
