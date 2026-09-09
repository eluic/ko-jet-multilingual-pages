<?php
/**
 * 言語管理クラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Language_Manager
 *
 * 有効な言語の管理、言語情報の取得、翻訳グループの操作を担当.
 */
class Language_Manager {

	/**
	 * プリセット言語定義.
	 *
	 * @var array<string, array{name: string, native_name: string, flag: string, deepl_code: string, google_code: string}>
	 */
	private const PRESET_LANGUAGES = array(
		'ja'    => array(
			'name'        => 'Japanese',
			'native_name' => '日本語',
			'flag'        => '🇯🇵',
			'deepl_code'  => 'JA',
			'google_code' => 'ja',
		),
		'en'    => array(
			'name'        => 'English',
			'native_name' => 'English',
			'flag'        => '🇺🇸',
			'deepl_code'  => 'EN',
			'google_code' => 'en',
		),
		'zh'    => array(
			'name'        => 'Chinese (Simplified)',
			'native_name' => '简体中文',
			'flag'        => '🇨🇳',
			'deepl_code'  => 'ZH-HANS',
			'google_code' => 'zh-CN',
		),
		'ko'    => array(
			'name'        => 'Korean',
			'native_name' => '한국어',
			'flag'        => '🇰🇷',
			'deepl_code'  => 'KO',
			'google_code' => 'ko',
		),
		'zh-TW' => array(
			'name'        => 'Chinese (Traditional)',
			'native_name' => '繁體中文',
			'flag'        => '🇹🇼',
			'deepl_code'  => 'ZH-HANT',
			'google_code' => 'zh-TW',
		),
	);

	/**
	 * デフォルト言語コードを取得.
	 *
	 * @return string
	 */
	public function get_default_language(): string {
		return get_option( 'mmp_default_language', 'ja' );
	}

	/**
	 * 有効な翻訳対象言語コード一覧を取得.
	 *
	 * @return string[]
	 */
	public function get_enabled_languages(): array {
		$languages = get_option( 'mmp_enabled_languages', array( 'en', 'zh', 'ko', 'zh-TW' ) );
		return is_array( $languages ) ? $languages : array();
	}

	/**
	 * デフォルト言語を含む全有効言語コード一覧を取得.
	 *
	 * @return string[]
	 */
	public function get_all_languages(): array {
		return array_merge( array( $this->get_default_language() ), $this->get_enabled_languages() );
	}

	/**
	 * 言語コードから言語情報を取得.
	 *
	 * @param string $code 言語コード.
	 * @return array|null
	 */
	public function get_language_info( string $code ): ?array {
		return self::PRESET_LANGUAGES[ $code ] ?? null;
	}

	/**
	 * 全プリセット言語を取得.
	 *
	 * @return array
	 */
	public function get_preset_languages(): array {
		return self::PRESET_LANGUAGES;
	}

	/**
	 * 言語の表示名を取得.
	 *
	 * @param string $code 言語コード.
	 * @return string
	 */
	public function get_language_label( string $code ): string {
		$info = $this->get_language_info( $code );
		if ( $info ) {
			return $info['flag'] . ' ' . $info['native_name'];
		}
		return $code;
	}

	/**
	 * 言語別設定を取得.
	 *
	 * @param string $lang_code 言語コード.
	 * @return array{engine: string, auto_translate: bool}
	 */
	public function get_language_settings( string $lang_code ): array {
		$per_lang = get_option( 'mmp_per_lang_settings', array() );
		$defaults = array(
			'engine'         => get_option( 'mmp_translation_engine', 'deepl' ),
			'auto_translate' => true,
		);

		if ( isset( $per_lang[ $lang_code ] ) && is_array( $per_lang[ $lang_code ] ) ) {
			return wp_parse_args( $per_lang[ $lang_code ], $defaults );
		}

		return $defaults;
	}

	/**
	 * 投稿の言語コードを取得.
	 *
	 * @param int $post_id 投稿ID.
	 * @return string 言語コード、未設定なら空文字.
	 */
	public function get_post_language( int $post_id ): string {
		return get_post_meta( $post_id, '_mmp_translation_lang', true ) ?: '';
	}

	/**
	 * 投稿の翻訳グループIDを取得.
	 *
	 * @param int $post_id 投稿ID.
	 * @return string グループID、未設定なら空文字.
	 */
	public function get_translation_group_id( int $post_id ): string {
		return get_post_meta( $post_id, '_mmp_translation_group_id', true ) ?: '';
	}

	/**
	 * 投稿を翻訳グループに登録.
	 *
	 * @param int    $post_id  投稿ID.
	 * @param string $group_id グループID.
	 * @param string $lang     言語コード.
	 * @return void
	 */
	public function set_post_translation( int $post_id, string $group_id, string $lang ): void {
		update_post_meta( $post_id, '_mmp_translation_group_id', $group_id );
		update_post_meta( $post_id, '_mmp_translation_lang', $lang );
	}

	/**
	 * 同じ翻訳グループの全投稿を取得.
	 *
	 * @param string $group_id  翻訳グループID.
	 * @param string $post_type 投稿タイプ.
	 * @return array<string, int> 言語コード => 投稿ID のマップ.
	 */
	public function get_translation_group( string $group_id, string $post_type = 'page' ): array {
		if ( empty( $group_id ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key'       => '_mmp_translation_group_id',
				'meta_value'     => $group_id,
				'posts_per_page' => 50,
				'fields'         => 'ids',
			)
		);

		$result = array();
		foreach ( $posts as $pid ) {
			$lang = $this->get_post_language( (int) $pid );
			if ( $lang ) {
				$result[ $lang ] = (int) $pid;
			}
		}

		return $result;
	}

	/**
	 * 指定投稿の特定言語版を取得.
	 *
	 * @param int    $post_id 投稿ID.
	 * @param string $lang    対象言語コード.
	 * @return int|null 翻訳版の投稿ID, 存在しなければ null.
	 */
	public function get_translated_post( int $post_id, string $lang ): ?int {
		$group_id = $this->get_translation_group_id( $post_id );
		if ( empty( $group_id ) ) {
			return null;
		}

		$group = $this->get_translation_group( $group_id );
		return $group[ $lang ] ?? null;
	}

	/**
	 * 新しい翻訳グループIDを生成.
	 *
	 * @return string UUID v4.
	 */
	public function generate_group_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * 投稿が「要更新」かどうかを判定.
	 *
	 * @param int $post_id 投稿ID.
	 * @return bool
	 */
	public function needs_update( int $post_id ): bool {
		return '1' === get_post_meta( $post_id, '_mmp_needs_update', true );
	}

	/**
	 * 投稿の翻訳ステータスを取得.
	 *
	 * @param int    $source_post_id ソース投稿ID.
	 * @param string $lang           言語コード.
	 * @return string 'translated' | 'needs_update' | 'not_translated'
	 */
	public function get_translation_status( int $source_post_id, string $lang ): string {
		$translated_id = $this->get_translated_post( $source_post_id, $lang );

		if ( null === $translated_id ) {
			return 'not_translated';
		}

		if ( $this->needs_update( $translated_id ) ) {
			return 'needs_update';
		}

		return 'translated';
	}
}
