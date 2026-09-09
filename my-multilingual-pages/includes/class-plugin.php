<?php
/**
 * メインプラグインクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * プラグインの各コンポーネントを初期化し統括するコンテナクラス.
 */
class Plugin {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/** @var Translators\Translator_Factory */
	private Translators\Translator_Factory $translator_factory;

	/** @var Translation_Sync */
	private Translation_Sync $translation_sync;

	/** @var Translation_Queue */
	private Translation_Queue $translation_queue;

	/** @var Admin_Settings */
	private Admin_Settings $admin_settings;

	/** @var Admin_Metabox */
	private Admin_Metabox $admin_metabox;

	/** @var Admin_Columns */
	private Admin_Columns $admin_columns;

	/** @var Admin_Dashboard */
	private Admin_Dashboard $admin_dashboard;

	/** @var Frontend */
	private Frontend $frontend;

	/** @var Rewrite_Manager */
	private Rewrite_Manager $rewrite_manager;

	/** @var Block_Registrar */
	private Block_Registrar $block_registrar;

	/**
	 * プラグインの初期化.
	 *
	 * @return void
	 */
	public function init(): void {
		// テキストドメインの読み込み.
		load_plugin_textdomain( MMP_TEXT_DOMAIN, false, dirname( MMP_PLUGIN_BASENAME ) . '/languages' );

		// コアコンポーネント.
		$this->language_manager   = new Language_Manager();
		$this->translator_factory = new Translators\Translator_Factory();
		$this->translation_sync   = new Translation_Sync( $this->language_manager, $this->translator_factory );
		$this->translation_queue  = new Translation_Queue( $this->translation_sync );

		// 管理画面コンポーネント.
		if ( is_admin() ) {
			$this->admin_settings  = new Admin_Settings( $this->language_manager, $this->translator_factory );
			$this->admin_metabox   = new Admin_Metabox( $this->language_manager, $this->translator_factory );
			$this->admin_columns   = new Admin_Columns( $this->language_manager );
			$this->admin_dashboard = new Admin_Dashboard( $this->language_manager );
		}

		// フロントエンドコンポーネント.
		$this->frontend        = new Frontend( $this->language_manager );
		$this->rewrite_manager = new Rewrite_Manager( $this->language_manager );

		// ブロック登録.
		$this->block_registrar = new Block_Registrar();

		// AJAX ハンドラー登録.
		$this->register_ajax_handlers();

		// Cron イベント登録.
		$this->translation_queue->register_cron();
	}

	/**
	 * プラグイン有効化時の処理.
	 *
	 * @return void
	 */
	public function activate(): void {
		// デフォルト設定の保存.
		$defaults = array(
			'mmp_default_language'    => 'ja',
			'mmp_enabled_languages'   => array( 'en', 'zh', 'ko', 'zh-TW' ),
			'mmp_translation_engine'  => 'deepl',
			'mmp_update_mode'         => 'notify',
			'mmp_delete_on_uninstall' => '0',
			'mmp_per_lang_settings'   => array(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// リライトルールのフラッシュ.
		$language_manager = new Language_Manager();
		$rewrite_manager  = new Rewrite_Manager( $language_manager );
		$rewrite_manager->add_rewrite_rules();
		flush_rewrite_rules();

		// Cron スケジュール登録.
		if ( ! wp_next_scheduled( 'mmp_process_translation_queue' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'mmp_process_translation_queue' );
		}
	}

	/**
	 * プラグイン無効化時の処理.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Cron スケジュール解除.
		$timestamp = wp_next_scheduled( 'mmp_process_translation_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mmp_process_translation_queue' );
		}

		// リライトルールのフラッシュ.
		flush_rewrite_rules();
	}

	/**
	 * AJAX ハンドラーの登録.
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_mmp_create_translation', array( $this->admin_metabox, 'ajax_create_translation' ) );
		add_action( 'wp_ajax_mmp_validate_api_key', array( $this->admin_settings, 'ajax_validate_api_key' ) );
		add_action( 'wp_ajax_mmp_sync_translation', array( $this->translation_sync, 'ajax_sync_translation' ) );
	}

	/**
	 * Language Manager を取得.
	 *
	 * @return Language_Manager
	 */
	public function get_language_manager(): Language_Manager {
		return $this->language_manager;
	}

	/**
	 * Translator Factory を取得.
	 *
	 * @return Translators\Translator_Factory
	 */
	public function get_translator_factory(): Translators\Translator_Factory {
		return $this->translator_factory;
	}

	/**
	 * Translation Sync を取得.
	 *
	 * @return Translation_Sync
	 */
	public function get_translation_sync(): Translation_Sync {
		return $this->translation_sync;
	}

	/**
	 * Translation Queue を取得.
	 *
	 * @return Translation_Queue
	 */
	public function get_translation_queue(): Translation_Queue {
		return $this->translation_queue;
	}

	/**
	 * カスタム Cron スケジュール追加.
	 *
	 * @param array $schedules スケジュール一覧.
	 * @return array
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'my-multilingual-pages' ),
		);
		return $schedules;
	}
}

// Cron スケジュールフィルター（クラス外で登録）.
add_filter( 'cron_schedules', array( Plugin::class, 'add_cron_schedules' ) );
