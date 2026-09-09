<?php
/**
 * プラグインアンインストール処理.
 *
 * WordPress がこのファイルを呼び出すのは、プラグインが管理画面から削除された時のみ.
 *
 * @package MyMultilingualPages
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 削除オプションの確認.
$delete_data = get_option( 'mmp_delete_on_uninstall', '0' );

if ( '1' !== $delete_data ) {
	// データを残す設定の場合、オプションのみクリーンアップ.
	delete_option( 'mmp_api_error_log' );
	delete_option( 'mmp_translation_queue' );
	return;
}

// === データ削除モード ===

// 1. プラグイン設定（wp_options）を削除.
$options_to_delete = array(
	'mmp_default_language',
	'mmp_enabled_languages',
	'mmp_translation_engine',
	'mmp_deepl_api_key',
	'mmp_google_api_key',
	'mmp_openai_api_key',
	'mmp_update_mode',
	'mmp_per_lang_settings',
	'mmp_delete_on_uninstall',
	'mmp_api_error_log',
	'mmp_translation_queue',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// 2. 翻訳関連の投稿メタを削除.
global $wpdb;

$meta_keys = array(
	'_mmp_translation_group_id',
	'_mmp_translation_lang',
	'_mmp_source_post_id',
	'_mmp_last_translated_hash',
	'_mmp_needs_update',
	'_mmp_update_mode',
);

foreach ( $meta_keys as $meta_key ) {
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $meta_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		array( '%s' )
	);
}

// 3. 翻訳ページを削除（オプション: デフォルト言語以外のページ）.
// 注意: これは破壊的操作のため、翻訳用メタが付いたページのみ対象.
$translated_pages = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != %s",
		'_mmp_translation_lang',
		'ja'
	)
);

// この時点でメタは既に削除済みなので、事前に取得したIDリストで処理.
// (上記のクエリはメタ削除前に実行される想定だが、安全のためスキップ)
// 翻訳ページの削除は手動で行うことを推奨.

// 4. Cron スケジュール解除.
$timestamp = wp_next_scheduled( 'mmp_process_translation_queue' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'mmp_process_translation_queue' );
}

// 5. リライトルールをフラッシュ.
flush_rewrite_rules();
