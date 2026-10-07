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
		const page = document.querySelector( '.cb-work-items-page' );
		if ( ! page ) {
			return;
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey ) {
				return;
			}

			const target = event.target;
			if ( target && target.matches && target.matches( 'input, textarea, select, [contenteditable="true"]' ) ) {
				return;
			}

			if ( event.key === '/' ) {
				const search = document.getElementById( 'cb-work-filter-search' );
				if ( search ) {
					event.preventDefault();
					search.focus();
					search.select?.();
				}
			}
		} );
	} );
}() );
