<?php
/**
 * 翻訳エンジンインターフェース.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Translator_Interface
 *
 * 全翻訳エンジンが実装すべき共通インターフェース.
 */
interface Translator_Interface {

	/**
	 * 翻訳エンジン名を取得.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * 翻訳エンジンのスラッグを取得.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * 単一テキストを翻訳.
	 *
	 * @param string $text        翻訳するテキスト.
	 * @param string $source_lang ソース言語コード.
	 * @param string $target_lang ターゲット言語コード.
	 * @return string|false 翻訳結果、失敗時は false.
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string|false;

	/**
	 * 複数テキストを一括翻訳.
	 *
	 * @param string[] $texts       翻訳するテキスト配列.
	 * @param string   $source_lang ソース言語コード.
	 * @param string   $target_lang ターゲット言語コード.
	 * @return string[]|false 翻訳結果配列、失敗時は false.
	 */
	public function translate_batch( array $texts, string $source_lang, string $target_lang ): array|false;

	/**
	 * APIキーの有効性をチェック.
	 *
	 * @param string $api_key APIキー.
	 * @return bool
	 */
	public function validate_api_key( string $api_key ): bool;

	/**
	 * APIキーを設定.
	 *
	 * @param string $api_key APIキー.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void;
}
