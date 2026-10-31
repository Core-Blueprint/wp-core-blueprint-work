import assert from 'node:assert/strict';
import { minimizeUnassignedBoardContext } from '../assets/js/workspace/canonical-mount.js';

function card(projectName) {
	const project = {textContent: projectName};
	const context = {hidden: false, querySelector(selector) {
		return selector === '.cb-work-board__project' ? project : null;
	}};
	return {context,querySelector(selector) {
		return selector === '.cb-work-board__context' ? context : null;
	}};
}
const empty = card('No project'), assigned = card('Client Website'), translated = card('Geen project');
const root = {querySelectorAll(selector) {
	assert.equal(selector,'.cb-work-board__card');
	return [empty,assigned,translated];
}};
assert.equal(minimizeUnassignedBoardContext(root,'No project'),1);
assert.equal(empty.context.hidden,true);
assert.equal(assigned.context.hidden,false);
assert.equal(translated.context.hidden,false);
assert.equal(minimizeUnassignedBoardContext(root,'Geen project'),1);
assert.equal(translated.context.hidden,true);
assert.equal(minimizeUnassignedBoardContext(root,''),0);
assert.equal(minimizeUnassignedBoardContext(null,'No project'),0);
console.log('Work M3-UX3-F2C Board projection: PASS (exact localized placeholder, non-destructive cards, no-JS fallback).');
