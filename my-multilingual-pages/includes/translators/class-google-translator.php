<?php
/**
 * Google Cloud Translation 翻訳エンジン.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Translator
 *
 * Google Cloud Translation API v2 を使用した翻訳エンジン実装.
 */
class Google_Translator implements Translator_Interface {

	/** @var string API エンドポイント */
	private const API_URL = 'https://translation.googleapis.com/language/translate/v2';

	/** @var string APIキー */
	private string $api_key = '';

	/** @var int 最大リトライ回数 */
	private const MAX_RETRIES = 3;

	/**
	 * コンストラクタ.
	 */
	public function __construct() {
		if ( defined( 'MMP_GOOGLE_API_KEY' ) && MMP_GOOGLE_API_KEY ) {
			$this->api_key = MMP_GOOGLE_API_KEY;
		} else {
			$this->api_key = get_option( 'mmp_google_api_key', '' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Google Cloud Translation';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string|false {
		if ( empty( $this->api_key ) || empty( $text ) ) {
			return false;
		}

		$body = array(
			'q'      => $text,
			'source' => $this->map_language_code( $source_lang ),
			'target' => $this->map_language_code( $target_lang ),
			'format' => 'html',
			'key'    => $this->api_key,
		);

		$response = $this->api_request( $body );

		if ( false === $response ) {
			return false;
		}

		return $response['data']['translations'][0]['translatedText'] ?? false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate_batch( array $texts, string $source_lang, string $target_lang ): array|false {
		if ( empty( $this->api_key ) || empty( $texts ) ) {
			return false;
		}

		$body = array(
			'q'      => array_values( $texts ),
			'source' => $this->map_language_code( $source_lang ),
			'target' => $this->map_language_code( $target_lang ),
			'format' => 'html',
			'key'    => $this->api_key,
		);

		$response = $this->api_request( $body );

		if ( false === $response ) {
			return false;
		}

		$result = array();
		if ( isset( $response['data']['translations'] ) ) {
			foreach ( $response['data']['translations'] as $index => $translation ) {
				$result[ $index ] = $translation['translatedText'];
			}
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_api_key( string $api_key ): bool {
		$url = self::API_URL . '?' . http_build_query(
			array(
				'q'      => 'test',
				'source' => 'en',
				'target' => 'ja',
				'key'    => $api_key,
			)
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * API リクエスト（リトライ付き）.
	 *
	 * @param array $body リクエストボディ.
	 * @return array|false
	 */
	private function api_request( array $body ): array|false {
		for ( $attempt = 0; $attempt < self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				sleep( (int) pow( 2, $attempt - 1 ) );
			}

			$response = wp_remote_post(
				self::API_URL,
				array(
					'body'    => $body,
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'Google API request failed', $response->get_error_message(), $attempt + 1 );
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}

			if ( 429 === $code || $code >= 500 ) {
				$this->log_error( 'Google API error', "HTTP {$code}", $attempt + 1 );
				continue;
			}

			$this->log_error( 'Google API client error', "HTTP {$code}: " . wp_remote_retrieve_body( $response ), $attempt + 1 );
			break;
		}

		return false;
	}

	/**
	 * 言語コードマッピング.
	 *
	 * @param string $code 内部言語コード.
	 * @return string Google 言語コード.
	 */
	private function map_language_code( string $code ): string {
		$map = array(
			'ja'    => 'ja',
			'en'    => 'en',
			'zh'    => 'zh-CN',
			'ko'    => 'ko',
			'zh-TW' => 'zh-TW',
		);

		return $map[ $code ] ?? $code;
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
		$log   = get_option( 'mmp_api_error_log', array() );
		$log[] = array(
			'engine'    => 'google',
			'context'   => $context,
			'message'   => $message,
			'attempt'   => $attempt,
			'timestamp' => current_time( 'mysql' ),
		);
		$log = array_slice( $log, -100 );
		update_option( 'mmp_api_error_log', $log, false );
	}
}
