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
		const panel = document.querySelector( '[data-cb-work-quick-add-panel]' );
		if ( ! panel ) {
			return;
		}

		const title = panel.querySelector( '#cb-work-quick-title' );
		const project = panel.querySelector( '#cb-work-quick-project' );
		const status = panel.querySelector( '[data-cb-work-quick-status]' );
		const statusContext = panel.querySelector( '[data-cb-work-quick-status-context]' );
		const statusLabel = panel.querySelector( '[data-cb-work-quick-status-label]' );
		const fullEditor = panel.querySelector( '[data-cb-work-quick-add-full]' );
		const defaultFullEditorUrl = fullEditor ? fullEditor.href : '';
		let trigger = null;

		function focusable() {
			return Array.from( panel.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ) )
				.filter( function ( node ) { return ! node.hidden && node.offsetParent !== null; } );
		}

		function fullEditorUrl( sourceUrl ) {
			try {
				const url = new URL( sourceUrl || defaultFullEditorUrl, window.location.href );
				if ( url.searchParams.get( 'post_type' ) === 'cb_work_item' ) {
					return url.toString();
				}
			} catch ( error ) {
				// Keep canonical server-rendered fallback URL.
			}
			return defaultFullEditorUrl;
		}

		function open( source ) {
			trigger = source || document.activeElement;
			const sourceUrl = source && source.href ? source.href : defaultFullEditorUrl;
			try {
				const url = new URL( sourceUrl, window.location.href );
				const projectId = url.searchParams.get( 'project_id' ) || '0';
				const requestedStatus = url.searchParams.get( 'cb_work_status' ) || '';
				const allowedStatuses = [ 'planned', 'in_progress', 'blocked' ];
				const laneStatus = allowedStatuses.includes( requestedStatus ) ? requestedStatus : '';
				if ( status ) {
					status.value = laneStatus;
				}
				if ( statusContext ) {
					statusContext.hidden = laneStatus === '';
				}
				if ( statusLabel ) {
					statusLabel.textContent = laneStatus && source
						? String( source.dataset.cbWorkQuickStatusLabel || laneStatus.replace( /_/g, ' ' ) )
						: '';
				}
				if ( project && Array.from( project.options ).some( function ( option ) { return option.value === projectId; } ) ) {
					project.value = projectId;
				} else if ( project ) {
					project.value = '0';
				}
			} catch ( error ) {
				if ( project ) {
					project.value = '0';
				}
				if ( status ) {
					status.value = '';
				}
				if ( statusContext ) {
					statusContext.hidden = true;
				}
				if ( statusLabel ) {
					statusLabel.textContent = '';
				}
			}
			if ( fullEditor ) {
				fullEditor.href = fullEditorUrl( sourceUrl );
			}
			panel.hidden = false;
			document.body.classList.add( 'cb-work-quick-add-open' );
			window.setTimeout( function () {
				if ( title ) {
					title.focus();
				}
			}, 0 );
		}

		function close() {
			panel.hidden = true;
			document.body.classList.remove( 'cb-work-quick-add-open' );
			if ( trigger && typeof trigger.focus === 'function' ) {
				trigger.focus();
			}
		}

		document.addEventListener( 'click', function ( event ) {
			const closeButton = event.target.closest( '[data-cb-work-quick-add-close]' );
			if ( closeButton ) {
				event.preventDefault();
				close();
				return;
			}

			const link = event.target.closest( 'a[href*="post-new.php"][href*="post_type=cb_work_item"]' );
			if ( ! link || link.matches( '[data-cb-work-quick-add-full]' ) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			event.preventDefault();
			open( link );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! panel.hidden && event.key === 'Tab' ) {
				const nodes = focusable();
				if ( nodes.length > 0 ) {
					const first = nodes[ 0 ];
					const last = nodes[ nodes.length - 1 ];
					if ( event.shiftKey && document.activeElement === first ) {
						event.preventDefault();
						last.focus();
					} else if ( ! event.shiftKey && document.activeElement === last ) {
						event.preventDefault();
						first.focus();
					}
				}
				return;
			}
			if ( event.key === 'Escape' && ! panel.hidden ) {
				event.preventDefault();
				close();
				return;
			}
			if ( event.altKey && ! event.ctrlKey && ! event.metaKey && event.key.toLowerCase() === 'n' ) {
				event.preventDefault();
				open( null );
			}
		} );
	} );
}() );
