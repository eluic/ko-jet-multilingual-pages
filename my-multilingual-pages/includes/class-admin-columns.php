<?php
/**
 * 管理画面 カスタムカラムクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Columns
 *
 * 固定ページ一覧に言語カラムと翻訳ステータスを表示.
 */
class Admin_Columns {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager $language_manager 言語マネージャー.
	 */
	public function __construct( Language_Manager $language_manager ) {
		$this->language_manager = $language_manager;

		add_filter( 'manage_pages_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-page_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_language_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_language' ) );
		add_action( 'admin_head', array( $this, 'column_styles' ) );
	}

	/**
	 * カスタムカラムを追加.
	 *
	 * @param array $columns カラム配列.
	 * @return array
	 */
	public function add_columns( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['mmp_language']     = __( '言語', 'my-multilingual-pages' );
				$new_columns['mmp_translations'] = __( '翻訳状況', 'my-multilingual-pages' );
			}
		}
		return $new_columns;
	}

	/**
	 * カラム内容を描画.
	 *
	 * @param string $column_name カラム名.
	 * @param int    $post_id     投稿ID.
	 * @return void
	 */
	public function render_column( string $column_name, int $post_id ): void {
		if ( 'mmp_language' === $column_name ) {
			$lang = $this->language_manager->get_post_language( $post_id );
			if ( $lang ) {
				echo esc_html( $this->language_manager->get_language_label( $lang ) );
				if ( $lang === $this->language_manager->get_default_language() ) {
					echo ' <span class="mmp-col-badge mmp-col-badge-master">M</span>';
				}
			} else {
				echo '<span class="mmp-col-unset">—</span>';
			}
		}

		if ( 'mmp_translations' === $column_name ) {
			$group_id = $this->language_manager->get_translation_group_id( $post_id );
			if ( empty( $group_id ) ) {
				echo '<span class="mmp-col-unset">—</span>';
				return;
			}

			$enabled = $this->language_manager->get_enabled_languages();
			$post_lang = $this->language_manager->get_post_language( $post_id );

			foreach ( $enabled as $lang_code ) {
				if ( $lang_code === $post_lang ) {
					continue;
				}

				$status = $this->language_manager->get_translation_status( $post_id, $lang_code );
				$info   = $this->language_manager->get_language_info( $lang_code );
				$flag   = $info ? $info['flag'] : $lang_code;

				$css_class = match ( $status ) {
					'translated'     => 'mmp-col-status-ok',
					'needs_update'   => 'mmp-col-status-warning',
					'not_translated' => 'mmp-col-status-none',
					default          => 'mmp-col-status-none',
				};

				$title = match ( $status ) {
					'translated'     => __( '翻訳済み', 'my-multilingual-pages' ),
					'needs_update'   => __( '要更新', 'my-multilingual-pages' ),
					'not_translated' => __( '未翻訳', 'my-multilingual-pages' ),
					default          => '',
				};

				printf(
					'<span class="mmp-col-status %s" title="%s">%s</span> ',
					esc_attr( $css_class ),
					esc_attr( $title ),
					esc_html( $flag )
				);
			}
		}
	}

	/**
	 * ソート可能カラム.
	 *
	 * @param array $columns カラム配列.
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['mmp_language'] = 'mmp_language';
		return $columns;
	}

	/**
	 * 言語フィルター追加.
	 *
	 * @param string $post_type 投稿タイプ.
	 * @return void
	 */
	public function add_language_filter( string $post_type ): void {
		if ( 'page' !== $post_type ) {
			return;
		}

		$all_languages = $this->language_manager->get_all_languages();
		$selected      = sanitize_text_field( wp_unslash( $_GET['mmp_language'] ?? '' ) );

		echo '<select name="mmp_language">';
		echo '<option value="">' . esc_html__( 'すべての言語', 'my-multilingual-pages' ) . '</option>';

		foreach ( $all_languages as $code ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $this->language_manager->get_language_label( $code ) )
			);
		}

		// 翻訳ステータスフィルター.
		$status_filter = sanitize_text_field( wp_unslash( $_GET['mmp_status'] ?? '' ) );
		echo '</select>';

		echo '<select name="mmp_status">';
		echo '<option value="">' . esc_html__( 'すべてのステータス', 'my-multilingual-pages' ) . '</option>';
		echo '<option value="needs_update" ' . selected( $status_filter, 'needs_update', false ) . '>' . esc_html__( '要更新', 'my-multilingual-pages' ) . '</option>';
		echo '<option value="not_translated" ' . selected( $status_filter, 'not_translated', false ) . '>' . esc_html__( '未翻訳あり', 'my-multilingual-pages' ) . '</option>';
		echo '</select>';
	}

	/**
	 * 言語でフィルタリング.
	 *
	 * @param \WP_Query $query クエリ.
	 * @return void
	 */
	public function filter_by_language( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-page' !== $screen->id ) {
			return;
		}

		$lang = sanitize_text_field( wp_unslash( $_GET['mmp_language'] ?? '' ) );
		if ( ! empty( $lang ) ) {
			$meta_query = $query->get( 'meta_query' ) ?: array();
			$meta_query[] = array(
				'key'   => '_mmp_translation_lang',
				'value' => $lang,
			);
			$query->set( 'meta_query', $meta_query );
		}

		$status = sanitize_text_field( wp_unslash( $_GET['mmp_status'] ?? '' ) );
		if ( 'needs_update' === $status ) {
			$meta_query = $query->get( 'meta_query' ) ?: array();
			$meta_query[] = array(
				'key'   => '_mmp_needs_update',
				'value' => '1',
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * カラム用CSS.
	 *
	 * @return void
	 */
	public function column_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-page' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-mmp_language { width: 100px; }
			.column-mmp_translations { width: 150px; }
			.mmp-col-badge { display: inline-block; background: #2271b1; color: #fff; border-radius: 3px; padding: 0 4px; font-size: 10px; vertical-align: middle; }
			.mmp-col-badge-master { background: #d63638; }
			.mmp-col-unset { color: #999; }
			.mmp-col-status { display: inline-block; margin-right: 2px; cursor: default; }
			.mmp-col-status-ok { opacity: 1; }
			.mmp-col-status-warning { filter: grayscale(50%); border-bottom: 2px solid #dba617; }
			.mmp-col-status-none { opacity: 0.3; }
		</style>
		<?php
	}
}
