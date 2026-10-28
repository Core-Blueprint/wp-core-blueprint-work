'use strict';

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.resolve(__dirname, '../assets/projects-list.js'), 'utf8');
let checks = 0;
const check = (pass, label) => {
	checks += 1;
	assert.ok(pass, label);
};

class Element {
	constructor(tag) {
		this.tagName = tag.toLowerCase();
		this.className = '';
		this.children = [];
		this.parentNode = null;
		this.dataset = {};
		this.href = '';
		this.textContent = '';
	}
	get nextElementSibling() {
		if (!this.parentNode) return null;
		const siblings = this.parentNode.children;
		return siblings[siblings.indexOf(this) + 1] || null;
	}
	insertBefore(node, reference) {
		if (node.parentNode) {
			const previous = node.parentNode.children;
			previous.splice(previous.indexOf(node), 1);
		}
		const index = reference == null ? this.children.length : this.children.indexOf(reference);
		assert.ok(index >= 0, 'reference node exists within parent');
		this.children.splice(index, 0, node);
		node.parentNode = this;
		return node;
	}
	appendChild(node) {
		return this.insertBefore(node, null);
	}
	insertAdjacentElement(position, node) {
		assert.equal(position, 'afterend');
		assert.ok(this.parentNode);
		this.parentNode.insertBefore(node, this.nextElementSibling);
	}
	matches(selector) {
		if (selector === 'a.page-title-action') {
			return this.tagName === 'a' && this.className.split(/\s+/).includes('page-title-action');
		}
		if (selector.startsWith('[data-')) {
			const property = selector.slice(6, -1).replace(/-([a-z])/g, (_, char) => char.toUpperCase());
			return Object.prototype.hasOwnProperty.call(this.dataset, property);
		}
		const [tag, className] = selector.split('.');
		return this.tagName === tag && (!className || this.className.split(/\s+/).includes(className));
	}
	querySelector(selector) {
		for (const child of this.children) {
			if (child.matches(selector)) return child;
			const nested = child.querySelector(selector);
			if (nested) return nested;
		}
		return null;
	}
	querySelectorAll(selector) {
		const result = [];
		for (const child of this.children) {
			if (child.matches(selector)) result.push(child);
			result.push(...child.querySelectorAll(selector));
		}
		return result;
	}
}

function scene({ importAvailable = true, boundary = true, scriptConfig = true, loading = false } = {}) {
	const wrap = new Element('div');
	wrap.className = 'wrap';
	const heading = new Element('h1');
	heading.className = 'wp-heading-inline';
	heading.textContent = 'Projects';
	const add = new Element('a');
	add.className = 'page-title-action';
	add.href = '/wp-admin/post-new.php?post_type=cb_work_project';
	add.textContent = 'Add Project';
	const hr = new Element('hr');
	hr.className = 'wp-header-end';
	const table = new Element('table');
	table.className = 'wp-list-table';
	wrap.appendChild(heading);
	wrap.appendChild(add);
	if (boundary) wrap.appendChild(hr);
	wrap.appendChild(table);
	const listeners = new Map();
	const document = {
		readyState: loading ? 'loading' : 'complete',
		querySelector: selector => selector === '.wrap' ? wrap : null,
		createElement: tag => new Element(tag),
		addEventListener: (name, callback, options) => listeners.set(name, { callback, options }),
	};
	const config = scriptConfig ? {
		description: 'Organize internal and customer projects. Open a workspace to see its Work Items.',
		importUrl: importAvailable ? '/wp-admin/admin.php?page=core-blueprint-work-project-import' : '',
		importLabel: importAvailable ? 'Import project' : '',
	} : null;
	const context = { document, window: { cbWorkProjectsList: config } };
	return { wrap, heading, add, hr, table, listeners, context };
}

function run(state) {
	vm.runInNewContext(source, state.context, { filename: 'projects-list.js' });
}

// Existing WordPress controls are moved, never cloned or recreated.
{
	const s = scene();
	run(s);
	const header = s.wrap.querySelector('[data-cb-work-projects-header]');
	const importAction = s.wrap.querySelector('[data-cb-work-project-import-action]');
	check(Boolean(header), 'project header created');
	check(header.tagName === 'header', 'native semantic header');
	check(s.heading.parentNode?.parentNode === header, 'original h1 moved into copy');
	check(s.add.parentNode?.parentNode === header, 'original Add Project link preserved');
	check(s.add.href.includes('post-new.php'), 'original native create route preserved');
	check(importAction?.href.endsWith('core-blueprint-work-project-import'), 'optional Import route preserved');
	check(importAction?.parentNode === s.add.parentNode, 'native Add and optional Import grouped');
	check(s.wrap.children[0] === header && s.wrap.children.includes(s.hr), 'WordPress header separator remains');
	check(s.wrap.children.includes(s.table), 'native post list table retained');
	check(header.querySelector('p.description')?.textContent === s.context.window.cbWorkProjectsList.description, 'localized description shown');

	run(s);
	check(s.wrap.querySelectorAll('[data-cb-work-projects-header]').length === 1, 'header remains idempotent');
	check(s.wrap.querySelectorAll('[data-cb-work-project-import-action]').length === 1, 'no duplicate Import action');
	check(s.wrap.querySelectorAll('a.page-title-action').length === 2, 'both original-style native actions retained');
}

// Project management is not blocked when the optional Data Exchange provider is absent.
{
	const s = scene({ importAvailable: false });
	run(s);
	check(Boolean(s.wrap.querySelector('[data-cb-work-projects-header]')), 'header exists without Import provider');
	check(s.wrap.querySelectorAll('a.page-title-action').length === 1, 'only native Add button without Import');
	check(s.add.parentNode?.className === 'cb-work-projects-list__actions', 'native Add grouped without provider');
}

// Fail safe on changed WordPress DOM: Import action remains usable if provided,
// but no custom header replaces the missing native separator.
{
	const s = scene({ boundary: false });
	run(s);
	check(!s.wrap.querySelector('[data-cb-work-projects-header]'), 'unknown native markup left unchanged');
	check(Boolean(s.wrap.querySelector('[data-cb-work-project-import-action]')), 'Import fallback preserved');
	check(s.heading.parentNode === s.wrap && s.table.parentNode === s.wrap, 'native elements retain their positions');
}

// No configuration means no Work injection on unrelated wp-admin screens.
{
	const s = scene({ scriptConfig: false });
	run(s);
	check(!s.wrap.querySelector('[data-cb-work-projects-header]'), 'no header outside configured Project list');
	check(!s.wrap.querySelector('[data-cb-work-project-import-action]'), 'no Import outside configured Project list');
}

// Deferral respects WordPress document ready state; no pre-DOM mutation.
{
	const s = scene({ loading: true, importAvailable: false });
	run(s);
	check(!s.wrap.querySelector('[data-cb-work-projects-header]'), 'waits for DOM readiness');
	check(s.listeners.get('DOMContentLoaded')?.options?.once === true, 'one-shot DOM-ready listener');
	s.listeners.get('DOMContentLoaded').callback();
	check(Boolean(s.wrap.querySelector('[data-cb-work-projects-header]')), 'mounts after DOM loaded');
}

console.log(`Work UX-02A native Projects list DOM runtime: PASS (${checks} assertions).`);
