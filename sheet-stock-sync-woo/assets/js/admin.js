/**
 * Stock Manager for WooCommerce — admin behaviour.
 *
 * Three things: the stock table's search/filters, per-cell autosave for
 * quantities and thresholds, and the CSV/XLSX bulk import button.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		if ( typeof SSWAdmin === 'undefined' ) {
			return;
		}

		function escapeHtml( str ) {
			return $( '<div>' ).text( str ).html();
		}

		function showNotice( $target, success, message ) {
			$target.html(
				'<div class="notice ' + ( success ? 'notice-success' : 'notice-error' ) + '" style="margin:12px 0;"><p>' +
					escapeHtml( message ) +
				'</p></div>'
			);
		}

		/* ------------------------------------------------------------------
		 * Stock table: search + filters
		 * --------------------------------------------------------------- */

		var $table   = $( '#ssw-stock-table' );
		var $search  = $( '#ssw-search' );
		var $catSel  = $( '#ssw-filter-category' );
		var $lvlSel  = $( '#ssw-filter-level' );
		var $count   = $( '#ssw-count' );

		function applyFilters() {
			if ( ! $table.length ) {
				return;
			}

			var term  = ( $search.val() || '' ).toLowerCase().trim();
			var cat   = ( $catSel.val() || '' ).toLowerCase();
			var level = $lvlSel.val() || '';
			var $rows = $table.find( 'tbody tr' );
			var shown = 0;

			$rows.each( function () {
				var $row    = $( this );
				var haystack = String( $row.data( 'search' ) || '' );
				var cats     = String( $row.data( 'cats' ) || '' );
				var rowLevel = String( $row.data( 'level' ) || '' );

				var matches =
					( '' === term || -1 !== haystack.indexOf( term ) ) &&
					( '' === cat || -1 !== cats.split( '|' ).indexOf( cat ) ) &&
					( '' === level || rowLevel === level );

				$row.toggle( matches );

				if ( matches ) {
					shown++;
				}
			} );

			if ( $count.length ) {
				$count.text(
					SSWAdmin.i18n.showing
						.replace( '%1$d', shown )
						.replace( '%2$d', $rows.length )
				);
			}
		}

		if ( $table.length ) {
			$search.on( 'input', applyFilters );
			$catSel.on( 'change', applyFilters );
			$lvlSel.on( 'change', applyFilters );
			applyFilters();
		}

		/* ------------------------------------------------------------------
		 * Stock table: per-cell autosave
		 * --------------------------------------------------------------- */

		$table.on( 'change', 'input[data-field]', function () {
			var $input   = $( this );
			var $row     = $input.closest( 'tr' );
			var $msg     = $row.find( '.ssw-rowmsg' );
			var field    = $input.data( 'field' );
			var product  = $input.data( 'product-id' );

			$msg.removeClass( 'is-ok is-error' ).text( SSWAdmin.i18n.saving );

			$.post( SSWAdmin.ajaxUrl, {
				action: 'ssw_builtin_update',
				nonce: SSWAdmin.builtinNonce,
				product_id: product,
				field: field,
				value: $input.val()
			} )
				.done( function ( response ) {
					if ( response && response.success && response.data ) {
						$msg.addClass( 'is-ok' ).text( SSWAdmin.i18n.saved );

						if ( response.data.level_html ) {
							$row.find( '.ssw-pill' ).replaceWith( response.data.level_html );
							$row.attr( 'data-level', response.data.level );
							$row.data( 'level', response.data.level );
						}

						if ( 'threshold' === field && '' !== String( response.data.threshold ) ) {
							$row.find( 'input[data-field="threshold"]' ).val( response.data.threshold );
						}

						applyFilters();
					} else {
						var msg = ( response && response.data && response.data.message )
							? response.data.message
							: SSWAdmin.i18n.saveFail;
						$msg.addClass( 'is-error' ).text( msg );
					}
				} )
				.fail( function () {
					$msg.addClass( 'is-error' ).text( SSWAdmin.i18n.saveFail );
				} )
				.always( function () {
					window.setTimeout( function () {
						$msg.fadeOut( 300, function () {
							$( this ).text( '' ).removeClass( 'is-ok is-error' ).show();
						} );
					}, 2200 );
				} );
		} );

		/* ------------------------------------------------------------------
		 * Bulk import
		 * --------------------------------------------------------------- */

		var $importFile    = $( '#ssw-import-file' );
		var $importButton  = $( '#ssw-import-button' );
		var $importSpinner = $( '#ssw-import-spinner' );
		var $importResult  = $( '#ssw-import-result' );

		$importButton.on( 'click', function () {
			var file = $importFile[ 0 ] && $importFile[ 0 ].files ? $importFile[ 0 ].files[ 0 ] : null;

			if ( ! file ) {
				showNotice( $importResult, false, SSWAdmin.i18n.pickFile );
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'ssw_import_stock' );
			formData.append( 'nonce', SSWAdmin.importNonce );
			formData.append( 'file', file );

			$importButton.prop( 'disabled', true ).text( SSWAdmin.i18n.importing );
			$importSpinner.addClass( 'is-active' );
			$importResult.html( '' );

			$.ajax( {
				url: SSWAdmin.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false
			} )
				.done( function ( response ) {
					var data = response && response.data ? response.data : {};
					showNotice( $importResult, !! ( response && response.success ), data.message || SSWAdmin.i18n.importFail );

					if ( response && response.success ) {
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					}
				} )
				.fail( function () {
					showNotice( $importResult, false, SSWAdmin.i18n.importFail );
				} )
				.always( function () {
					$importButton.prop( 'disabled', false ).text( SSWAdmin.i18n.importBtn );
					$importSpinner.removeClass( 'is-active' );
				} );
		} );
	} );
} )( jQuery );
