<?php
/**
 * リライトルール管理クラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rewrite_Manager
 *
 * /en/page-slug/ 形式のサブディレクトリ URL を実装するリライトルール管理.
 */
class Rewrite_Manager {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager $language_manager 言語マネージャー.
	 */
	public function __construct( Language_Manager $language_manager ) {
		$this->language_manager = $language_manager;

		add_action( 'init', array( $this, 'add_rewrite_rules' ), 5 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'request', array( $this, 'parse_request' ) );
		add_filter( 'page_link', array( $this, 'filter_page_link' ), 10, 2 );
	}

	/**
	 * リライトルール追加.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		$enabled_langs = $this->language_manager->get_enabled_languages();

		foreach ( $enabled_langs as $lang_code ) {
			$escaped_lang = preg_quote( $lang_code, '/' );

			// /en/page-slug/ → pagename=page-slug&mmp_lang=en
			add_rewrite_rule(
				'^' . $escaped_lang . '/(.+?)/?$',
				'index.php?pagename=$matches[1]&mmp_lang=' . $lang_code,
				'top'
			);

			// /en/ → フロントページの翻訳版.
			add_rewrite_rule(
				'^' . $escaped_lang . '/?$',
				'index.php?mmp_lang=' . $lang_code . '&mmp_front_page=1',
				'top'
			);
		}
	}

	/**
	 * カスタムクエリ変数追加.
	 *
	 * @param string[] $vars クエリ変数配列.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'mmp_lang';
		$vars[] = 'mmp_front_page';
		return $vars;
	}

	/**
	 * リクエスト解析.
	 *
	 * 言語プレフィックス付きURLから正しい翻訳ページを解決する.
	 *
	 * @param array $query_vars クエリ変数.
	 * @return array
	 */
	public function parse_request( array $query_vars ): array {
		if ( empty( $query_vars['mmp_lang'] ) ) {
			return $query_vars;
		}

		$lang = sanitize_text_field( $query_vars['mmp_lang'] );

		// フロントページの翻訳版.
		if ( ! empty( $query_vars['mmp_front_page'] ) ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$translated_id = $this->language_manager->get_translated_post( $front_page_id, $lang );
				if ( $translated_id ) {
					$query_vars['page_id'] = $translated_id;
					unset( $query_vars['mmp_front_page'] );
					return $query_vars;
				}
			}
		}

		// 通常ページ: pagename から対応する翻訳ページを検索.
		if ( ! empty( $query_vars['pagename'] ) ) {
			$pagename = $query_vars['pagename'];

			// まず日本語マスターページのスラッグで検索.
			$master_page = get_page_by_path( $pagename );

			if ( $master_page ) {
				// マスターページの翻訳版を取得.
				$translated_id = $this->language_manager->get_translated_post( $master_page->ID, $lang );
				if ( $translated_id ) {
					$query_vars['page_id'] = $translated_id;
					unset( $query_vars['pagename'] );
					return $query_vars;
				}
			}

			// 翻訳ページ自体のスラッグで検索.
			$translated_page = get_page_by_path( $pagename );
			if ( $translated_page ) {
				$page_lang = $this->language_manager->get_post_language( $translated_page->ID );
				if ( $page_lang === $lang ) {
					$query_vars['page_id'] = $translated_page->ID;
					unset( $query_vars['pagename'] );
					return $query_vars;
				}
			}
		}

		return $query_vars;
	}

	/**
	 * ページリンクフィルター.
	 *
	 * 翻訳ページのパーマリンクに言語プレフィックスを付与.
	 *
	 * @param string $link    パーマリンク.
	 * @param int    $post_id 投稿ID.
	 * @return string
	 */
	public function filter_page_link( string $link, int $post_id ): string {
		$post_lang    = $this->language_manager->get_post_language( $post_id );
		$default_lang = $this->language_manager->get_default_language();

		// デフォルト言語（日本語）はプレフィックスなし.
		if ( empty( $post_lang ) || $post_lang === $default_lang ) {
			return $link;
		}

		$home_url = home_url( '/' );

		// 既にプレフィックスが付いている場合はスキップ.
		$enabled_langs = $this->language_manager->get_enabled_languages();
		foreach ( $enabled_langs as $lc ) {
			if ( str_contains( $link, $home_url . $lc . '/' ) ) {
				return $link;
			}
		}

		// home_url からの相対パスを取得.
		$relative = str_replace( $home_url, '', $link );

		return $home_url . $post_lang . '/' . ltrim( $relative, '/' );
	}
}
