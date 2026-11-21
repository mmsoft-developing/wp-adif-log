( function () {
	// Az országlista sorrendje a blokk nyelvének ábécéje szerint. A szerver is rendez,
	// de ha ott nincs intl bővítmény, a böngésző pontosabb (pl. magyar cs, gy, sz betűk).
	function sortCountries( block ) {
		var list = block.querySelector( '.ham2k-adif-log__country-list' );
		if ( ! list || ! window.Intl || ! Intl.Collator ) {
			return;
		}
		var collator = new Intl.Collator( block.getAttribute( 'lang' ) || undefined );
		Array.prototype.slice
			.call( list.children )
			.sort( function ( a, b ) {
				return collator.compare( a.getAttribute( 'data-name' ) || '', b.getAttribute( 'data-name' ) || '' );
			} )
			.forEach( function ( li ) {
				list.appendChild( li );
			} );
	}

	function init( block ) {
		sortCountries( block );

		var input = block.querySelector( '.ham2k-adif-log__search' );
		if ( ! input || input.dataset.ham2kReady ) {
			return;
		}
		input.dataset.ham2kReady = '1';

		var rows = Array.prototype.slice.call(
			block.querySelectorAll( '.ham2k-adif-log__table tbody tr:not(.ham2k-adif-log__empty)' )
		);
		var empty = block.querySelector( '.ham2k-adif-log__empty' );

		input.addEventListener( 'input', function () {
			var filter = input.value.trim().toUpperCase();
			var visible = 0;
			rows.forEach( function ( tr ) {
				var match = tr.textContent.toUpperCase().indexOf( filter ) > -1;
				tr.hidden = ! match;
				if ( match ) {
					visible++;
				}
			} );
			if ( empty ) {
				empty.hidden = visible > 0;
			}
		} );
	}

	function initAll( root ) {
		( root || document ).querySelectorAll( '.ham2k-adif-log' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}

	// Elementor: a widget az előnézetben újrarajzolódhat, ilyenkor újra be kell kötni a keresőt.
	var hooked = false;
	function hookElementor() {
		if ( hooked || ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		hooked = true;
		function onReady( $scope ) {
			initAll( $scope[ 0 ] );
		}
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/ham2k_adif_log.default', onReady );
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/ham2k_adif_contest_log.default', onReady );
	}
	hookElementor();
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
} )();
