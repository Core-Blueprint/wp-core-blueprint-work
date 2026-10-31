import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Execute the actual shipped pure editor renderer under a minimal DOM facade.
const js = readFileSync(new URL('../assets/js/workspace/recurring.js', import.meta.url), 'utf8');
const { formView, readChanges, schedulePreviewFields } =
	new Function(js.replace(/^export /gm, '') + '\nreturn {formView,readChanges,schedulePreviewFields};')();

class Node {
	constructor(tag) {
		this.tagName = tag;
		this.children = [];
		this.dataset = {};
		this.attrs = {};
		this.hidden = false;
		this.disabled = false;
		this.required = false;
		this.value = '';
		this.name = '';
		this.className = '';
		this.listeners = new Map();
		this.classList = { add: (value) => { this.className += ' ' + value; } };
	}
	append(...nodes) { for (const child of nodes) { this.children.push(child); child.parentNode = this; } }
	setAttribute(name, value) { this.attrs[name] = value; }
	addEventListener(name, listener) { this.listeners.set(name, listener); }
	emit(name) { this.listeners.get(name)?.({target:this,type:name}); }
	walk() {
		const children = [];
		for (const child of this.children) { children.push(child); children.push(...child.walk()); }
		return children;
	}
	querySelectorAll(selector) {
		const nodes = this.walk();
		if (selector.includes('[name]')) {
			return nodes.filter((node) => ['input','select','textarea'].includes(node.tagName) && node.name);
		}
		return nodes.filter((node) => selector === node.tagName);
	}
	querySelector(selector) {
		const name = selector.startsWith('[name="') && selector.endsWith('"]') ? selector.slice(7, -2) : null;
		if (name) return this.walk().find((node) => node.name === name) || null;
		return this.walk().find((node) => node.tagName === selector) || null;
	}
	checkValidity() {
		if (this.disabled) return true;
		if (this.required && !String(this.value)) return false;
		if (this.type === 'number' && this.value !== '') {
			const n = Number(this.value);
			if (!Number.isFinite(n) || (this.min !== undefined && n < Number(this.min))
				|| (this.max !== undefined && n > Number(this.max))) return false;
		}
		return true;
	}
	reportValidity() { return this.checkValidity(); }
}
const doc = {createElement: (tag) => new Node(tag)};
const labels = {
	cbWorkRecurringDetails:'Work details', cbWorkRecurringSchedule:'Schedule',
	cbWorkRecurringAssignment:'Assignment', cbWorkRecurringContext:'Work context',
	cbWorkRecurringEvery:'Every', cbWorkRecurringFrequency:'Frequency',
	cbWorkRecurringUnitDaily:'dag', cbWorkRecurringUnitWeekly:'week',
	cbWorkRecurringUnitMonthly:'maand', cbWorkRecurringUnitYearly:'jaar',
	cbWorkRecurringEndDate:'End date', cbWorkRecurringNoLimit:'No date limit',
	cbWorkRecurringPreview:'Rule Preview', cbWorkRecurringUpcoming:'Upcoming occurrences',
	cbWorkRecurringAdvanced:'Advanced',
};
const panel = {dataset:labels};
const root = {
	dataset: {cbWorkM2Title:'Title',cbWorkM2DescriptionLabel:'Description'},
	querySelector: (selector) => selector === '[data-cb-work-uwe-panel="recurring"]' ? panel : null,
};
function findClass(element, name) { return [element,...element.walk()].find((n) => n.className.split(' ').includes(name)); }
function create(item = {}) {
	return formView(doc, root,
		{id:0,title:'Maintenance',priority:'normal',work_context:'internal', ...item},
		{today:'2026-10-31',projects:[],services:[],work_types:[]},false,false);
}
const draft = create();
const form = draft.form;
assert.ok(findClass(draft.body, 'cb-work-recurring__layout'), 'real layout has bounded two-column root');
assert.ok(findClass(draft.body, 'cb-work-recurring__main'), 'primary editor content');
assert.ok(findClass(draft.body, 'cb-work-recurring__aside'), 'preview is contextual');
assert.equal(form.querySelector('[name="title"]').value, 'Maintenance');
assert.equal(form.querySelector('[name="description"]').value, '');
assert.equal(form.querySelector('[name="frequency"]').value,'weekly','canonical value not localized');
const frequency = form.querySelector('[name="frequency"]');
assert.equal(frequency.children.find((node)=>node.value === 'monthly').textContent, 'maand','localized presentation does not change schema');
assert.equal(form.querySelector('[name="interval_count"]').value,'1');
const endInput = form.querySelector('[name="end_on"]');
const endLabel = findClass(form,'cb-work-recurring__end-date');
const endToggle = findClass(form,'cb-work-recurring__end-toggle').querySelector('input');
assert.equal(endToggle.name,'','UI-only checkbox never enters canonical writer');
assert.equal(endLabel.hidden,true,'optional end hidden when open-ended');
assert.equal(endInput.value,'');
assert.equal(draft.preview.getAttribute, undefined);
const preview = schedulePreviewFields(form);
assert.deepEqual(preview,{frequency:'weekly',interval_count:1,start_on:'2026-10-31',end_on:null});
const createFields = readChanges(form,null);
assert.equal(createFields.frequency,'weekly');
assert.equal(createFields.interval_count,1);
assert.equal(createFields.end_on,'');
assert.equal(Object.hasOwn(createFields,''),false);
assert.equal(Object.hasOwn(createFields,'endToggle'),false);
endToggle.checked = true;
endToggle.emit('change');
assert.equal(endLabel.hidden,false);
assert.equal(endInput.required,true);
endInput.value = '2026-12-31';
assert.equal(schedulePreviewFields(form).end_on,'2026-12-31');
endToggle.checked = false;
endToggle.emit('change');
assert.equal(endInput.value,'');
assert.equal(readChanges(form,null).end_on,'');
endToggle.checked = true;
endToggle.emit('change');
assert.equal(endInput.value,'2026-12-31','toggling end date restores unsaved date');
assert.equal(readChanges(form,null).end_on,'2026-12-31');
const original = { ...readChanges(form,null) };
assert.deepEqual(readChanges(form,original),{},'no-op editor leaves canonical values intact');
form.querySelector('[name="interval_count"]').value = '0';
assert.equal(schedulePreviewFields(form),null,'invalid frequency interval never reaches preview');
assert.equal(readChanges(form,original),null,'invalid value never reaches writer');
const locked = create({id:17, title:'Locked',frequency:'monthly',interval_count:2,
	start_on:'2026-09-01',end_on:'2026-12-01',schedule_locked:true});
for (const key of ['frequency','interval_count','start_on','end_on']) {
	assert.equal(locked.form.querySelector('[name="'+key+'"]').disabled,true, key+' locked by occurrence history');
}
assert.equal(findClass(locked.form,'cb-work-recurring__end-toggle').querySelector('input').disabled,true);
assert.equal(schedulePreviewFields(locked.form).end_on,'2026-12-01','locked editor still permits read-only preview');
const quick = formView(doc,root,{title:'Quick',priority:'high'}, {},true,false);
assert.equal(quick.form.querySelector('[name="frequency"]'),null,'Quick Edit remains minimal');
assert.deepEqual(Object.keys(readChanges(quick.form, null)).sort(), ['priority','title']);
console.log('Work M3-B4-B4 Recurring editor runtime: PASS (layout, localized cadence, end toggle, locked schedules, canonical writes, Quick Edit).');
