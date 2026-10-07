( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	function element( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( typeof text === 'string' ) {
			node.textContent = text;
		}
		return node;
	}

	function value( control ) {
		if ( ! control ) {
			return '';
		}
		if ( control.matches && control.matches( 'input, select' ) ) {
			return String( control.value || '' ).trim();
		}
		const input = control.querySelector( 'input[data-cb-core-object-picker-input]' );
		return input ? String( input.value || '' ).trim() : '';
	}

	function picker( form, id ) {
		const input = form.querySelector( '#' + id );
		return input ? input.closest( '.cb-core-object-picker' ) : null;
	}

	function statusFromLane( lane, strings ) {
		const heading = lane.querySelector( '.hndle' );
		if ( ! heading ) {
			return '';
		}
		const label = String( heading.textContent || '' )
			.replace( /\s*\(\d+\)\s*$/, '' )
			.trim()
			.toLocaleLowerCase();
		const labels = {
			planned: strings.planned || 'Planned',
			in_progress: strings.inProgress || 'In Progress',
			blocked: strings.blocked || 'Blocked',
			completed: strings.completed || 'Completed',
			skipped: strings.skipped || 'Skipped',
			cancelled: strings.cancelled || 'Cancelled',
		};
		return Object.keys( labels ).find( function ( status ) {
			return String( labels[ status ] ).trim().toLocaleLowerCase() === label;
		} ) || '';
	}

	function laneCount( lane ) {
		const heading = lane.querySelector( '.hndle' );
		const match = heading ? String( heading.textContent || '' ).match( /\((\d+)\)\s*$/ ) : null;
		return match ? Number.parseInt( match[ 1 ], 10 ) || 0 : 0;
	}

	function emptyState( page, hasFilters, strings ) {
		const empty = element( 'div', 'cb-work-empty-state cb-work-empty-state--primary' );
		empty.appendChild( element(
			'h3',
			'',
			hasFilters ? ( strings.noMatchingItems || 'No Work Items match these filters.' ) : ( strings.noItemsYet || 'No Work Items yet.' )
		) );
		empty.appendChild( element(
			'p',
			'description',
			hasFilters ? ( strings.noMatchingDetail || 'Adjust or clear the current filters to broaden this view.' ) : ( strings.noItemsYetDetail || 'Create your first Work Item to start planning and tracking customer work.' )
		) );
		const source = hasFilters
			? page.querySelector( '.cb-work-clear-filters' )
			: page.querySelector( '.cb-work-page-header__primary' );
		if ( source ) {
			const action = source.cloneNode( true );
			action.className = hasFilters ? 'button' : 'button button-primary';
			empty.appendChild( action );
		}
		return empty;
	}

	function refineBoard( page, form, strings ) {
		const currentView = form.querySelector( 'input[name="view"]' )?.value || 'table';
		if ( currentView !== 'kanban' ) {
			return;
		}
		const board = page.querySelector( '.cb-work-items-kanban' );
		if ( ! board ) {
			return;
		}

		const hasFilters = Boolean( page.querySelector( '.cb-work-filter-summary' ) );
		if ( board.classList.contains( 'cb-work-board--empty' ) ) {
			board.replaceWith( emptyState( page, hasFilters, strings ) );
			return;
		}

		const lanes = Array.from( board.children ).filter( function ( lane ) {
			return lane.tagName === 'SECTION';
		} );
		const byStatus = {};
		lanes.forEach( function ( lane ) {
			const status = statusFromLane( lane, strings );
			if ( status ) {
				lane.dataset.cbWorkStatus = status;
				lane.classList.add( 'cb-work-board__lane' );
				byStatus[ status ] = lane;
			}
		} );

		[ 'planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled' ].forEach( function ( status ) {
			if ( byStatus[ status ] ) {
				board.appendChild( byStatus[ status ] );
			}
		} );

		const closed = [ byStatus.skipped, byStatus.cancelled ].filter( Boolean );
		const selectedStatus = value( form.querySelector( 'select[name="status"]' ) );
		let showClosed = selectedStatus === 'skipped' || selectedStatus === 'cancelled';
		const closedCount = closed.reduce( function ( total, lane ) {
			return total + laneCount( lane );
		}, 0 );

		function refresh() {
			closed.forEach( function ( lane ) {
				lane.hidden = ! showClosed;
			} );
			const visible = Array.from( board.children ).filter( function ( lane ) {
				return lane.tagName === 'SECTION' && ! lane.hidden;
			} ).length;
			board.style.gridTemplateColumns = 'repeat(' + Math.max( 1, visible ) + ', minmax(240px, 1fr))';
		}
		refresh();

		if ( closedCount <= 0 || showClosed ) {
			return;
		}
		const resultsHeader = page.querySelector( '.cb-work-results-header' );
		if ( ! resultsHeader || resultsHeader.querySelector( '.cb-work-board-closed-toggle' ) ) {
			return;
		}
		const toggle = element( 'button', 'button button-small cb-work-board-closed-toggle', ( strings.showClosed || 'Show closed' ) + ' (' + closedCount + ')' );
		toggle.type = 'button';
		toggle.setAttribute( 'aria-pressed', 'false' );
		toggle.addEventListener( 'click', function () {
			showClosed = ! showClosed;
			toggle.setAttribute( 'aria-pressed', showClosed ? 'true' : 'false' );
			toggle.textContent = showClosed
				? ( strings.hideClosed || 'Hide closed' )
				: ( strings.showClosed || 'Show closed' ) + ' (' + closedCount + ')';
			refresh();
		} );
		resultsHeader.appendChild( toggle );
	}

	function refineCalendar( page, form, strings ) {
		const currentView = form.querySelector( 'input[name="view"]' )?.value || 'table';
		if ( currentView !== 'calendar' ) {
			return;
		}
		const calendar = page.querySelector( '.cb-work-items-calendar' );
		const navigation = page.querySelector( '.cb-work-calendar-navigation' );
		if ( ! calendar || ! navigation || calendar.querySelector( '.card' ) || page.querySelector( '.cb-work-calendar-empty-note' ) ) {
			return;
		}
		const note = element( 'p', 'description cb-work-calendar-empty-note', strings.noScheduledThisMonth || 'No scheduled work or deadlines this month.' );
		navigation.insertAdjacentElement( 'afterend', note );
	}

	function init() {
		const page = document.querySelector( '.cb-work-items-page' );
		const form = page ? page.querySelector( '.cb-work-items-filters' ) : null;
		if ( ! page || ! form ) {
			return;
		}
		const strings = window.cbWorkAdminUx || {};
		refineBoard( page, form, strings );
		refineCalendar( page, form, strings );
	}

	ready( init );
}() );
