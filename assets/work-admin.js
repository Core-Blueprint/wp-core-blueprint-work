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

	function pickerFor( id ) {
		const input = document.getElementById( id );
		return input ? input.closest( '.cb-core-object-picker' ) : null;
	}

	function controlValue( control ) {
		if ( ! control ) {
			return '';
		}
		if ( control.matches && control.matches( 'input, select' ) ) {
			return String( control.value || '' ).trim();
		}
		const input = control.querySelector( 'input[data-cb-core-object-picker-input]' );
		return input ? String( input.value || '' ).trim() : '';
	}

	function selectedText( control ) {
		if ( ! control || ! control.matches || ! control.matches( 'select' ) ) {
			return '';
		}
		return control.selectedOptions && control.selectedOptions[ 0 ]
			? String( control.selectedOptions[ 0 ].textContent || '' ).trim()
			: '';
	}

	function pickerSelectedText( control ) {
		if ( ! control ) {
			return '';
		}
		return Array.from( control.querySelectorAll( '.cb-core-object-picker__chip-label' ) )
			.map( function ( node ) {
				return String( node.textContent || '' ).trim();
			} )
			.filter( Boolean )
			.join( ', ' );
	}

	function ensureId( control, id ) {
		if ( control && ! control.id ) {
			control.id = id;
		}
		return control;
	}

	function field( labelText, control, modifier ) {
		if ( ! control ) {
			return null;
		}

		const wrapper = element( 'div', 'cb-work-filter-field' + ( modifier ? ' ' + modifier : '' ) );
		const input = control.matches && control.matches( 'input, select' )
			? control
			: ( control.querySelector( '[data-cb-core-object-picker-search]' ) || control.querySelector( 'input[data-cb-core-object-picker-input]' ) );
		const label = element( 'label', 'cb-work-filter-label', labelText );
		if ( input && input.id ) {
			label.htmlFor = input.id;
		}
		wrapper.appendChild( label );
		wrapper.appendChild( control );
		return wrapper;
	}

	function rangeField( labelText, from, to, toText ) {
		if ( ! from || ! to ) {
			return null;
		}

		const wrapper = element( 'div', 'cb-work-filter-field cb-work-filter-field--range' );
		wrapper.appendChild( element( 'span', 'cb-work-filter-label', labelText ) );

		const controls = element( 'div', 'cb-work-filter-range' );
		controls.appendChild( from );
		controls.appendChild( element( 'span', 'cb-work-filter-range__separator', toText ) );
		controls.appendChild( to );
		wrapper.appendChild( controls );
		return wrapper;
	}

	function rangeSummary( from, to, separator ) {
		const fromValue = controlValue( from );
		const toValue = controlValue( to );
		if ( ! fromValue && ! toValue ) {
			return '';
		}
		return ( fromValue || '…' ) + ' ' + separator + ' ' + ( toValue || '…' );
	}

	function appendSummaryChip( container, label, value ) {
		if ( ! value ) {
			return;
		}
		container.appendChild( element( 'span', 'cb-work-filter-chip', label + ': ' + value ) );
	}


	function initWorkItems() {
		const page = document.querySelector( '.cb-work-items-page' );
		const form = page ? page.querySelector( '.cb-work-items-filters' ) : null;
		if ( ! page || ! form ) {
			return;
		}

		const strings = window.cbWorkAdminUx || {};
		const currentView = form.querySelector( 'input[name="view"]' )?.value || 'table';
		const legacyChildren = Array.from( form.children );

		const controls = {
			search: ensureId( form.querySelector( '#cb-work-filter-search' ), 'cb-work-filter-search' ),
			status: form.querySelector( 'select[name="status"]' ),
			priority: ensureId( form.querySelector( 'select[name="priority"]' ), 'cb-work-filter-priority' ),
			project: form.querySelector( 'select[name="project_id"]' ),
			service: form.querySelector( 'select[name="service_id"]' ),
			workType: ensureId( form.querySelector( 'select[name="work_type_id"]' ), 'cb-work-filter-work-type' ),
			workContext: ensureId( form.querySelector( 'select[name="work_context"]' ), 'cb-work-filter-work-context' ),
			billing: ensureId( form.querySelector( 'select[name="billing"]' ), 'cb-work-filter-billing' ),
			customer: pickerFor( 'cb-work-filter-customer' ),
			assignee: pickerFor( 'cb-work-filter-assignee' ),
			scheduledFrom: form.querySelector( '#cb-work-filter-scheduled-from' ),
			scheduledTo: form.querySelector( '#cb-work-filter-scheduled-to' ),
			dueFrom: form.querySelector( '#cb-work-filter-due-from' ),
			dueTo: form.querySelector( '#cb-work-filter-due-to' ),
			sort: ensureId( form.querySelector( '#cb-work-filter-sort' ), 'cb-work-filter-sort' ),
			submit: form.querySelector( 'button[type="submit"]' ),
			clear: form.querySelector( 'a.button' ),
		};

		if ( ! controls.status || ! controls.project || ! controls.service || ! controls.submit ) {
			return;
		}

		const advancedValues = [
			controlValue( controls.priority ),
			controlValue( controls.workType ) !== '0' ? controlValue( controls.workType ) : '',
			controlValue( controls.workContext ),
			controlValue( controls.billing ),
			controlValue( controls.customer ),
			controlValue( controls.assignee ),
			controlValue( controls.scheduledFrom ),
			controlValue( controls.scheduledTo ),
			controlValue( controls.dueFrom ),
			controlValue( controls.dueTo ),
			controlValue( controls.sort ) !== 'workload' ? controlValue( controls.sort ) : '',
		].filter( Boolean );
		const advancedCount = advancedValues.length;
		const hasPrimaryFilters = Boolean(
			controlValue( controls.status ) ||
			( controlValue( controls.project ) && controlValue( controls.project ) !== '0' ) ||
			( controlValue( controls.service ) && controlValue( controls.service ) !== '0' ) ||
			controlValue( controls.search )
		);
		const hasAnyFilters = hasPrimaryFilters || advancedCount > 0;

		const toolbar = element( 'div', 'cb-work-toolbar' );
		const row = element( 'div', 'cb-work-toolbar__row' );
		const primary = element( 'div', 'cb-work-toolbar__primary' );
		const search = element( 'div', 'cb-work-toolbar__search' );
		const advanced = element( 'div', 'cb-work-toolbar__advanced' );
		advanced.id = 'cb-work-more-filters';

		primary.appendChild( controls.status );
		primary.appendChild( controls.project );
		primary.appendChild( controls.service );
		controls.submit.textContent = strings.filter || 'Filter';
		controls.submit.className = 'button';
		primary.appendChild( controls.submit );

		const moreButton = element(
			'button',
			'button cb-work-more-filters-toggle',
			advancedCount
				? ( strings.lessFilters || 'Hide filters' )
				: ( strings.moreFilters || 'More filters' )
		);
		moreButton.type = 'button';
		moreButton.setAttribute( 'aria-controls', advanced.id );
		moreButton.setAttribute( 'aria-expanded', advancedCount ? 'true' : 'false' );
		primary.appendChild( moreButton );

		if ( hasAnyFilters && controls.clear ) {
			controls.clear.className = 'cb-work-clear-filters';
			primary.appendChild( controls.clear );
		}

		if ( controls.search ) {
			search.appendChild( controls.search );
			const searchButton = element( 'button', 'button', strings.search || 'Search' );
			searchButton.type = 'submit';
			search.appendChild( searchButton );
		}

		row.appendChild( primary );
		row.appendChild( search );
		toolbar.appendChild( row );

		if ( hasAnyFilters ) {
			const summary = element( 'div', 'cb-work-filter-summary' );
			summary.setAttribute( 'aria-label', strings.activeFilters || 'Active filters' );
			summary.appendChild( element( 'span', 'cb-work-filter-summary__label', strings.activeFilters || 'Active filters' ) );
			appendSummaryChip( summary, strings.status || 'Status', controlValue( controls.status ) ? selectedText( controls.status ) : '' );
			appendSummaryChip( summary, strings.project || 'Project', controlValue( controls.project ) !== '0' ? selectedText( controls.project ) : '' );
			appendSummaryChip( summary, strings.service || 'Service', controlValue( controls.service ) !== '0' ? selectedText( controls.service ) : '' );
			appendSummaryChip( summary, strings.search || 'Search', controlValue( controls.search ) );
			appendSummaryChip( summary, strings.priority || 'Priority', controlValue( controls.priority ) ? selectedText( controls.priority ) : '' );
			appendSummaryChip( summary, strings.workType || 'Work Type', controlValue( controls.workType ) !== '0' ? selectedText( controls.workType ) : '' );
			appendSummaryChip( summary, strings.workContext || 'Work context', controlValue( controls.workContext ) ? selectedText( controls.workContext ) : '' );
			appendSummaryChip( summary, strings.billing || 'Billing', controlValue( controls.billing ) ? selectedText( controls.billing ) : '' );
			appendSummaryChip( summary, strings.customer || 'Customer', pickerSelectedText( controls.customer ) || ( controlValue( controls.customer ) ? ( strings.selected || 'Selected' ) : '' ) );
			appendSummaryChip( summary, strings.assignee || 'Assignee', pickerSelectedText( controls.assignee ) || ( controlValue( controls.assignee ) ? ( strings.selected || 'Selected' ) : '' ) );
			appendSummaryChip( summary, strings.scheduled || 'Scheduled', currentView === 'calendar' ? '' : rangeSummary( controls.scheduledFrom, controls.scheduledTo, strings.to || 'to' ) );
			appendSummaryChip( summary, strings.due || 'Due', rangeSummary( controls.dueFrom, controls.dueTo, strings.to || 'to' ) );
			appendSummaryChip( summary, strings.sort || 'Sort', controlValue( controls.sort ) !== 'workload' ? selectedText( controls.sort ) : '' );
			toolbar.appendChild( summary );
		}

		const grid = element( 'div', 'cb-work-toolbar__advanced-grid' );
		[
			field( strings.customer || 'Customer', controls.customer, 'cb-work-filter-field--wide' ),
			field( strings.assignee || 'Assignee', controls.assignee, 'cb-work-filter-field--wide' ),
			field( strings.priority || 'Priority', controls.priority ),
			field( strings.workType || 'Work Type', controls.workType ),
			field( strings.workContext || 'Work context', controls.workContext ),
			field( strings.billing || 'Billing', controls.billing ),
			field( strings.sort || 'Sort', controls.sort ),
			currentView === 'calendar' ? null : rangeField( strings.scheduled || 'Scheduled', controls.scheduledFrom, controls.scheduledTo, strings.to || 'to' ),
			rangeField( strings.due || 'Due', controls.dueFrom, controls.dueTo, strings.to || 'to' ),
		].forEach( function ( node ) {
			if ( node ) {
				grid.appendChild( node );
			}
		} );
		advanced.appendChild( grid );
		advanced.hidden = advancedCount === 0;
		toolbar.appendChild( advanced );
		form.appendChild( toolbar );

		legacyChildren.forEach( function ( child ) {
			if ( child.parentElement === form && ! child.matches( 'input[type="hidden"]' ) && child !== toolbar ) {
				child.remove();
			}
		} );

		moreButton.addEventListener( 'click', function () {
			const opening = advanced.hidden;
			advanced.hidden = ! opening;
			moreButton.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
			moreButton.textContent = opening
				? ( strings.lessFilters || 'Hide filters' )
				: ( strings.moreFilters || 'More filters' ) + ( advancedCount ? ' (' + advancedCount + ')' : '' );
			if ( opening ) {
				advanced.querySelector( 'input, select' )?.focus( { preventScroll: true } );
			}
		} );

		const boardTab = Array.from( page.querySelectorAll( '.nav-tab' ) ).find( function ( link ) {
			return link.href.indexOf( 'view=kanban' ) !== -1;
		} );
		if ( boardTab ) {
			boardTab.textContent = strings.board || 'Board';
		}

		const resultHeading = Array.from( page.children ).find( function ( child ) {
			return child.tagName === 'H2' && ! child.classList.contains( 'nav-tab-wrapper' );
		} );
		const resultCount = resultHeading && resultHeading.nextElementSibling?.classList.contains( 'description' )
			? resultHeading.nextElementSibling
			: null;
		if ( resultHeading && resultCount ) {
			const header = element( 'div', 'cb-work-results-header' );
			resultHeading.parentNode.insertBefore( header, resultHeading );
			header.appendChild( resultHeading );
			header.appendChild( resultCount );
		}


		const emptyParagraph = Array.from( page.children ).find( function ( child ) {
			return child.tagName === 'P' && ! child.classList.contains( 'description' ) && child.textContent.trim() !== '';
		} );
		if ( ! emptyParagraph || currentView === 'calendar' ) {
			return;
		}

		if ( currentView === 'kanban' ) {
			const board = element( 'div', 'cb-work-items-kanban cb-work-board cb-work-board--empty' );
			[
				strings.planned || 'Planned',
				strings.inProgress || 'In Progress',
				strings.completed || 'Completed',
				strings.skipped || 'Skipped',
				strings.cancelled || 'Cancelled',
			].forEach( function ( label ) {
				const lane = element( 'section', 'postbox cb-work-board__lane' );
				const heading = element( 'h2', 'hndle' );
				heading.appendChild( element( 'span', '', label + ' (0)' ) );
				lane.appendChild( heading );
				const inside = element( 'div', 'inside' );
				inside.appendChild( element( 'p', 'description', strings.emptyLane || 'No Work Items' ) );
				lane.appendChild( inside );
				board.appendChild( lane );
			} );
			emptyParagraph.replaceWith( board );
			return;
		}

		const empty = element( 'div', 'cb-work-empty-state' );
		empty.appendChild( element( 'h3', '', hasAnyFilters ? ( strings.noMatchingItems || 'No Work Items match these filters.' ) : ( strings.noItemsYet || 'No Work Items yet.' ) ) );
		empty.appendChild( element( 'p', 'description', hasAnyFilters ? ( strings.noMatchingDetail || 'Adjust or clear the current filters to broaden this view.' ) : ( strings.noItemsYetDetail || 'Create your first Work Item to start planning and tracking customer work.' ) ) );

		const action = hasAnyFilters && controls.clear
			? controls.clear.cloneNode( true )
			: page.querySelector( '.page-title-action' )?.cloneNode( true );
		if ( action ) {
			action.className = hasAnyFilters ? 'button' : 'button button-primary';
			empty.appendChild( action );
		}
		emptyParagraph.replaceWith( empty );
	}

	ready( initWorkItems );
}() );
