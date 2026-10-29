( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	ready( function () {
		// The Work workspace mounts canonical Work Items after DOM ready.
		// Resolve the active Work Items fragment when the shortcut is used,
		// not once at initial load. Register only one global listener.
		document.addEventListener( 'keydown', function ( event ) {
			const page = document.querySelector( '.cb-work-items-page' );
			if ( ! page || page.closest( '[data-cb-work-uwe-panel]' )?.hidden
				|| page.closest( '[data-cb-work-uwe-canonical]' )?.hidden ) return;
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey ) {
				return;
			}

			const target = event.target;
			// Never escape Base's modal focus trap or interrupt nested editable
			// content (including rich text descendants) with the / shortcut.
			const modalOpen = document.querySelector( 'dialog[open], [role="dialog"][aria-modal="true"]' );
			const editable = 'input, textarea, select, [contenteditable], [role="textbox"]';
			if ( modalOpen || target?.closest?.( editable ) || target?.matches?.( editable ) ) {
				return;
			}

			if ( event.key === '/' ) {
				const search = page.querySelector( '#cb-work-filter-search' );
				if ( search ) {
					event.preventDefault();
					search.focus();
					search.select?.();
				}
			}
		} );
	} );
}() );
