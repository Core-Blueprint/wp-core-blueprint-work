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
		const form = page ? page.querySelector( '.cb-work-items-filters' ) : null;
		const toggle = form ? form.querySelector( '.cb-work-more-filters-toggle' ) : null;
		const advanced = form ? form.querySelector( '#cb-work-more-filters' ) : null;

		if ( ! page || ! form || ! toggle || ! advanced ) {
			return;
		}

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

	ready( initWorkItems );
}() );
