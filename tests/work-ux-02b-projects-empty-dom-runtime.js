'use strict';

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.resolve(__dirname, '../assets/projects-list.js'), 'utf8');
let checks = 0;

function expect(condition, label) {
    checks += 1;
    assert.ok(condition, label);
}

class Element {
    constructor(tag) {
        this.tagName = tag.toLowerCase();
        this.className = '';
        this.parentNode = null;
        this.children = [];
        this.dataset = {};
        this.textContent = '';
        this.href = '';
    }
    get nextElementSibling() {
        if (!this.parentNode) return null;
        return this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null;
    }
    matches(selector) {
        if (selector.startsWith('[data-')) {
            const key = selector.slice(6, -1).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
            return Object.prototype.hasOwnProperty.call(this.dataset, key);
        }
        const match = /^([a-z][a-z0-9]*)?(?:\.([\w-]+))?$/.exec(selector);
        return Boolean(match && (!match[1] || this.tagName === match[1])
            && (!match[2] || this.className.split(/\s+/).includes(match[2])));
    }
    querySelector(selector) {
        if (selector === '.wp-list-table tbody tr.no-items td') {
            const table = this.querySelector('table.wp-list-table');
            const row = table?.querySelector('tr.no-items');
            return row?.querySelector('td') || null;
        }
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
    insertBefore(child, ref) {
        if (child.parentNode) {
            const old = child.parentNode.children;
            old.splice(old.indexOf(child), 1);
        }
        const pos = ref ? this.children.indexOf(ref) : this.children.length;
        assert.ok(pos >= 0);
        this.children.splice(pos, 0, child);
        child.parentNode = this;
        return child;
    }
    appendChild(child) { return this.insertBefore(child, null); }
    insertAdjacentElement(position, child) {
        assert.equal(position, 'afterend');
        this.parentNode.insertBefore(child, this.nextElementSibling);
    }
    replaceChildren(...children) {
        for (const old of this.children) old.parentNode = null;
        this.children = [];
        this.textContent = '';
        for (const child of children) this.appendChild(child);
    }
}

function build({ empty = true, mode = 'first_run', configured = true, customText = null } = {}) {
    const wrap = new Element('div'); wrap.className = 'wrap';
    const title = new Element('h1'); title.className = 'wp-heading-inline'; title.textContent = 'Projects';
    const add = new Element('a'); add.className = 'page-title-action';
    add.href = '/wp-admin/post-new.php?post_type=cb_work_project';
    const end = new Element('hr'); end.className = 'wp-header-end';
    const table = new Element('table'); table.className = 'wp-list-table';
    const tbody = new Element('tbody');
    const row = new Element('tr'); row.className = empty ? 'no-items' : 'project-item';
    const cell = new Element('td'); cell.textContent = empty ? 'No projects found.' : 'Website Maintenance';
    wrap.appendChild(title); wrap.appendChild(add); wrap.appendChild(end); wrap.appendChild(table);
    table.appendChild(tbody); tbody.appendChild(row); row.appendChild(cell);

    const presets = {
        first_run: { kind: 'first_run', title: 'No projects yet.',
            description: 'Create an Internal Project for your own work, or a Customer Project for client work.',
            actionUrl: '', actionLabel: '' },
        filtered: { kind: 'filtered', title: 'No matching Projects.',
            description: 'Try another search or clear your filters.',
            actionUrl: '/wp-admin/edit.php?post_type=cb_work_project', actionLabel: 'Clear filters' },
        trash: { kind: 'trash', title: 'Trash is empty.', description: '',
            actionUrl: '/wp-admin/edit.php?post_type=cb_work_project', actionLabel: 'View all Projects' },
    };
    const config = configured ? {
        description: 'Organize internal and customer projects.',
        importUrl: '/wp-admin/admin.php?page=core-blueprint-work-project-import',
        importLabel: 'Import project',
        empty: customText || presets[mode],
    } : null;
    const document = {
        readyState: 'complete',
        querySelector: selector => selector === '.wrap' ? wrap : null,
        createElement: tag => new Element(tag),
    };
    return { wrap, cell, row, table, add, title, context: { document, window: { cbWorkProjectsList: config } } };
}

function run(scene) { vm.runInNewContext(source, scene.context, { filename: 'projects-list.js' }); }

for (const mode of ['first_run', 'filtered', 'trash']) {
    const page = build({ mode });
    run(page);
    const panel = page.cell.querySelector('[data-cb-work-projects-empty]');
    expect(Boolean(panel), mode + ': native no-items cell enhanced');
    expect(page.cell.children.length === 1, mode + ': only one replacement container');
    expect(panel.querySelector('h2')?.textContent === page.context.window.cbWorkProjectsList.empty.title,
        mode + ': accurate, localized title');
    expect(page.table.parentNode === page.wrap && page.row.parentNode?.parentNode === page.table,
        mode + ': original list table and empty row remain');
    expect(page.title.parentNode?.parentNode === page.wrap.children[0],
        mode + ': original heading remains grouped');
    expect(page.add.href.includes('post-new.php'), mode + ': native Add Project route untouched');
    expect(page.wrap.querySelectorAll('[data-cb-work-project-import-action]').length === 1,
        mode + ': optional Import action remains unique');

    const buttons = panel.querySelectorAll('a.button');
    expect(buttons.length === (mode === 'first_run' ? 0 : 1),
        mode + ': no redundant Add CTA, only applicable reset navigation');
    if (mode !== 'first_run') {
        expect(buttons[0].href === '/wp-admin/edit.php?post_type=cb_work_project',
            mode + ': canonical clear/reset URL, no retained filters');
    }
    run(page);
    expect(page.cell.querySelectorAll('[data-cb-work-projects-empty]').length === 1,
        mode + ': idempotent on repeat mount');
    expect(page.wrap.querySelectorAll('[data-cb-work-project-import-action]').length === 1,
        mode + ': Import remains idempotent');
}

// Existing Project rows are never touched, even if server supplies an empty hint.
{
    const page = build({ empty: false, mode: 'first_run' });
    run(page);
    expect(page.cell.textContent === 'Website Maintenance', 'real existing Project remains visible');
    expect(page.cell.children.length === 0, 'no empty-state node on nonempty table');
    expect(page.table.parentNode === page.wrap, 'WP table remains native');
}

// No Work config (e.g. unrelated wp-admin page): no markup mutation.
{
    const page = build({ configured: false });
    run(page);
    expect(page.cell.textContent === 'No projects found.', 'native no-items fallback without config');
    expect(page.cell.children.length === 0, 'no unauthorized Project enhancement');
}

// Malformed/unknown type does not replace WordPress's native message.
{
    const page = build({ customText: { kind: 'unknown', title: 'Oops' } });
    run(page);
    expect(page.cell.textContent === 'No projects found.', 'unknown type fails closed');
    expect(page.cell.children.length === 0, 'invalid response leaves native list functional');
}

expect(!source.includes('innerHTML'), 'no HTML injection / unsafe string rendering');
expect(!source.includes('fetch('), 'no parallel Project query or network transport');
expect(!source.includes('localStorage'), 'no persisted client-side state');
console.log(`Work UX-02B native Projects empty DOM: PASS (${checks} assertions).`);
