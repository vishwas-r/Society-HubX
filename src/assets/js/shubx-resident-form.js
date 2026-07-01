/**
 * Society HubX - Resident Form JS
 *
 * Handles interactive logic for the shared resident-form.php component:
 *  1. Profile image live preview
 *  2. Multi-flat checkbox selection with primary-flat picker
 *  3. Resident type toggle (family vs. resident role visibility)
 */

( function () {
	// 1. Image Preview Logic
	const fileInputs = document.querySelectorAll( '.js-profile-upload' );
	fileInputs.forEach( function ( input ) {
		if ( input.dataset.handled ) {
			return;
		}
		input.dataset.handled = 'true';

		input.addEventListener( 'change', function ( e ) {
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

	// 2. Multi-Flat Checkbox Selection Logic
	function initFlatSelector( context ) {
		const checkboxes    = document.querySelectorAll( '.js-flat-checkbox-' + context );
		const primaryWrapper = document.getElementById( 'primary-flat-wrapper-' + context );
		const primarySelect  = document.getElementById( 'primary-flat-select-' + context );
		const hiddenFlatNo   = document.getElementById( 'flat-no-hidden-' + context );

		if ( ! checkboxes.length || ! primarySelect ) {
			return;
		}

		function updatePrimarySelect() {
			const checkedBoxes = Array.from( checkboxes ).filter( function ( cb ) {
				return cb.checked;
			} );
			const selectedVal = primarySelect.value;

			primarySelect.innerHTML = '';

			if ( checkedBoxes.length > 1 ) {
				if ( primaryWrapper ) {
					primaryWrapper.style.display = 'block';
				}
				primarySelect.removeAttribute( 'disabled' );
				primarySelect.setAttribute( 'required', 'required' );

				checkedBoxes.forEach( function ( cb ) {
					const opt       = document.createElement( 'option' );
					opt.value       = cb.value;
					opt.textContent = cb.dataset.number || cb.value;
					if ( cb.value === selectedVal ) {
						opt.selected = true;
					}
					primarySelect.appendChild( opt );
				} );

				// Update hidden input to match the chosen primary flat
				if ( hiddenFlatNo ) {
					hiddenFlatNo.value = primarySelect.value;
				}
			} else {
				if ( primaryWrapper ) {
					primaryWrapper.style.display = 'none';
				}
				primarySelect.setAttribute( 'disabled', 'disabled' );
				primarySelect.removeAttribute( 'required' );

				if ( checkedBoxes.length === 1 ) {
					if ( hiddenFlatNo ) {
						hiddenFlatNo.value = checkedBoxes[ 0 ].value;
					}
				} else {
					if ( hiddenFlatNo ) {
						hiddenFlatNo.value = '';
					}
				}
			}
		}

		checkboxes.forEach( function ( cb ) {
			cb.addEventListener( 'change', updatePrimarySelect );
		} );

		if ( primarySelect ) {
			primarySelect.addEventListener( 'change', function () {
				if ( hiddenFlatNo ) {
					hiddenFlatNo.value = this.value;
				}
			} );
		}

		// Run once on load to populate primary options if pre-checked
		updatePrimarySelect();
	}

	// Initialize flat selectors for each possible form context (admin, profile, family)
	[ 'admin', 'frontend_profile', 'frontend_family' ].forEach( initFlatSelector );

	// 3. Relationship/Role Toggle Logic
	const typeToggles = document.querySelectorAll( '.js-resident-type-toggle' );
	typeToggles.forEach( function ( toggle ) {
		if ( toggle.dataset.toggleHandled ) {
			return;
		}
		toggle.dataset.toggleHandled = 'true';

		toggle.addEventListener( 'change', function () {
			const context    = this.dataset.context;
			const container  = this.closest( '.row' ); // Form row scope
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
