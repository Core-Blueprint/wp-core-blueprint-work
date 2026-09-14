( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	function format( template, value ) {
		return String( template || '' ).replace( '%s', value );
	}

	function initBillingDefaults() {
		const service = document.getElementById( 'cb-work-item-service' );
		const billing = document.getElementById( 'cb-work-item-billing' );
		const config = window.cbWorkBillingUx || {};
		const services = config.services || {};

		if ( ! service || ! billing ) {
			return;
		}

		Array.from( service.options ).forEach( function ( option ) {
			const metadata = services[ String( option.value || '' ) ];
			if ( ! metadata || ! metadata.pricingLabel || option.dataset.cbPricingDecorated === '1' ) {
				return;
			}
			option.textContent = String( option.textContent || '' ).trim() + ' — ' + metadata.pricingLabel;
			option.dataset.cbPricingDecorated = '1';
		} );

		let hint = document.getElementById( 'cb-work-item-billing-hint' );
		if ( ! hint ) {
			hint = document.createElement( 'p' );
			hint.id = 'cb-work-item-billing-hint';
			hint.className = 'description';
			billing.insertAdjacentElement( 'afterend', hint );
		}

		let autoValue = '';

		function selectedMetadata() {
			return services[ String( service.value || '' ) ] || null;
		}

		function updateHint() {
			const metadata = selectedMetadata();
			if ( metadata && metadata.billing ) {
				hint.textContent = format( config.serviceDefault, metadata.billing.charAt( 0 ).toUpperCase() + metadata.billing.slice( 1 ) );
				return;
			}
			hint.textContent = config.noService || '';
		}

		function applyDefault() {
			const metadata = selectedMetadata();
			const nextValue = metadata && metadata.billing ? String( metadata.billing ) : '';

			if ( billing.value === '' || ( autoValue && billing.value === autoValue ) ) {
				billing.value = nextValue;
				autoValue = nextValue;
			}
			updateHint();
		}

		if ( billing.value === '' ) {
			applyDefault();
		} else {
			updateHint();
		}

		billing.addEventListener( 'change', function () {
			autoValue = '';
			updateHint();
		} );

		service.addEventListener( 'change', applyDefault );
	}

	ready( initBillingDefaults );
}() );
