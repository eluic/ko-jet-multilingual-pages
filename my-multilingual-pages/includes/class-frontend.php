<?php
/**
 * フロントエンドクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 *
 * 言語スイッチャー（ショートコード・ウィジェット）と hreflang タグ出力.
 */
class Frontend {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager $language_manager 言語マネージャー.
	 */
	public function __construct( Language_Manager $language_manager ) {
		$this->language_manager = $language_manager;

		// hreflang タグ.
		add_action( 'wp_head', array( $this, 'output_hreflang_tags' ) );

		// ショートコード.
		add_shortcode( 'lang_switcher', array( $this, 'shortcode_lang_switcher' ) );

		// ウィジェット.
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// フロントエンド用CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * hreflang タグ出力.
	 *
	 * @return void
	 */
	public function output_hreflang_tags(): void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$post_id  = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$group_id = $this->language_manager->get_translation_group_id( $post_id );
		if ( empty( $group_id ) ) {
			return;
		}

		$translations = $this->language_manager->get_translation_group( $group_id );

		foreach ( $translations as $lang_code => $trans_id ) {
			$trans_post = get_post( $trans_id );
			if ( ! $trans_post || 'publish' !== $trans_post->post_status ) {
				continue;
			}

			$url      = $this->get_translated_url( $trans_id, $lang_code );
			$hreflang = $this->get_hreflang_code( $lang_code );

			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $hreflang ),
				esc_url( $url )
			);
		}

		// x-default (日本語ページ).
		$default_lang = $this->language_manager->get_default_language();
		if ( isset( $translations[ $default_lang ] ) ) {
			$default_url = get_permalink( $translations[ $default_lang ] );
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
				esc_url( $default_url )
			);
		}
	}

	/**
	 * 言語スイッチャーショートコード.
	 *
	 * @param array|string $atts ショートコード属性.
	 * @return string HTML 出力.
	 */
	public function shortcode_lang_switcher( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'style'         => 'list',       // 'list' | 'dropdown' | 'inline'
				'show_inactive' => 'no',         // 未翻訳言語を表示するか.
				'show_flags'    => 'yes',
				'show_names'    => 'yes',
			),
			$atts,
			'lang_switcher'
		);

		return $this->render_language_switcher( $atts );
	}

	/**
	 * 言語スイッチャーの HTML を生成.
	 *
	 * @param array $args 表示オプション.
	 * @return string HTML.
	 */
	public function render_language_switcher( array $args = array() ): string {
		if ( ! is_singular( 'page' ) ) {
			return '';
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$group_id = $this->language_manager->get_translation_group_id( $post_id );
		if ( empty( $group_id ) ) {
			return '';
		}

		$translations = $this->language_manager->get_translation_group( $group_id );
		$current_lang = $this->language_manager->get_post_language( $post_id );
		$all_langs    = $this->language_manager->get_all_languages();

		$show_inactive = 'yes' === ( $args['show_inactive'] ?? 'no' );
		$show_flags    = 'yes' === ( $args['show_flags'] ?? 'yes' );
		$show_names    = 'yes' === ( $args['show_names'] ?? 'yes' );
		$style         = $args['style'] ?? 'list';

		$items = array();

		foreach ( $all_langs as $lang_code ) {
			$trans_id   = $translations[ $lang_code ] ?? null;
			$trans_post = $trans_id ? get_post( $trans_id ) : null;
			$is_published = $trans_post && 'publish' === $trans_post->post_status;

			if ( ! $is_published && ! $show_inactive ) {
				continue;
			}

			$lang_info = $this->language_manager->get_language_info( $lang_code );
			$label     = '';

			if ( $show_flags && $lang_info ) {
				$label .= $lang_info['flag'] . ' ';
			}
			if ( $show_names && $lang_info ) {
				$label .= $lang_info['native_name'];
			}

			$url        = $is_published ? $this->get_translated_url( $trans_id, $lang_code ) : '';
			$is_current = $lang_code === $current_lang;

			$items[] = array(
				'lang_code'  => $lang_code,
				'label'      => trim( $label ),
				'url'        => $url,
				'is_current' => $is_current,
				'is_active'  => $is_published,
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		return $this->render_switcher_html( $items, $style );
	}

	/**
	 * スイッチャーの HTML レンダリング.
	 *
	 * @param array  $items 言語アイテム配列.
	 * @param string $style 表示スタイル.
	 * @return string HTML.
	 */
	private function render_switcher_html( array $items, string $style ): string {
		$html = '<nav class="mmp-lang-switcher mmp-lang-switcher--' . esc_attr( $style ) . '" aria-label="' . esc_attr__( '言語選択', 'my-multilingual-pages' ) . '">';

		if ( 'dropdown' === $style ) {
			$html .= '<select onchange="if(this.value) window.location.href=this.value;" class="mmp-lang-dropdown">';
			foreach ( $items as $item ) {
				$selected = $item['is_current'] ? ' selected' : '';
				$disabled = ! $item['is_active'] ? ' disabled' : '';
				$html .= '<option value="' . esc_attr( $item['url'] ) . '"' . $selected . $disabled . '>'
					. esc_html( $item['label'] )
					. '</option>';
			}
			$html .= '</select>';
		} else {
			$tag = 'inline' === $style ? 'span' : 'ul';
			$html .= '<' . $tag . ' class="mmp-lang-list">';

			foreach ( $items as $item ) {
				$inner_tag = 'inline' === $style ? 'span' : 'li';
				$classes   = 'mmp-lang-item';
				if ( $item['is_current'] ) {
					$classes .= ' mmp-lang-current';
				}
				if ( ! $item['is_active'] ) {
					$classes .= ' mmp-lang-inactive';
				}

				$html .= '<' . $inner_tag . ' class="' . esc_attr( $classes ) . '">';

				if ( $item['is_active'] && ! $item['is_current'] ) {
					$html .= '<a href="' . esc_url( $item['url'] ) . '" hreflang="' . esc_attr( $item['lang_code'] ) . '">'
						. esc_html( $item['label'] )
						. '</a>';
				} else {
					$html .= '<span>' . esc_html( $item['label'] ) . '</span>';
				}

				$html .= '</' . $inner_tag . '>';
			}

			$html .= '</' . $tag . '>';
		}

		$html .= '</nav>';

		return $html;
	}

	/**
	 * ウィジェット登録.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		register_widget( Language_Switcher_Widget::class );
	}

	/**
	 * フロントエンド用CSS.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		wp_enqueue_style(
			'mmp-frontend',
			MMP_PLUGIN_URL . 'admin/css/frontend.css',
			array(),
			MMP_VERSION
		);
	}

	/**
	 * 翻訳ページのURLを取得（言語プレフィックス付き）.
	 *
	 * @param int    $post_id   投稿ID.
	 * @param string $lang_code 言語コード.
	 * @return string URL.
	 */
	private function get_translated_url( int $post_id, string $lang_code ): string {
		$default_lang = $this->language_manager->get_default_language();

		if ( $lang_code === $default_lang ) {
			return get_permalink( $post_id );
		}

		// 言語プレフィックス付きURL.
		$permalink = get_permalink( $post_id );
		$home_url  = home_url( '/' );

		// home_url からの相対パスを取得.
		$relative = str_replace( $home_url, '', $permalink );

		return home_url( '/' . $lang_code . '/' . ltrim( $relative, '/' ) );
	}

	/**
	 * hreflang 用言語コードを取得.
	 *
	 * @param string $lang_code 内部言語コード.
	 * @return string hreflang コード.
	 */
	private function get_hreflang_code( string $lang_code ): string {
		$map = array(
			'ja'    => 'ja',
			'en'    => 'en',
			'zh'    => 'zh-Hans',
			'ko'    => 'ko',
			'zh-TW' => 'zh-Hant',
		);

		return $map[ $lang_code ] ?? $lang_code;
	}
}
