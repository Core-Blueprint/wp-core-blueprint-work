( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	function urlWith( overrides, clearFilters ) {
		const url = new URL( window.location.href );
		const filterKeys = [ 's', 'status', 'priority', 'project_id', 'service_id', 'work_type_id', 'assignee_id', 'billing', 'customer', 'scheduled_from', 'scheduled_to', 'due_from', 'due_to', 'sort', 'paged' ];
		if ( clearFilters ) {
			filterKeys.forEach( function ( key ) { url.searchParams.delete( key ); } );
		}
		Object.keys( overrides ).forEach( function ( key ) {
			const value = overrides[ key ];
			if ( value === '' || value === null || typeof value === 'undefined' ) {
				url.searchParams.delete( key );
			} else {
				url.searchParams.set( key, String( value ) );
			}
		} );
		url.searchParams.delete( 'cb-work-notice' );
		url.searchParams.delete( 'cb-work-quick-notice' );
		return url.toString();
	}

	function link( label, href, current ) {
		const node = document.createElement( 'a' );
		node.className = 'cb-work-fast-path' + ( current ? ' is-current' : '' );
		node.href = href;
		node.textContent = label;
		if ( current ) {
			node.setAttribute( 'aria-current', 'page' );
		}
		return node;
	}

	ready( function () {
		const page = document.querySelector( '.cb-work-items-page' );
		if ( ! page ) {
			return;
		}

		const data = window.cbWorkFastPaths || {};
		const params = new URL( window.location.href ).searchParams;
		const userId = Number( data.userId || 0 );
		const today = String( data.today || '' );
		const yesterday = String( data.yesterday || '' );
		const currentStatus = params.get( 'status' ) || '';
		const currentAssignee = Number( params.get( 'assignee_id' ) || 0 );
		const currentDueTo = params.get( 'due_to' ) || '';
		const hasOtherFilters = [ 's', 'priority', 'project_id', 'service_id', 'work_type_id', 'billing', 'customer', 'scheduled_from', 'scheduled_to', 'due_from', 'sort' ].some( function ( key ) {
			return params.has( key ) && params.get( key ) !== '' && params.get( key ) !== '0' && !( key === 'sort' && params.get( key ) === 'workload' );
		} );

		const views = page.querySelector( '.nav-tab-wrapper' );
		if ( views && ! page.querySelector( '.cb-work-fast-paths' ) ) {
			const bar = document.createElement( 'nav' );
			bar.className = 'cb-work-fast-paths';
			bar.setAttribute( 'aria-label', data.focusViews || 'Focus views' );

			const allCurrent = ! hasOtherFilters && ! currentStatus && currentAssignee === 0 && ! currentDueTo;
			bar.appendChild( link( data.all || 'All', urlWith( {}, true ), allCurrent ) );
			if ( userId > 0 ) {
				bar.appendChild( link(
					data.myWork || 'My work',
					urlWith( { status: 'active', assignee_id: userId }, true ),
					! hasOtherFilters && currentStatus === 'active' && currentAssignee === userId
				) );
			}
			bar.appendChild( link(
				data.active || 'Active',
				urlWith( { status: 'active' }, true ),
				! hasOtherFilters && currentStatus === 'active' && currentAssignee === 0
			) );
			bar.appendChild( link(
				data.blocked || 'Blocked',
				urlWith( { status: 'blocked' }, true ),
				! hasOtherFilters && currentStatus === 'blocked'
			) );
			if ( yesterday ) {
				bar.appendChild( link(
					data.overdue || 'Overdue',
					urlWith( { status: 'active', due_to: yesterday }, true ),
					! hasOtherFilters && currentStatus === 'active' && currentDueTo === yesterday
				) );
			}
			views.parentNode.insertBefore( bar, views );
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

		if ( today && ! page.querySelector( '.cb-work-keyboard-hint' ) ) {
			const hint = document.createElement( 'span' );
			hint.className = 'cb-work-keyboard-hint';
			hint.textContent = ( data.keyboardHint || 'Shortcut: / search · Alt+N add Work Item' );
			const results = page.querySelector( '.cb-work-results-header' );
			if ( results ) {
				results.appendChild( hint );
			}
		}
	} );
}() );
