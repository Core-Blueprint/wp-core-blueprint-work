import assert from 'node:assert/strict';
import { taskCountLabel } from '../assets/js/workspace/canonical-mount.js';
assert.equal(taskCountLabel('2','task','tasks'),'2 tasks');
assert.equal(taskCountLabel('1','taak','taken'),'1 taak');
assert.equal(taskCountLabel('0','task','tasks'),'0 tasks');
assert.equal(taskCountLabel('-1','task','tasks'),'');
assert.equal(taskCountLabel('nonsense','task','tasks'),'');
assert.equal(taskCountLabel('3.5','task','tasks'),'');
assert.equal(taskCountLabel('2','',''),'');
import { localizeWorkspaceBoard } from '../assets/js/workspace/canonical-mount.js';
const add = { textContent: 'Add Work Item' };
const empty = { textContent: 'No Work Items found.' };
const drop = { textContent: 'Drop Work Items here' };
const iconAdd = { attributes: {}, setAttribute(k,v){ this.attributes[k]=v; } };
const fragment = {querySelectorAll(selector){return ({
	'.cb-work-board__add > span:last-child':[add],
	'.cb-work-board__empty-title':[empty],
	'.cb-work-board__empty-hint':[drop],
	'.cb-work-board__header-add':[iconAdd],
})[selector]||[];}};
assert.equal(localizeWorkspaceBoard(fragment,{add:'Taak toevoegen',empty:'Geen taken gevonden.',drop:'Sleep taken hierheen'}),4);
assert.equal(add.textContent,'Taak toevoegen');
assert.equal(empty.textContent,'Geen taken gevonden.');
assert.equal(drop.textContent,'Sleep taken hierheen');
assert.equal(iconAdd.attributes['aria-label'],'Taak toevoegen');
assert.equal(iconAdd.attributes.title,'Taak toevoegen');
assert.equal(localizeWorkspaceBoard(null,{add:'Add Task'}),0);
console.log('Work M3-UX3-F2B count/runtime: PASS (singular, plural, board wording, native fallback).');
