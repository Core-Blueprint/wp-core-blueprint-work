( function () {
	'use strict';

	const config = window.cbWorkTaxRateDataExchange || {};
	const actions = config.actions || {};
	const labels = config.labels || {};
	const wrapper = document.querySelector( '[data-cb-work-tax-rate-import]' );

	if ( ! wrapper || ! config.ajaxUrl || ! config.nonce ) {
		return;
	}

	let controller = null;
	let previewFingerprint = '';
	let previewMapping = '';

	function mapperRoot() {
		return wrapper.querySelector( '[data-cb-data-mapper-root]' );
	}

	function primaryButton() {
		return wrapper.querySelector( '[data-cb-data-mapper-primary]' );
	}

	function setPrimaryLabel( label ) {
		const button = primaryButton();
		if ( button ) {
			button.textContent = label;
			button.setAttribute( 'aria-label', label );
			button.setAttribute( 'title', label );
		}
	}

	function resetAppliedHistoryControls() {
		const undo = wrapper.querySelector( '[data-cb-design-shell-undo]' );
		const redo = wrapper.querySelector( '[data-cb-design-shell-redo]' );
		if ( undo instanceof HTMLButtonElement ) {
			undo.disabled = true;
		}
		if ( redo instanceof HTMLButtonElement ) {
			redo.disabled = true;
		}
	}

	function showPreview() {
		const tab = wrapper.querySelector( '[data-cb-design-shell-tab="preview"]' );
		if ( tab instanceof HTMLElement ) {
			tab.click();
		}
	}

	function mappingSignature( mapping ) {
		try {
			return JSON.stringify( Array.isArray( mapping ) ? mapping : [] );
		} catch ( error ) {
			return '';
		}
	}

	function clearPreviewState() {
		previewFingerprint = '';
		previewMapping = '';
		setPrimaryLabel( labels.validate || 'Validate import' );
	}

	function formData( action, file, mapping = null, fingerprint = '' ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		if ( file instanceof File ) {
			body.append( 'file', file, file.name );
		}
		if ( Array.isArray( mapping ) ) {
			body.append( 'mapping', JSON.stringify( mapping ) );
		}
		if ( fingerprint ) {
			body.append( 'fingerprint', fingerprint );
		}
		return body;
	}

	async function request( body ) {
		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
		let payload = null;
		try {
			payload = await response.json();
		} catch ( error ) {
			payload = null;
		}
		if ( ! payload || typeof payload !== 'object' ) {
			throw new Error( labels.requestFailed || 'The VAT import request could not be completed.' );
		}
		return payload;
	}

	function validationFrom( payload, fallback ) {
		if ( payload?.data?.validation && typeof payload.data.validation === 'object' ) {
			return payload.data.validation;
		}
		return {
			valid: false,
			message: fallback,
			errors: [ { code: 'request_failed', message: fallback } ],
		};
	}

	function attach( nextController ) {
		if ( ! nextController || controller === nextController ) {
			return;
		}
		controller = nextController;
		clearPreviewState();
	}

	wrapper.addEventListener( 'cb:data-mapper:ready', function ( event ) {
		attach( event.detail?.controller || null );
	} );

	wrapper.addEventListener( 'cb:data-mapper:change', function () {
		clearPreviewState();
	} );

	wrapper.addEventListener( 'cb:data-mapper:file-selected', async function ( event ) {
		clearPreviewState();
		const file = event.detail?.file || null;
		if ( ! controller || ! ( file instanceof File ) ) {
			return;
		}

		controller.setBusy( true, labels.inspecting || 'Inspecting CSV…' );
		try {
			const payload = await request( formData( actions.inspect, file ) );
			if ( payload.success !== true || ! Array.isArray( payload.data?.fields ) ) {
				controller.setValidation( validationFrom( payload, labels.requestFailed || 'The VAT import request could not be completed.' ) );
				showPreview();
				return;
			}
			controller.setSourceFields( payload.data.fields );
		} catch ( error ) {
			controller.setValidation( {
				valid: false,
				message: error instanceof Error ? error.message : ( labels.requestFailed || 'The VAT import request could not be completed.' ),
				errors: [ { code: 'request_failed', message: error instanceof Error ? error.message : 'Request failed.' } ],
			} );
			showPreview();
		} finally {
			controller.setBusy( false, labels.fileReady || 'CSV ready for mapping.' );
		}
	} );

	wrapper.addEventListener( 'cb:data-mapper:submit', async function ( event ) {
		event.preventDefault();
		if ( ! controller ) {
			return;
		}

		const file = event.detail?.file || controller.file();
		const mapping = Array.isArray( event.detail?.mapping ) ? event.detail.mapping : controller.mapping();
		if ( ! ( file instanceof File ) ) {
			return;
		}

		const signature = mappingSignature( mapping );
		const applying = previewFingerprint && signature === previewMapping;
		controller.setBusy( true, applying
			? ( labels.applying || 'Applying VAT import…' )
			: ( labels.validating || 'Building server preview…' )
		);

		try {
			const payload = await request(
				formData(
					applying ? actions.apply : actions.preview,
					file,
					mapping,
					applying ? previewFingerprint : ''
				)
			);
			const validation = validationFrom( payload, labels.requestFailed || 'The VAT import request could not be completed.' );
			controller.setValidation( validation );
			showPreview();

			if ( payload.success === true && ! applying && validation.valid === true && validation.fingerprint ) {
				previewFingerprint = String( validation.fingerprint );
				previewMapping = signature;
				setPrimaryLabel( labels.apply || 'Apply import' );
				return;
			}

			if ( applying ) {
				clearPreviewState();
				if ( payload.success === true ) {
					resetAppliedHistoryControls();
				}
			}
		} catch ( error ) {
			controller.setValidation( {
				valid: false,
				message: error instanceof Error ? error.message : ( labels.requestFailed || 'The VAT import request could not be completed.' ),
				errors: [ { code: 'request_failed', message: error instanceof Error ? error.message : 'Request failed.' } ],
			} );
			showPreview();
			clearPreviewState();
		} finally {
			controller.setBusy( false, '' );
		}
	} );

	function attachExisting() {
		const root = mapperRoot();
		const existing = root && window.cbCore?.dataMapper?.get
			? window.cbCore.dataMapper.get( root )
			: null;
		attach( existing );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', attachExisting, { once: true } );
	} else {
		attachExisting();
	}
}() );
