<?php
/**
 * DeepL 翻訳エンジン.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DeepL_Translator
 *
 * DeepL API を使用した翻訳エンジン実装.
 */
class DeepL_Translator implements Translator_Interface {

	/** @var string API エンドポイント（Free プラン） */
	private const API_URL_FREE = 'https://api-free.deepl.com/v2/';

	/** @var string API エンドポイント（Pro プラン） */
	private const API_URL_PRO = 'https://api.deepl.com/v2/';

	/** @var string APIキー */
	private string $api_key = '';

	/** @var int 最大リトライ回数 */
	private const MAX_RETRIES = 3;

	/**
	 * コンストラクタ.
	 */
	public function __construct() {
		// wp-config.php の定数定義を優先.
		if ( defined( 'MMP_DEEPL_API_KEY' ) && MMP_DEEPL_API_KEY ) {
			$this->api_key = MMP_DEEPL_API_KEY;
		} else {
			$this->api_key = get_option( 'mmp_deepl_api_key', '' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'DeepL';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'deepl';
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
	}

	/**
	 * API ベースURLを取得.
	 *
	 * Free プランのキーは `:fx` で終わる.
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		if ( str_ends_with( $this->api_key, ':fx' ) ) {
			return self::API_URL_FREE;
		}
		return self::API_URL_PRO;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string|false {
		if ( empty( $this->api_key ) || empty( $text ) ) {
			return false;
		}

		$body = array(
			'text'        => array( $text ),
			'source_lang' => $this->map_language_code( $source_lang, 'source' ),
			'target_lang' => $this->map_language_code( $target_lang, 'target' ),
			'tag_handling' => 'html',
		);

		$response = $this->api_request( 'translate', $body );

		if ( false === $response ) {
			return false;
		}

		return $response['translations'][0]['text'] ?? false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate_batch( array $texts, string $source_lang, string $target_lang ): array|false {
		if ( empty( $this->api_key ) || empty( $texts ) ) {
			return false;
		}

		$body = array(
			'text'        => array_values( $texts ),
			'source_lang' => $this->map_language_code( $source_lang, 'source' ),
			'target_lang' => $this->map_language_code( $target_lang, 'target' ),
			'tag_handling' => 'html',
		);

		$response = $this->api_request( 'translate', $body );

		if ( false === $response ) {
			return false;
		}

		$result = array();
		foreach ( $response['translations'] as $index => $translation ) {
			$result[ $index ] = $translation['text'];
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_api_key( string $api_key ): bool {
		$original_key  = $this->api_key;
		$this->api_key = $api_key;

		$url = $this->get_api_url() . 'usage';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $this->api_key,
				),
				'timeout' => 15,
			)
		);

		$this->api_key = $original_key;

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * DeepL API にリクエストを送信（リトライ付き）.
	 *
	 * @param string $endpoint エンドポイント.
	 * @param array  $body     リクエストボディ.
	 * @return array|false レスポンス配列、失敗時は false.
	 */
	private function api_request( string $endpoint, array $body ): array|false {
		$url = $this->get_api_url() . $endpoint;

		for ( $attempt = 0; $attempt < self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				// 指数バックオフ: 1秒、2秒、4秒.
				sleep( (int) pow( 2, $attempt - 1 ) );
			}

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'DeepL-Auth-Key ' . $this->api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'DeepL API request failed', $response->get_error_message(), $attempt + 1 );
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}

			// 429 (Rate Limit) や 5xx はリトライ.
			if ( 429 === $code || $code >= 500 ) {
				$this->log_error( 'DeepL API error', "HTTP {$code}", $attempt + 1 );
				continue;
			}

			// 4xx（429除く）はリトライしない.
			$this->log_error( 'DeepL API client error', "HTTP {$code}: " . wp_remote_retrieve_body( $response ), $attempt + 1 );
			break;
		}

		return false;
	}

	/**
	 * 言語コードを DeepL 形式にマッピング.
	 *
	 * @param string $code 内部言語コード.
	 * @param string $type 'source' | 'target'.
	 * @return string DeepL 言語コード.
	 */
	private function map_language_code( string $code, string $type = 'target' ): string {
		$map = array(
			'ja'    => 'JA',
			'en'    => 'EN',
			'zh'    => 'ZH-HANS',
			'ko'    => 'KO',
			'zh-TW' => 'ZH-HANT',
		);

		$mapped = $map[ $code ] ?? strtoupper( $code );

		// ソース言語の場合、EN-US/EN-GB の区別なし.
		if ( 'source' === $type && 'EN' === $mapped ) {
			return 'EN';
		}

		// ターゲット言語で EN の場合は EN-US を使用.
		if ( 'target' === $type && 'EN' === $mapped ) {
			return 'EN-US';
		}

		return $mapped;
	}

	/**
	 * エラーログを記録.
	 *
	 * @param string $context コンテキスト.
	 * @param string $message エラーメッセージ.
	 * @param int    $attempt 試行回数.
	 * @return void
	 */
	private function log_error( string $context, string $message, int $attempt ): void {
		$log = get_option( 'mmp_api_error_log', array() );

		$log[] = array(
			'engine'    => 'deepl',
			'context'   => $context,
			'message'   => $message,
			'attempt'   => $attempt,
			'timestamp' => current_time( 'mysql' ),
		);

		// 直近100件のみ保持.
		$log = array_slice( $log, -100 );

		update_option( 'mmp_api_error_log', $log, false );
	}
}
