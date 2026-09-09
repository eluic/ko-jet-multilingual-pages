/**
 * 言語スイッチャーブロック - エディタスクリプト.
 *
 * @package MyMultilingualPages
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	const el = element.createElement;
	const { registerBlockType } = blocks;
	const { InspectorControls } = blockEditor;
	const { PanelBody, SelectControl, ToggleControl, Placeholder } = components;
	const { __ } = i18n;

	registerBlockType( 'mmp/lang-switcher', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { style, showInactive, showFlags, showNames } = attributes;

			return el(
				element.Fragment,
				null,
				// Inspector Controls (サイドバー設定).
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( '表示設定', 'my-multilingual-pages' ), initialOpen: true },
						el( SelectControl, {
							label: __( '表示スタイル', 'my-multilingual-pages' ),
							value: style,
							options: [
								{ label: __( 'リスト', 'my-multilingual-pages' ), value: 'list' },
								{ label: __( 'ドロップダウン', 'my-multilingual-pages' ), value: 'dropdown' },
								{ label: __( 'インライン', 'my-multilingual-pages' ), value: 'inline' },
							],
							onChange: function ( val ) {
								setAttributes( { style: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( '国旗を表示', 'my-multilingual-pages' ),
							checked: showFlags,
							onChange: function ( val ) {
								setAttributes( { showFlags: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( '言語名を表示', 'my-multilingual-pages' ),
							checked: showNames,
							onChange: function ( val ) {
								setAttributes( { showNames: val } );
							},
						} ),
						el( ToggleControl, {
							label: __( '未翻訳言語も表示', 'my-multilingual-pages' ),
							checked: showInactive,
							onChange: function ( val ) {
								setAttributes( { showInactive: val } );
							},
						} )
					)
				),
				// ブロックプレビュー.
				el(
					Placeholder,
					{
						icon: 'translation',
						label: __( '言語スイッチャー', 'my-multilingual-pages' ),
						instructions: __( 'フロントエンドで言語切り替えリンクが表示されます。サイドバーで表示設定を変更できます。', 'my-multilingual-pages' ),
					},
					el(
						'div',
						{ className: 'mmp-block-preview' },
						el( 'span', null, '🇯🇵 日本語' ),
						' | ',
						el( 'span', null, '🇺🇸 English' ),
						' | ',
						el( 'span', null, '🇨🇳 简体中文' ),
						' | ',
						el( 'span', null, '🇰🇷 한국어' ),
						' | ',
						el( 'span', null, '🇹🇼 繁體中文' )
					)
				)
			);
		},

		save: function () {
			// サーバーサイドレンダリングのため null を返す.
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
