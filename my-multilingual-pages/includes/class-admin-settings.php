<?php
/**
 * 管理画面 設定ページクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

use MMP\Translators\Translator_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings
 *
 * 「設定 > 多言語翻訳」ページの表示とオプション管理.
 */
class Admin_Settings {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/** @var Translator_Factory */
	private Translator_Factory $translator_factory;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager   $language_manager   言語マネージャー.
	 * @param Translator_Factory $translator_factory 翻訳ファクトリー.
	 */
	public function __construct( Language_Manager $language_manager, Translator_Factory $translator_factory ) {
		$this->language_manager   = $language_manager;
		$this->translator_factory = $translator_factory;

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * 設定メニュー追加.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( '多言語翻訳設定', 'my-multilingual-pages' ),
			__( '多言語翻訳', 'my-multilingual-pages' ),
			'manage_options',
			'mmp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * 設定項目を登録.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// 有効言語.
		register_setting( 'mmp_settings', 'mmp_enabled_languages', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_languages' ),
			'default'           => array( 'en', 'zh', 'ko', 'zh-TW' ),
		) );

		// デフォルト翻訳エンジン.
		register_setting( 'mmp_settings', 'mmp_translation_engine', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'deepl',
		) );

		// 更新モード.
		register_setting( 'mmp_settings', 'mmp_update_mode', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'notify',
		) );

		// APIキー.
		register_setting( 'mmp_settings', 'mmp_deepl_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'mmp_settings', 'mmp_google_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'mmp_settings', 'mmp_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		// 言語別設定.
		register_setting( 'mmp_settings', 'mmp_per_lang_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_per_lang_settings' ),
			'default'           => array(),
		) );

		// アンインストール設定.
		register_setting( 'mmp_settings', 'mmp_delete_on_uninstall', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0',
		) );
	}

	/**
	 * 管理画面用アセットの読み込み.
	 *
	 * @param string $hook_suffix 画面フック名.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_mmp-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'mmp-admin-settings',
			MMP_PLUGIN_URL . 'admin/css/settings.css',
			array(),
			MMP_VERSION
		);

		wp_enqueue_script(
			'mmp-admin-settings',
			MMP_PLUGIN_URL . 'admin/js/settings.js',
			array( 'jquery' ),
			MMP_VERSION,
			true
		);

		wp_localize_script( 'mmp-admin-settings', 'mmpSettings', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mmp_settings_nonce' ),
			'i18n'    => array(
				'validating'  => __( '検証中...', 'my-multilingual-pages' ),
				'valid'       => __( '✓ 有効なAPIキーです', 'my-multilingual-pages' ),
				'invalid'     => __( '✗ 無効なAPIキーです', 'my-multilingual-pages' ),
				'error'       => __( '✗ 検証に失敗しました', 'my-multilingual-pages' ),
			),
		) );
	}

	/**
	 * 設定ページを描画.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled_languages = $this->language_manager->get_enabled_languages();
		$preset_languages  = $this->language_manager->get_preset_languages();
		$engines           = $this->translator_factory->get_available_engines();
		$per_lang_settings = get_option( 'mmp_per_lang_settings', array() );
		$error_log         = get_option( 'mmp_api_error_log', array() );

		include MMP_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * 言語設定のサニタイズ.
	 *
	 * @param mixed $input 入力値.
	 * @return string[]
	 */
	public function sanitize_languages( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * 言語別設定のサニタイズ.
	 *
	 * @param mixed $input 入力値.
	 * @return array
	 */
	public function sanitize_per_lang_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $lang => $settings ) {
			$lang = sanitize_text_field( $lang );
			$sanitized[ $lang ] = array(
				'engine'         => sanitize_text_field( $settings['engine'] ?? 'deepl' ),
				'auto_translate' => ! empty( $settings['auto_translate'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * AJAX: APIキーの有効性チェック.
	 *
	 * @return void
	 */
	public function ajax_validate_api_key(): void {
		check_ajax_referer( 'mmp_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません', 'my-multilingual-pages' ) ) );
		}

		$engine  = sanitize_text_field( wp_unslash( $_POST['engine'] ?? '' ) );
		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

		if ( empty( $engine ) || empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'エンジンとAPIキーを指定してください', 'my-multilingual-pages' ) ) );
		}

		$translator = $this->translator_factory->create( $engine );

		if ( ! $translator ) {
			wp_send_json_error( array( 'message' => __( '不明な翻訳エンジンです', 'my-multilingual-pages' ) ) );
		}

		$is_valid = $translator->validate_api_key( $api_key );

		if ( $is_valid ) {
			wp_send_json_success( array( 'message' => __( '有効なAPIキーです', 'my-multilingual-pages' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( '無効なAPIキーです', 'my-multilingual-pages' ) ) );
		}
	}
}
