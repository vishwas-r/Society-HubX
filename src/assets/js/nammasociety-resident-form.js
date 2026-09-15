/**
 * Namma Society - Resident Form JS (Enterprise Scale)
 *
 * Handles interactive logic for the shared resident-form.php component:
 *  1. Profile image live preview
 *  2. World-Class Enterprise Unit Portfolio & Smart Omnibox (Handles 1,000s of units across 10 towers)
 *  3. Interactive Tower Matrix Explorer
 *  4. Resident type toggle (family vs. resident role visibility)
 */

( function () {
	'use strict';

	window.NAMMASOCIETY51_UnitPortfolio = window.NAMMASOCIETY51_UnitPortfolio || {};

	// ==========================================
	// 1. Profile Image Preview
	// ==========================================
	const fileInputs = document.querySelectorAll( '.js-profile-upload' );
	fileInputs.forEach( function ( input ) {
		if ( input.dataset.handled ) {
			return;
		}
		input.dataset.handled = 'true';

		input.addEventListener( 'change', function () {
			if ( this.files && this.files[ 0 ] ) {
				const reader          = new FileReader();
				const previewSelector = this.dataset.preview;
				const iconSelector    = this.dataset.icon;

				reader.onload = function ( e ) {
					const preview = document.querySelector( previewSelector );
					const icon    = document.querySelector( iconSelector );
					if ( preview ) {
						preview.src = e.target.result;
						preview.classList.remove( 'd-none' );
					}
					if ( icon ) {
						icon.classList.add( 'd-none' );
					}
				};
				reader.readAsDataURL( this.files[ 0 ] );
			}
		} );
	} );

	// ==========================================
	// 2. Enterprise Unit Portfolio Engine
	// ==========================================
	function initUnitPortfolio( context ) {
		const tray = document.getElementById( 'unit-portfolio-tray-' + context );
		if ( ! tray ) {
			return;
		}

		const flatsDataEl = document.getElementById( 'flats-data-' + context );
		const initDataEl  = document.getElementById( 'initial-flats-' + context );
		if ( ! flatsDataEl ) {
			return;
		}

		let allFlats = [];
		try {
			allFlats = JSON.parse( flatsDataEl.textContent || '[]' );
		} catch ( e ) {
			allFlats = [];
		}

		// Build Fast Hash Indexes (ID -> flat, Number+Block -> flat)
		const flatById = {};
		const flatByNumBlock = {};
		const uniqueTowers = [];

		allFlats.forEach( function ( f ) {
			const idStr = String( f.id );
			flatById[ idStr ] = f;

			const cleanB = String( f.block || '' ).trim().toLowerCase();
			const cleanN = String( f.number || f.id ).trim().toLowerCase();
			flatByNumBlock[ cleanB + '___' + cleanN ] = f;
			flatByNumBlock[ '___' + cleanN ] = f;

			if ( f.block && ! uniqueTowers.includes( f.block ) ) {
				uniqueTowers.push( f.block );
			}
		} );
		uniqueTowers.sort( function ( a, b ) {
			return a.localeCompare( b, undefined, { numeric: true, sensitivity: 'base' } );
		} );

		// Elements
		const countBadge     = document.getElementById( 'unit-count-badge-' + context );
		const emptyPrompt    = document.getElementById( 'unit-tray-empty-' + context );
		const hiddenInputs   = document.getElementById( 'unit-hidden-inputs-' + context );
		const hiddenFlatNo   = document.getElementById( 'flat-no-hidden-' + context );
		const hiddenBlock    = document.getElementById( 'block-hidden-' + context );

		const omniboxInput   = document.getElementById( 'unit-omnibox-input-' + context );
		const dropdownMenu   = document.getElementById( 'unit-omnibox-dropdown-' + context );
		const towerFilterBtn = document.getElementById( 'tower-filter-btn-' + context );
		const towerLabel     = document.getElementById( 'selected-tower-label-' + context );
		const towerFilterMenu = document.getElementById( 'tower-filter-menu-' + context );

		const matrixToggleBtn = document.getElementById( 'toggle-tower-matrix-btn-' + context );
		const matrixExplorer = document.getElementById( 'tower-matrix-explorer-' + context );
		const closeMatrixBtn = document.getElementById( 'close-matrix-btn-' + context );
		const matrixPills    = document.getElementById( 'matrix-tower-pills-' + context );
		const matrixGrid     = document.getElementById( 'matrix-units-grid-' + context );

		let selectedUnits     = []; // Array of { id, number, block, floor, display, isPrimary }
		let currentTowerFilter = 'all';
		let activeMatrixTower = uniqueTowers[ 0 ] || '';
		let highlightedIndex  = -1;

		// ------------------------------------------
		// Normalize and Lookup Helper
		// ------------------------------------------
		function findFlat( queryId, queryBlock ) {
			if ( ! queryId ) return null;
			const idStr = String( queryId ).trim();
			if ( flatById[ idStr ] ) return flatById[ idStr ];

			const cleanB = String( queryBlock || '' ).trim().toLowerCase().replace( /^block\s*/i, '' );
			const cleanN = idStr.toLowerCase().replace( /^flat_\s*/i, '' );

			if ( cleanB && flatByNumBlock[ cleanB + '___' + cleanN ] ) {
				return flatByNumBlock[ cleanB + '___' + cleanN ];
			}
			if ( flatByNumBlock[ '___' + cleanN ] ) {
				return flatByNumBlock[ '___' + cleanN ];
			}

			// Partial search match
			const found = allFlats.find( function ( f ) {
				return String( f.number ) === idStr || String( f.display ) === idStr || String( f.id ) === idStr;
			} );
			return found || null;
		}

		// ------------------------------------------
		// Render Tray & Sync Hidden Inputs
		// ------------------------------------------
		function renderTray() {
			// Remove existing cards
			tray.querySelectorAll( '.js-unit-card' ).forEach( function ( el ) {
				el.remove();
			} );

			if ( selectedUnits.length === 0 ) {
				if ( emptyPrompt ) emptyPrompt.classList.remove( 'd-none' );
				if ( countBadge ) {
					countBadge.textContent = '0 Units Allocated';
					countBadge.className = 'badge bg-secondary bg-opacity-10 text-secondary fw-semibold px-2 py-1 rounded-pill small';
				}
				if ( hiddenInputs ) hiddenInputs.innerHTML = '';
				if ( hiddenFlatNo ) hiddenFlatNo.value = '';
				if ( hiddenBlock ) hiddenBlock.value = '';
				updateMatrixGrid();
				return;
			}

			if ( emptyPrompt ) emptyPrompt.classList.add( 'd-none' );

			// Ensure at least one unit is primary
			const hasPrimary = selectedUnits.some( function ( u ) { return u.isPrimary; } );
			if ( ! hasPrimary && selectedUnits.length > 0 ) {
				selectedUnits[ 0 ].isPrimary = true;
			}

			if ( countBadge ) {
				const count = selectedUnits.length;
				countBadge.textContent = count === 1 ? '1 Unit Allocated' : count + ' Units Allocated';
				countBadge.className = 'badge bg-primary bg-opacity-10 text-primary fw-semibold px-2 py-1 rounded-pill small';
			}

			// Render Cards
			hiddenInputs.innerHTML = '';
			selectedUnits.forEach( function ( u ) {
				// 1. Hidden input for form submission
				const hid = document.createElement( 'input' );
				hid.type  = 'hidden';
				hid.name  = 'flat_ids[]';
				hid.value = u.id;
				hiddenInputs.appendChild( hid );

				// 2. Visual card
				const card = document.createElement( 'div' );
				card.className = 'js-unit-card d-inline-flex align-items-center bg-white border rounded-3 px-3 py-2 shadow-sm gap-2';
				card.style.transition = 'all 0.15s ease';

				let towerHtml = '';
				if ( u.block ) {
					towerHtml = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-bold" style="font-size: 11px;">Tower ' + escapeHtml( u.block ) + '</span>';
				}

				let floorHtml = '';
				if ( u.floor ) {
					floorHtml = '<span class="text-muted smaller">Fl. ' + escapeHtml( u.floor ) + '</span>';
				}

				let primaryHtml = '';
				if ( u.isPrimary ) {
					primaryHtml = '<span class="badge bg-warning text-dark border border-warning px-2 py-1 small fw-bold d-inline-flex align-items-center gap-1 shadow-sm">' +
					              '<i class="bi bi-star-fill text-dark small"></i> Primary</span>';
				} else {
					primaryHtml = '<button type="button" class="btn btn-sm btn-link text-decoration-none text-muted p-0 smaller hover-primary js-set-primary" data-id="' + escapeHtml( u.id ) + '" title="Set as Primary Residence">' +
					              '<i class="bi bi-star me-1"></i>Set Primary</button>';
				}

				card.innerHTML =
					towerHtml +
					'<strong class="text-dark fs-6">' + escapeHtml( u.number ) + '</strong>' +
					floorHtml +
					primaryHtml +
					'<button type="button" class="btn-close ms-1 smaller js-remove-unit shadow-none" data-id="' + escapeHtml( u.id ) + '" title="Remove Flat" aria-label="Close" style="font-size: 0.65rem;"></button>';

				tray.appendChild( card );

				// Sync Primary flat to hidden inputs
				if ( u.isPrimary ) {
					if ( hiddenFlatNo ) hiddenFlatNo.value = u.number || u.id;
					if ( hiddenBlock ) hiddenBlock.value = u.block || '';
				}
			} );

			// Attach Card Events
			tray.querySelectorAll( '.js-set-primary' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					setPrimary( this.dataset.id );
				} );
			} );

			tray.querySelectorAll( '.js-remove-unit' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					removeUnit( this.dataset.id );
				} );
			} );

			updateMatrixGrid();
		}

		function escapeHtml( str ) {
			if ( ! str ) return '';
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		}

		// ------------------------------------------
		// Add / Remove / Primary Operations
		// ------------------------------------------
		function addUnit( flatId ) {
			const flat = findFlat( flatId );
			if ( ! flat ) return false;

			const exists = selectedUnits.some( function ( u ) { return String( u.id ) === String( flat.id ); } );
			if ( exists ) return false;

			selectedUnits.push( {
				id: String( flat.id ),
				number: String( flat.number || flat.id ),
				block: String( flat.block || '' ),
				floor: String( flat.floor || '' ),
				display: String( flat.display || flat.number ),
				isPrimary: selectedUnits.length === 0
			} );

			renderTray();
			return true;
		}

		function removeUnit( flatId ) {
			const idx = selectedUnits.findIndex( function ( u ) { return String( u.id ) === String( flatId ); } );
			if ( idx === -1 ) return;

			const wasPrimary = selectedUnits[ idx ].isPrimary;
			selectedUnits.splice( idx, 1 );

			if ( wasPrimary && selectedUnits.length > 0 ) {
				selectedUnits[ 0 ].isPrimary = true;
			}

			renderTray();
		}

		function setPrimary( flatId ) {
			selectedUnits.forEach( function ( u ) {
				u.isPrimary = ( String( u.id ) === String( flatId ) );
			} );
			renderTray();
		}

		function setSelection( flatArray, primaryFlatId, blockVal ) {
			selectedUnits = [];
			const arr = Array.isArray( flatArray ) ? flatArray : ( flatArray ? [ flatArray ] : [] );

			arr.forEach( function ( fid ) {
				const flat = findFlat( fid, blockVal );
				if ( flat ) {
					const exists = selectedUnits.some( function ( u ) { return String( u.id ) === String( flat.id ); } );
					if ( ! exists ) {
						selectedUnits.push( {
							id: String( flat.id ),
							number: String( flat.number || flat.id ),
							block: String( flat.block || '' ),
							floor: String( flat.floor || '' ),
							display: String( flat.display || flat.number ),
							isPrimary: false
						} );
					}
				}
			} );

			// Designate Primary
			if ( selectedUnits.length > 0 ) {
				let matched = false;
				if ( primaryFlatId ) {
					selectedUnits.forEach( function ( u ) {
						if ( String( u.id ) === String( primaryFlatId ) || String( u.number ) === String( primaryFlatId ) ) {
							u.isPrimary = true;
							matched = true;
						}
					} );
				}
				if ( ! matched ) {
					selectedUnits[ 0 ].isPrimary = true;
				}
			}

			renderTray();
		}

		function clearSelection() {
			selectedUnits = [];
			renderTray();
		}

		// ------------------------------------------
		// Smart Omnibox Autocomplete Logic
		// ------------------------------------------
		function filterFlats( query ) {
			const q = String( query || '' ).trim().toLowerCase();
			return allFlats.filter( function ( f ) {
				// 1. Tower filter check
				if ( currentTowerFilter !== 'all' ) {
					if ( String( f.block || '' ).toLowerCase() !== currentTowerFilter.toLowerCase() ) {
						return false;
					}
				}

				if ( ! q ) {
					return true;
				}

				const numStr = String( f.number || f.id ).toLowerCase();
				const blkStr = String( f.block || '' ).toLowerCase();
				const flrStr = String( f.floor || '' ).toLowerCase();
				const dspStr = String( f.display || '' ).toLowerCase();

				return numStr.includes( q ) ||
					blkStr.includes( q ) ||
					dspStr.includes( q ) ||
					( 'floor ' + flrStr ).includes( q ) ||
					( 'tower ' + blkStr ).includes( q );
			} );
		}

		function renderDropdown( query ) {
			if ( ! dropdownMenu ) return;

			const matches = filterFlats( query );
			highlightedIndex = -1;

			if ( matches.length === 0 ) {
				dropdownMenu.innerHTML = '<div class="p-3 text-center text-muted small"><i class="bi bi-search me-1"></i>No flats found matching "<strong>' + escapeHtml( query ) + '</strong>"</div>';
				dropdownMenu.classList.remove( 'd-none' );
				return;
			}

			// Group by Tower
			const grouped = {};
			matches.slice( 0, 40 ).forEach( function ( f ) { // Cap at top 40 for speed
				const towerKey = f.block ? 'Tower ' + f.block : 'General Units';
				if ( ! grouped[ towerKey ] ) grouped[ towerKey ] = [];
				grouped[ towerKey ].push( f );
			} );

			let html = '';
			Object.keys( grouped ).forEach( function ( towerName ) {
				html += '<div class="dropdown-header small fw-bold text-uppercase text-secondary px-2 pt-2 pb-1 border-bottom bg-light bg-opacity-75 d-flex justify-content-between">' +
						'<span><i class="bi bi-building me-1"></i>' + escapeHtml( towerName ) + '</span>' +
						'<span class="badge bg-secondary bg-opacity-25 text-dark">' + grouped[ towerName ].length + ' units</span>' +
						'</div>';

				html += '<div class="py-1">';
				grouped[ towerName ].forEach( function ( f ) {
					const isSelected = selectedUnits.some( function ( u ) { return String( u.id ) === String( f.id ); } );

					html += '<div class="js-dropdown-item d-flex align-items-center justify-content-between p-2 rounded-2 cursor-pointer hover-bg-light transition-all ' + ( isSelected ? 'bg-primary bg-opacity-10' : '' ) + '" data-id="' + escapeHtml( f.id ) + '">' +
							'<div class="d-flex align-items-center gap-2">' +
							'<span class="badge bg-white text-dark border fw-bold px-2 py-1 shadow-xs">' + escapeHtml( f.number ) + '</span>' +
							( f.floor ? '<span class="text-muted smaller">Floor ' + escapeHtml( f.floor ) + '</span>' : '' ) +
							'</div>' +
							'<div>' +
							( isSelected ?
								'<span class="badge bg-success text-white small fw-semibold"><i class="bi bi-check2 me-1"></i>Allocated</span>' :
								'<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold smaller rounded-pill">+ Allocate</button>'
							) +
							'</div>' +
							'</div>';
				} );
				html += '</div>';
			} );

			dropdownMenu.innerHTML = html;
			dropdownMenu.classList.remove( 'd-none' );

			// Click handler on items
			dropdownMenu.querySelectorAll( '.js-dropdown-item' ).forEach( function ( item ) {
				item.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					const fid = this.dataset.id;
					const isSel = selectedUnits.some( function ( u ) { return String( u.id ) === String( fid ); } );
					if ( isSel ) {
						removeUnit( fid );
					} else {
						addUnit( fid );
					}
					// Re-render dropdown to reflect new state
					renderDropdown( omniboxInput ? omniboxInput.value : '' );
				} );
			} );
		}

		if ( omniboxInput ) {
			omniboxInput.addEventListener( 'focus', function () {
				renderDropdown( this.value );
			} );

			omniboxInput.addEventListener( 'input', function () {
				renderDropdown( this.value );
			} );

			omniboxInput.addEventListener( 'keydown', function ( e ) {
				const items = dropdownMenu ? dropdownMenu.querySelectorAll( '.js-dropdown-item' ) : [];
				if ( ! items.length || dropdownMenu.classList.contains( 'd-none' ) ) {
					return;
				}

				if ( e.key === 'ArrowDown' ) {
					e.preventDefault();
					highlightedIndex = Math.min( highlightedIndex + 1, items.length - 1 );
					updateHighlight( items );
				} else if ( e.key === 'ArrowUp' ) {
					e.preventDefault();
					highlightedIndex = Math.max( highlightedIndex - 1, 0 );
					updateHighlight( items );
				} else if ( e.key === 'Enter' ) {
					e.preventDefault();
					if ( highlightedIndex >= 0 && items[ highlightedIndex ] ) {
						items[ highlightedIndex ].click();
					} else if ( items.length > 0 ) {
						items[ 0 ].click();
					}
				} else if ( e.key === 'Escape' ) {
					dropdownMenu.classList.add( 'd-none' );
				}
			} );
		}

		function updateHighlight( items ) {
			items.forEach( function ( item, idx ) {
				if ( idx === highlightedIndex ) {
					item.classList.add( 'bg-light', 'border-primary' );
					item.scrollIntoView( { block: 'nearest' } );
				} else {
					item.classList.remove( 'bg-light', 'border-primary' );
				}
			} );
		}

		// Close dropdown when clicked outside
		document.addEventListener( 'click', function ( e ) {
			if ( dropdownMenu && ! dropdownMenu.contains( e.target ) && omniboxInput && ! omniboxInput.contains( e.target ) ) {
				dropdownMenu.classList.add( 'd-none' );
			}
		} );

		// ------------------------------------------
		// Tower Filter Menu Dropdown Handlers
		// ------------------------------------------
		if ( towerFilterMenu ) {
			towerFilterMenu.querySelectorAll( '.js-tower-filter' ).forEach( function ( link ) {
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					towerFilterMenu.querySelectorAll( '.js-tower-filter' ).forEach( function ( l ) { l.classList.remove( 'active', 'fw-bold' ); } );
					this.classList.add( 'active', 'fw-bold' );

					currentTowerFilter = this.dataset.tower || 'all';
					if ( towerLabel ) {
						towerLabel.textContent = currentTowerFilter === 'all' ? 'All Towers' : 'Tower ' + currentTowerFilter;
					}

					if ( omniboxInput ) {
						renderDropdown( omniboxInput.value );
					}
				} );
			} );
		}

		// ------------------------------------------
		// Interactive Tower Matrix Explorer
		// ------------------------------------------
		function initMatrixExplorer() {
			if ( ! matrixPills || ! matrixGrid ) return;

			matrixPills.innerHTML = '';
			uniqueTowers.forEach( function ( towerName, idx ) {
				const li = document.createElement( 'li' );
				li.className = 'nav-item';
				const btn = document.createElement( 'button' );
				btn.type  = 'button';
				btn.className = 'nav-link py-1 px-3 small rounded-2 fw-semibold ' + ( idx === 0 ? 'active' : '' );
				btn.textContent = 'Tower ' + towerName;
				btn.dataset.tower = towerName;
				btn.addEventListener( 'click', function () {
					matrixPills.querySelectorAll( '.nav-link' ).forEach( function ( b ) { b.classList.remove( 'active' ); } );
					this.classList.add( 'active' );
					activeMatrixTower = this.dataset.tower;
					updateMatrixGrid();
				} );
				li.appendChild( btn );
				matrixPills.appendChild( li );
			} );

			updateMatrixGrid();
		}

		function updateMatrixGrid() {
			if ( ! matrixGrid ) return;

			const towerUnits = allFlats.filter( function ( f ) {
				return String( f.block || '' ).toLowerCase() === String( activeMatrixTower ).toLowerCase();
			} );

			if ( towerUnits.length === 0 ) {
				matrixGrid.innerHTML = '<div class="text-muted small p-2">No units mapped for Tower ' + escapeHtml( activeMatrixTower ) + '</div>';
				return;
			}

			let html = '';
			towerUnits.forEach( function ( f ) {
				const isSelected = selectedUnits.some( function ( u ) { return String( u.id ) === String( f.id ); } );
				html += '<button type="button" class="btn btn-sm ' + ( isSelected ? 'btn-primary text-white shadow-sm fw-bold' : 'btn-outline-light text-dark border bg-light bg-opacity-50' ) + ' js-matrix-tile px-2 py-1 rounded-2 smaller" data-id="' + escapeHtml( f.id ) + '">' +
						( isSelected ? '<i class="bi bi-check2 me-1"></i>' : '' ) +
						escapeHtml( f.number ) +
						'</button>';
			} );

			matrixGrid.innerHTML = html;

			matrixGrid.querySelectorAll( '.js-matrix-tile' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					const fid = this.dataset.id;
					const isSel = selectedUnits.some( function ( u ) { return String( u.id ) === String( fid ); } );
					if ( isSel ) {
						removeUnit( fid );
					} else {
						addUnit( fid );
					}
				} );
			} );
		}

		if ( matrixToggleBtn && matrixExplorer ) {
			matrixToggleBtn.addEventListener( 'click', function () {
				const isHidden = matrixExplorer.classList.contains( 'd-none' );
				if ( isHidden ) {
					matrixExplorer.classList.remove( 'd-none' );
					initMatrixExplorer();
				} else {
					matrixExplorer.classList.add( 'd-none' );
				}
			} );
		}

		if ( closeMatrixBtn && matrixExplorer ) {
			closeMatrixBtn.addEventListener( 'click', function () {
				matrixExplorer.classList.add( 'd-none' );
			} );
		}

		// Initial hydration from pre-existing flat data
		if ( initDataEl ) {
			try {
				const initObj = JSON.parse( initDataEl.textContent || '{}' );
				if ( initObj.flats && initObj.flats.length > 0 ) {
					setSelection( initObj.flats, initObj.primary, initObj.block );
				}
			} catch ( e ) {}
		}

		// Register global API instance
		window.NAMMASOCIETY51_UnitPortfolio[ context ] = {
			addUnit: addUnit,
			removeUnit: removeUnit,
			setPrimary: setPrimary,
			setSelection: setSelection,
			clearSelection: clearSelection,
			getSelected: function () { return selectedUnits; }
		};
	}

	// Initialize for available contexts
	[ 'admin', 'frontend_profile', 'frontend_family' ].forEach( initUnitPortfolio );

	// ==========================================
	// 3. Resident Type Toggle Logic
	// ==========================================
	const typeToggles = document.querySelectorAll( '.js-resident-type-toggle' );
	typeToggles.forEach( function ( toggle ) {
		if ( toggle.dataset.toggleHandled ) {
			return;
		}
		toggle.dataset.toggleHandled = 'true';

		toggle.addEventListener( 'change', function () {
			const context    = this.dataset.context;
			const container  = this.closest( '.row' );
			const relWrapper = container.querySelector( '#relation-wrapper-' + context );
			const roleWrapper = container.querySelector( '#society-role-wrapper-' + context );
			const relSelect  = relWrapper ? relWrapper.querySelector( 'select' ) : null;

			if ( this.value === 'family' ) {
				if ( relWrapper )  { relWrapper.style.display  = 'block'; }
				if ( roleWrapper ) { roleWrapper.style.display = 'none'; }
				if ( relSelect )   { relSelect.setAttribute( 'required', 'required' ); }
			} else {
				if ( relWrapper )  { relWrapper.style.display  = 'none'; }
				if ( roleWrapper ) { roleWrapper.style.display = 'block'; }
				if ( relSelect )   { relSelect.removeAttribute( 'required' ); }
			}
		} );
	} );
} )();
