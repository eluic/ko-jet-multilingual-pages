/**
 * メタボックス用JavaScript
 *
 * @package MyMultilingualPages
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// 翻訳ページ作成ボタン.
		$( document ).on( 'click', '.mmp-create-translation', function () {
			var $button = $( this );
			var postId  = $button.data( 'post-id' );
			var lang    = $button.data( 'lang' );
			var $row    = $button.closest( '.mmp-translation-row' );

			if ( ! confirm( mmpMetabox.i18n.confirm ) ) {
				return;
			}

			$button.prop( 'disabled', true ).text( mmpMetabox.i18n.creating );
			$row.addClass( 'mmp-creating' );

			$.ajax( {
				url: mmpMetabox.ajaxUrl,
				type: 'POST',
				data: {
					action:  'mmp_create_translation',
					nonce:   mmpMetabox.nonce,
					post_id: postId,
					lang:    lang,
				},
				success: function ( response ) {
					if ( response.success ) {
						// ボタンを編集リンクに差し替え.
						$button.replaceWith(
							'<span class="mmp-status-badge mmp-status-ok">下書き</span> ' +
							'<a href="' + response.data.edit_url + '" class="mmp-edit-link">' +
							'編集</a>'
						);
						$row.removeClass( 'mmp-creating' );
					} else {
						alert( response.data.message || mmpMetabox.i18n.error );
						$button.prop( 'disabled', false ).text( 'ページを作成' );
						$row.removeClass( 'mmp-creating' );
					}
				},
				error: function () {
					alert( mmpMetabox.i18n.error );
					$button.prop( 'disabled', false ).text( 'ページを作成' );
					$row.removeClass( 'mmp-creating' );
				},
			} );
		} );
	} );
} )( jQuery );
