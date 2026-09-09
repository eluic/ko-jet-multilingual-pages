<?php
/**
 * 管理画面 ダッシュボードウィジェットクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Dashboard
 *
 * 翻訳状況ダッシュボードウィジェットを管理.
 */
class Admin_Dashboard {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager $language_manager 言語マネージャー.
	 */
	public function __construct( Language_Manager $language_manager ) {
		$this->language_manager = $language_manager;

		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * ダッシュボードウィジェット追加.
	 *
	 * @return void
	 */
	public function add_dashboard_widget(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'mmp_translation_status',
			__( '多言語翻訳状況', 'my-multilingual-pages' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * ウィジェット描画.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$default_lang   = $this->language_manager->get_default_language();
		$enabled_langs  = $this->language_manager->get_enabled_languages();

		// マスターページ数を取得.
		$master_pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'meta_key'       => '_mmp_translation_lang',
			'meta_value'     => $default_lang,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$total_masters = count( $master_pages );

		if ( 0 === $total_masters ) {
			echo '<p>' . esc_html__( '翻訳対象のページがまだありません。', 'my-multilingual-pages' ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">'
				. esc_html__( '固定ページ一覧へ', 'my-multilingual-pages' ) . '</a></p>';
			return;
		}

		echo '<table class="widefat striped" style="border:0;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( '言語', 'my-multilingual-pages' ) . '</th>';
		echo '<th style="text-align:center;">' . esc_html__( '翻訳済み', 'my-multilingual-pages' ) . '</th>';
		echo '<th style="text-align:center;">' . esc_html__( '要更新', 'my-multilingual-pages' ) . '</th>';
		echo '<th style="text-align:center;">' . esc_html__( '未翻訳', 'my-multilingual-pages' ) . '</th>';
		echo '<th style="text-align:center;">' . esc_html__( '進捗', 'my-multilingual-pages' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $enabled_langs as $lang_code ) {
			$stats = $this->get_language_stats( $master_pages, $lang_code );
			$percent = $total_masters > 0 ? round( ( $stats['translated'] / $total_masters ) * 100 ) : 0;

			echo '<tr>';
			echo '<td>' . esc_html( $this->language_manager->get_language_label( $lang_code ) ) . '</td>';
			echo '<td style="text-align:center;"><span style="color:#00a32a;">' . esc_html( (string) $stats['translated'] ) . '</span></td>';
			echo '<td style="text-align:center;"><span style="color:#dba617;">' . esc_html( (string) $stats['needs_update'] ) . '</span></td>';
			echo '<td style="text-align:center;"><span style="color:#d63638;">' . esc_html( (string) $stats['not_translated'] ) . '</span></td>';
			echo '<td style="text-align:center;">';
			echo '<div style="background:#f0f0f1;border-radius:3px;height:16px;position:relative;">';
			echo '<div style="background:#00a32a;border-radius:3px;height:100%;width:' . esc_attr( $percent . '%' ) . ';"></div>';
			echo '</div>';
			echo '<small>' . esc_html( $percent . '%' ) . '</small>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// 要更新ページの通知.
		$needs_update_pages = $this->get_needs_update_pages();
		if ( ! empty( $needs_update_pages ) ) {
			echo '<div style="margin-top:12px;padding:8px 12px;background:#fff3cd;border-left:4px solid #dba617;">';
			echo '<strong>' . esc_html__( '⚠ 翻訳更新が必要なページ:', 'my-multilingual-pages' ) . '</strong>';
			echo '<ul style="margin:4px 0 0 16px;">';
			foreach ( array_slice( $needs_update_pages, 0, 10 ) as $page ) {
				printf(
					'<li><a href="%s">%s</a> (%s)</li>',
					esc_url( get_edit_post_link( $page['id'] ) ),
					esc_html( $page['title'] ),
					esc_html( $page['lang'] )
				);
			}
			if ( count( $needs_update_pages ) > 10 ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %d: remaining pages count */
						__( 'ほか %d ページ', 'my-multilingual-pages' ),
						count( $needs_update_pages ) - 10
					)
				) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	/**
	 * 指定言語の翻訳統計を取得.
	 *
	 * @param int[]  $master_page_ids マスターページIDの配列.
	 * @param string $lang_code       言語コード.
	 * @return array{translated: int, needs_update: int, not_translated: int}
	 */
	private function get_language_stats( array $master_page_ids, string $lang_code ): array {
		$stats = array(
			'translated'     => 0,
			'needs_update'   => 0,
			'not_translated' => 0,
		);

		foreach ( $master_page_ids as $master_id ) {
			$status = $this->language_manager->get_translation_status( (int) $master_id, $lang_code );
			if ( isset( $stats[ $status ] ) ) {
				++$stats[ $status ];
			}
		}

		return $stats;
	}

	/**
	 * 要更新ページ一覧を取得.
	 *
	 * @return array<array{id: int, title: string, lang: string}>
	 */
	private function get_needs_update_pages(): array {
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'meta_key'       => '_mmp_needs_update',
			'meta_value'     => '1',
			'posts_per_page' => 50,
		) );

		$result = array();
		foreach ( $pages as $page ) {
			$lang = $this->language_manager->get_post_language( $page->ID );
			$result[] = array(
				'id'    => $page->ID,
				'title' => $page->post_title,
				'lang'  => $this->language_manager->get_language_label( $lang ),
			);
		}

		return $result;
	}
}
