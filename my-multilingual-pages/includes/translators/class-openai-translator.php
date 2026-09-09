<?php
/**
 * OpenAI 翻訳エンジン.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAI_Translator
 *
 * OpenAI ChatCompletion API を使用した翻訳エンジン実装.
 */
class OpenAI_Translator implements Translator_Interface {

	/** @var string API エンドポイント */
	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	/** @var string APIキー */
	private string $api_key = '';

	/** @var string 使用モデル */
	private const MODEL = 'gpt-4o-mini';

	/** @var int 最大リトライ回数 */
	private const MAX_RETRIES = 3;

	/**
	 * コンストラクタ.
	 */
	public function __construct() {
		if ( defined( 'MMP_OPENAI_API_KEY' ) && MMP_OPENAI_API_KEY ) {
			$this->api_key = MMP_OPENAI_API_KEY;
		} else {
			$this->api_key = get_option( 'mmp_openai_api_key', '' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'openai';
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

		$source_name = $this->get_language_name( $source_lang );
		$target_name = $this->get_language_name( $target_lang );

		$system_prompt = "You are a professional translator. Translate the following {$source_name} text to {$target_name}. "
			. 'Preserve all HTML tags and their attributes exactly as they are. '
			. 'Only translate the text content, not the HTML structure. '
			. 'Return only the translated text without any explanation or additional formatting.';

		$body = array(
			'model'       => self::MODEL,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $text,
				),
			),
			'temperature' => 0.3,
			'max_tokens'  => 4096,
		);

		$response = $this->api_request( $body );

		if ( false === $response ) {
			return false;
		}

		return $response['choices'][0]['message']['content'] ?? false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function translate_batch( array $texts, string $source_lang, string $target_lang ): array|false {
		if ( empty( $this->api_key ) || empty( $texts ) ) {
			return false;
		}

		// OpenAI はバッチ翻訳をサポートしないため、個別に翻訳.
		$result = array();
		foreach ( $texts as $index => $text ) {
			$translated = $this->translate( $text, $source_lang, $target_lang );
			if ( false === $translated ) {
				return false;
			}
			$result[ $index ] = $translated;
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_api_key( string $api_key ): bool {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 15,
			)
		);

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
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'OpenAI API request failed', $response->get_error_message(), $attempt + 1 );
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
				$this->log_error( 'OpenAI API error', "HTTP {$code}", $attempt + 1 );
				continue;
			}

			$this->log_error( 'OpenAI API client error', "HTTP {$code}: " . wp_remote_retrieve_body( $response ), $attempt + 1 );
			break;
		}

		return false;
	}

	/**
	 * 言語名を取得.
	 *
	 * @param string $code 言語コード.
	 * @return string
	 */
	private function get_language_name( string $code ): string {
		$map = array(
			'ja'    => 'Japanese',
			'en'    => 'English',
			'zh'    => 'Simplified Chinese',
			'ko'    => 'Korean',
			'zh-TW' => 'Traditional Chinese',
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
			'engine'    => 'openai',
			'context'   => $context,
			'message'   => $message,
			'attempt'   => $attempt,
			'timestamp' => current_time( 'mysql' ),
		);
		$log = array_slice( $log, -100 );
		update_option( 'mmp_api_error_log', $log, false );
	}
}
