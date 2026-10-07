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
		if ( ! page ) {
			return;
		}

		initTableBulkActions( page );
		initQuickEdit( page );
		initRowActionMenus( page );
		initListDisplay( page );

		const form = page.querySelector( '.cb-work-items-filters' );
		const toggle = form ? form.querySelector( '.cb-work-more-filters-toggle' ) : null;
		const advanced = form ? form.querySelector( '#cb-work-more-filters' ) : null;

		if ( ! form || ! toggle || ! advanced ) {
			return;
		}

		form.querySelectorAll( '[data-cb-work-auto-submit]' ).forEach( function ( control ) {
			control.addEventListener( 'change', function () {
				form.requestSubmit();
			} );
		} );

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


	function initTableBulkActions( page ) {
		const table = page.querySelector( '[data-cb-work-items-table]' );
		const form = page.querySelector( '[data-cb-work-bulk-form]' );
		const selectAll = table ? table.querySelector( '[data-cb-work-select-all]' ) : null;
		const items = table ? [ ...table.querySelectorAll( '[data-cb-work-select-item]' ) ] : [];
		const status = form ? form.querySelector( '[data-cb-work-bulk-status]' ) : null;
		const submit = form ? form.querySelector( '[data-cb-work-bulk-submit]' ) : null;
		const count = form ? form.querySelector( '[data-cb-work-selected-count]' ) : null;
		const editToggle = form ? form.querySelector( '[data-cb-work-bulk-edit-toggle]' ) : null;
		const editForm = page.querySelector( '[data-cb-work-bulk-edit-form]' );
		const editSubmit = editForm ? editForm.querySelector( '[data-cb-work-bulk-edit-submit]' ) : null;
		const editCount = editForm ? editForm.querySelector( '[data-cb-work-bulk-edit-count]' ) : null;
		const editIds = editForm ? [ ...editForm.querySelectorAll( '[data-cb-work-bulk-edit-id]' ) ] : [];
		const editControls = editForm ? [ ...editForm.querySelectorAll( '[data-cb-work-bulk-edit-control]' ) ] : [];
		const dueToggle = editForm ? editForm.querySelector( 'input[name="apply_due"]' ) : null;
		const dueInput = editForm ? editForm.querySelector( '[data-cb-work-bulk-due]' ) : null;
		const assigneesToggle = editForm ? editForm.querySelector( '[data-cb-work-bulk-assignees-toggle]' ) : null;
		const assigneesState = editForm ? editForm.querySelector( '[data-cb-work-bulk-assignees-state]' ) : null;

		if (
			! ( table instanceof HTMLTableElement )
			|| ! ( form instanceof HTMLFormElement )
			|| ! ( selectAll instanceof HTMLInputElement )
			|| ! ( status instanceof HTMLSelectElement )
			|| ! ( submit instanceof HTMLButtonElement )
			|| items.length === 0
		) {
			return;
		}

		const sync = function () {
			const selected = items.filter( function ( item ) {
				return item instanceof HTMLInputElement && item.checked;
			} );

			items.forEach( function ( item ) {
				if ( ! ( item instanceof HTMLInputElement ) ) {
					return;
				}
				item.closest( '[data-cb-work-table-row]' )?.classList.toggle( 'is-selected', item.checked );
			} );

			selectAll.checked = selected.length === items.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < items.length;
			form.hidden = selected.length === 0;
			if ( count ) {
				count.textContent = String( selected.length );
			}
			if ( editCount ) {
				editCount.textContent = String( selected.length );
			}
			const selectedIds = new Set( selected.map( function ( item ) { return item.value; } ) );
			editIds.forEach( function ( input ) {
				if ( input instanceof HTMLInputElement ) {
					input.disabled = ! selectedIds.has( input.value );
				}
			} );
			if ( selected.length === 0 && editForm ) {
				editForm.hidden = true;
			}
			submit.disabled = selected.length === 0 || status.value === '';
		};

		selectAll.addEventListener( 'change', function () {
			items.forEach( function ( item ) {
				if ( item instanceof HTMLInputElement ) {
					item.checked = selectAll.checked;
				}
			} );
			sync();
		} );

		items.forEach( function ( item ) {
			item.addEventListener( 'change', sync );
		} );
		status.addEventListener( 'change', sync );
		form.addEventListener( 'submit', function ( event ) {
			if ( submit.disabled ) {
				event.preventDefault();
			}
		} );

		const syncOptionalBulkFields = function () {
			if ( dueToggle instanceof HTMLInputElement && dueInput instanceof HTMLInputElement ) {
				dueInput.disabled = ! dueToggle.checked;
			}
			if ( assigneesToggle instanceof HTMLInputElement && assigneesState instanceof HTMLElement ) {
				const enabled = assigneesToggle.checked;
				assigneesState.toggleAttribute( 'inert', ! enabled );
				assigneesState.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
				assigneesState.classList.toggle( 'is-disabled', ! enabled );
			}
		};

		const syncEditSubmit = function () {
			if ( ! ( editSubmit instanceof HTMLButtonElement ) || ! editForm ) {
				return;
			}
			syncOptionalBulkFields();
			const hasChange = editControls.some( function ( control ) {
				if ( control instanceof HTMLInputElement && control.type === 'checkbox' ) {
					return control.checked;
				}
				if ( control instanceof HTMLSelectElement ) {
					return control.value !== '__keep';
				}
				return false;
			} );
			editSubmit.disabled = ! hasChange;
		};

		editToggle?.addEventListener( 'click', function () {
			if ( editForm ) {
				editForm.hidden = false;
				syncEditSubmit();
				editForm.querySelector( 'select, input:not([type="hidden"]), button' )?.focus( { preventScroll: true } );
			}
		} );
		editForm?.querySelectorAll( '[data-cb-work-bulk-edit-cancel]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				editForm.hidden = true;
			} );
		} );
		editControls.forEach( function ( control ) {
			control.addEventListener( 'change', syncEditSubmit );
		} );
		editForm?.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				editForm.hidden = true;
			}
		} );

		sync();
		syncEditSubmit();
	}


	function initQuickEdit( page ) {
		const rows = [ ...page.querySelectorAll( '[data-cb-work-quick-edit-row]' ) ];
		const closeAll = function ( exceptId = '' ) {
			rows.forEach( function ( row ) {
				if ( row.dataset.cbWorkQuickEditRow !== exceptId ) {
					row.hidden = true;
				}
			} );
		};

		page.querySelectorAll( '[data-cb-work-quick-edit-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				const id = String( toggle.dataset.cbWorkQuickEditToggle || '' );
				const row = rows.find( function ( candidate ) {
					return candidate.dataset.cbWorkQuickEditRow === id;
				} );
				if ( ! row ) {
					return;
				}
				const opening = row.hidden;
				closeAll( opening ? id : '' );
				row.hidden = ! opening;
				toggle.closest( 'details' )?.removeAttribute( 'open' );
				if ( opening ) {
					row.querySelector( 'input:not([type="hidden"]), select, button' )?.focus( { preventScroll: true } );
				}
			} );
		} );

		rows.forEach( function ( row ) {
			row.querySelectorAll( '[data-cb-work-quick-edit-cancel]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					row.hidden = true;
				} );
			} );
			row.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Escape' ) {
					row.hidden = true;
				}
			} );
		} );
	}


	function initListDisplay( page ) {
		const root = page.querySelector( '[data-cb-work-list-display]' );
		if ( ! root ) {
			return;
		}

		const toggle = root.querySelector( '[data-cb-work-list-display-toggle]' );
		const panel = root.querySelector( '[data-cb-work-list-display-panel]' );
		const groupButtons = [ ...root.querySelectorAll( '[data-cb-work-list-group]' ) ];
		const orderButtons = [ ...root.querySelectorAll( '[data-cb-work-list-project-order]' ) ];
		const orderSection = root.querySelector( '[data-cb-work-list-project-order-section]' );

		if ( ! ( toggle instanceof HTMLButtonElement ) || ! ( panel instanceof HTMLElement ) ) {
			return;
		}

		const selectedValue = function ( buttons, key ) {
			const selected = buttons.find( function ( button ) {
				return button.getAttribute( 'aria-checked' ) === 'true';
			} );
			return selected instanceof HTMLElement ? String( selected.dataset[ key ] || '' ) : '';
		};

		const readPolicy = function () {
			return {
				group_by: selectedValue( groupButtons, 'cbWorkListGroup' ) || 'project',
				project_order: selectedValue( orderButtons, 'cbWorkListProjectOrder' ) || 'asc',
			};
		};

		let currentPolicy = readPolicy();

		const selectButton = function ( buttons, selected ) {
			buttons.forEach( function ( button ) {
				button.setAttribute( 'aria-checked', button === selected ? 'true' : 'false' );
			} );
		};

		const sync = function () {
			if ( orderSection instanceof HTMLElement ) {
				orderSection.hidden = readPolicy().group_by !== 'project';
			}
		};

		const restore = function () {
			groupButtons.forEach( function ( button ) {
				button.setAttribute( 'aria-checked', String( button.dataset.cbWorkListGroup || '' ) === currentPolicy.group_by ? 'true' : 'false' );
			} );
			orderButtons.forEach( function ( button ) {
				button.setAttribute( 'aria-checked', String( button.dataset.cbWorkListProjectOrder || '' ) === currentPolicy.project_order ? 'true' : 'false' );
			} );
			sync();
		};

		const persist = async function () {
			const body = new FormData();
			body.set( 'action', root.dataset.action || '' );
			body.set( 'nonce', root.dataset.nonce || '' );
			body.set( 'policy', JSON.stringify( readPolicy() ) );

			const response = await fetch( root.dataset.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body,
			} );
			const payload = await response.json().catch( function () { return null; } );
			if ( ! response.ok || ! payload?.success || ! payload?.data?.policy ) {
				throw new Error( payload?.data?.message || root.dataset.error || 'List display preferences could not be saved.' );
			}
			currentPolicy = payload.data.policy;
			window.location.reload();
		};

		const saveSelection = async function ( buttons, selected ) {
			selectButton( buttons, selected );
			sync();
			try {
				await persist();
			} catch ( error ) {
				restore();
				window.alert( error instanceof Error ? error.message : root.dataset.error || '' );
			}
		};

		groupButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				void saveSelection( groupButtons, button );
			} );
		} );

		orderButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				void saveSelection( orderButtons, button );
			} );
		} );

		const close = function ( restoreFocus = false ) {
			if ( panel.hidden ) {
				return;
			}
			panel.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( restoreFocus ) {
				toggle.focus( { preventScroll: true } );
			}
		};

		toggle.addEventListener( 'click', function () {
			const opening = panel.hidden;
			panel.hidden = ! opening;
			toggle.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
			if ( opening ) {
				panel.querySelector( '[aria-checked="true"]' )?.focus( { preventScroll: true } );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( panel.hidden || ! ( event.target instanceof Node ) || root.contains( event.target ) ) {
				return;
			}
			close();
		} );

		panel.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}
			event.preventDefault();
			close( true );
		} );

		sync();
	}


	function initRowActionMenus( page ) {
		const menus = [ ...page.querySelectorAll( '.cb-work-row-actions__more' ) ];
		if ( menus.length === 0 ) {
			return;
		}

		const closeMenu = function ( menu, restoreFocus = false ) {
			if ( ! ( menu instanceof HTMLDetailsElement ) || ! menu.open ) {
				return;
			}
			menu.removeAttribute( 'open' );
			if ( restoreFocus ) {
				menu.querySelector( 'summary' )?.focus( { preventScroll: true } );
			}
		};

		menus.forEach( function ( menu ) {
			menu.addEventListener( 'toggle', function () {
				if ( ! menu.open ) {
					return;
				}
				menus.forEach( function ( candidate ) {
					if ( candidate !== menu ) {
						closeMenu( candidate );
					}
				} );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! ( event.target instanceof Node ) ) {
				return;
			}
			menus.forEach( function ( menu ) {
				if ( menu.open && ! menu.contains( event.target ) ) {
					closeMenu( menu );
				}
			} );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}
			const openMenu = menus.find( function ( menu ) {
				return menu.open;
			} );
			if ( openMenu ) {
				event.preventDefault();
				closeMenu( openMenu, true );
			}
		} );
	}

	ready( initWorkItems );
}() );
