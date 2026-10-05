( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	function selectedProjectContext( project ) {
		if ( ! project || String( project.value || '0' ) === '0' ) {
			return '';
		}
		const option = project.options[ project.selectedIndex ];
		return option ? String( option.dataset.cbWorkContext || '' ) : '';
	}

	function initScope( context ) {
		const scope = context.closest( 'form' ) || document;
		const contextRow = context.closest( '[data-cb-work-context-row]' );
		const customerRow = scope.querySelector( '[data-cb-work-customer-row]' );
		const project = scope.querySelector( '[data-cb-work-project-select]' );
		const billing = scope.querySelector( '[data-cb-work-billing-select]' );

		function sync() {
			const inheritedContext = selectedProjectContext( project );
			const inherited = inheritedContext !== '';
			if ( inherited ) {
				context.value = inheritedContext;
			}

			context.disabled = inherited;
			if ( contextRow ) {
				contextRow.hidden = inherited;
			}
			if ( customerRow ) {
				customerRow.hidden = inherited || context.value !== 'customer';
			}

			if ( billing ) {
				if ( context.value === 'internal' ) {
					billing.value = 'non_billable';
					billing.disabled = true;
				} else {
					billing.disabled = false;
				}
			}
		}

		context.addEventListener( 'change', sync );
		if ( project ) {
			project.addEventListener( 'change', sync );
		}
		sync();
	}

	ready( function () {
		document.querySelectorAll( '[data-cb-work-context-select]' ).forEach( initScope );
	} );
}() );
