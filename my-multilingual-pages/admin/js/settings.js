/**
 * 設定ページ用JavaScript
 *
 * @package MyMultilingualPages
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// APIキー検証ボタン.
		$( '.mmp-validate-key' ).on( 'click', function () {
			var $button = $( this );
			var engine  = $button.data( 'engine' );
			var fieldId = $button.data( 'field' );
			var apiKey  = $( '#' + fieldId ).val();
			var $status = $( '.mmp-key-status[data-engine="' + engine + '"]' );

			if ( ! apiKey ) {
				$status.text( mmpSettings.i18n.invalid ).removeClass().addClass( 'mmp-key-status mmp-key-invalid' );
				return;
			}

			$button.prop( 'disabled', true );
			$status.text( mmpSettings.i18n.validating ).removeClass().addClass( 'mmp-key-status mmp-key-checking' );

			$.ajax( {
				url: mmpSettings.ajaxUrl,
				type: 'POST',
				data: {
					action:  'mmp_validate_api_key',
					nonce:   mmpSettings.nonce,
					engine:  engine,
					api_key: apiKey,
				},
				success: function ( response ) {
					if ( response.success ) {
						$status.text( mmpSettings.i18n.valid ).removeClass().addClass( 'mmp-key-status mmp-key-valid' );
					} else {
						$status.text( mmpSettings.i18n.invalid ).removeClass().addClass( 'mmp-key-status mmp-key-invalid' );
					}
				},
				error: function () {
					$status.text( mmpSettings.i18n.error ).removeClass().addClass( 'mmp-key-status mmp-key-invalid' );
				},
				complete: function () {
					$button.prop( 'disabled', false );
				},
			} );
		} );
	} );
} )( jQuery );
