( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	function initWorkItems() {
		const page = document.querySelector( '.cb-work-items-page' );
		if ( ! page ) {
			return;
		}

		initTableBulkActions( page );

		const form = page.querySelector( '.cb-work-items-filters' );
		const toggle = form ? form.querySelector( '.cb-work-more-filters-toggle' ) : null;
		const advanced = form ? form.querySelector( '#cb-work-more-filters' ) : null;

		if ( ! form || ! toggle || ! advanced ) {
			return;
		}

		form.querySelectorAll( '[data-cb-work-auto-submit]' ).forEach( function ( control ) {
			control.addEventListener( 'change', function () {
				form.requestSubmit();
			} );
		} );

		toggle.addEventListener( 'click', function () {
			const opening = advanced.hidden;
			advanced.hidden = ! opening;
			toggle.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );

			if ( opening ) {
				advanced.querySelector( 'input, select, button, [tabindex]:not([tabindex="-1"])' )?.focus( { preventScroll: true } );
			}
		} );

		advanced.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}

			advanced.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.focus();
		} );
	}


	function initTableBulkActions( page ) {
		const table = page.querySelector( '[data-cb-work-items-table]' );
		const form = page.querySelector( '[data-cb-work-bulk-form]' );
		const selectAll = table ? table.querySelector( '[data-cb-work-select-all]' ) : null;
		const items = table ? [ ...table.querySelectorAll( '[data-cb-work-select-item]' ) ] : [];
		const status = form ? form.querySelector( '[data-cb-work-bulk-status]' ) : null;
		const submit = form ? form.querySelector( '[data-cb-work-bulk-submit]' ) : null;
		const count = form ? form.querySelector( '[data-cb-work-selected-count]' ) : null;

		if (
			! ( table instanceof HTMLTableElement )
			|| ! ( form instanceof HTMLFormElement )
			|| ! ( selectAll instanceof HTMLInputElement )
			|| ! ( status instanceof HTMLSelectElement )
			|| ! ( submit instanceof HTMLButtonElement )
			|| items.length === 0
		) {
			return;
		}

		const sync = function () {
			const selected = items.filter( function ( item ) {
				return item instanceof HTMLInputElement && item.checked;
			} );

			items.forEach( function ( item ) {
				if ( ! ( item instanceof HTMLInputElement ) ) {
					return;
				}
				item.closest( '[data-cb-work-table-row]' )?.classList.toggle( 'is-selected', item.checked );
			} );

			selectAll.checked = selected.length === items.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < items.length;
			form.hidden = selected.length === 0;
			if ( count ) {
				count.textContent = String( selected.length );
			}
			submit.disabled = selected.length === 0 || status.value === '';
		};

		selectAll.addEventListener( 'change', function () {
			items.forEach( function ( item ) {
				if ( item instanceof HTMLInputElement ) {
					item.checked = selectAll.checked;
				}
			} );
			sync();
		} );

		items.forEach( function ( item ) {
			item.addEventListener( 'change', sync );
		} );
		status.addEventListener( 'change', sync );
		form.addEventListener( 'submit', function ( event ) {
			if ( submit.disabled ) {
				event.preventDefault();
			}
		} );

		sync();
	}

	ready( initWorkItems );
}() );
