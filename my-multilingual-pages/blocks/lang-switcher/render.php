<?php
/**
 * 言語スイッチャーブロックのサーバーサイドレンダリング.
 *
 * @package MyMultilingualPages
 * @var array    $attributes ブロック属性.
 * @var string   $content    ブロック内容.
 * @var WP_Block $block      ブロックインスタンス.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$frontend = new MMP\Frontend( mmp()->get_language_manager() );

echo $frontend->render_language_switcher( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'style'         => $attributes['style'] ?? 'list',
	'show_inactive' => ! empty( $attributes['showInactive'] ) ? 'yes' : 'no',
	'show_flags'    => ! empty( $attributes['showFlags'] ) ? 'yes' : 'no',
	'show_names'    => ! empty( $attributes['showNames'] ) ? 'yes' : 'no',
) );
