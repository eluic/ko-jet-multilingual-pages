<?php
/**
 * 管理画面 翻訳メタボックスクラス.
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
 * Class Admin_Metabox
 *
 * 固定ページ編集画面の「多言語翻訳」メタボックスを管理.
 */
class Admin_Metabox {

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

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * メタボックス追加.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'mmp-translation-metabox',
			__( '多言語翻訳', 'my-multilingual-pages' ),
			array( $this, 'render_meta_box' ),
			'page',
			'side',
			'high'
		);
	}

	/**
	 * 管理画面用アセット.
	 *
	 * @param string $hook_suffix 画面フック名.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'mmp-admin-metabox',
			MMP_PLUGIN_URL . 'admin/css/metabox.css',
			array(),
			MMP_VERSION
		);

		wp_enqueue_script(
			'mmp-admin-metabox',
			MMP_PLUGIN_URL . 'admin/js/metabox.js',
			array( 'jquery' ),
			MMP_VERSION,
			true
		);

		wp_localize_script( 'mmp-admin-metabox', 'mmpMetabox', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mmp_metabox_nonce' ),
			'i18n'    => array(
				'creating'   => __( '翻訳ページを作成中...', 'my-multilingual-pages' ),
				'created'    => __( '翻訳ページを作成しました', 'my-multilingual-pages' ),
				'error'      => __( 'エラーが発生しました', 'my-multilingual-pages' ),
				'confirm'    => __( 'この言語の翻訳ページを作成しますか？', 'my-multilingual-pages' ),
			),
		) );
	}

	/**
	 * メタボックス描画.
	 *
	 * @param \WP_Post $post 投稿オブジェクト.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'mmp_metabox_save', 'mmp_metabox_nonce' );

		$post_lang = $this->language_manager->get_post_language( $post->ID );
		$group_id  = $this->language_manager->get_translation_group_id( $post->ID );
		$default_lang = $this->language_manager->get_default_language();
		$enabled_langs = $this->language_manager->get_enabled_languages();

		// まだ言語が設定されていない場合（新規ページ等）.
		$is_master = ( empty( $post_lang ) || $post_lang === $default_lang );

		// 翻訳グループ.
		$translations = array();
		if ( $group_id ) {
			$translations = $this->language_manager->get_translation_group( $group_id );
		}

		// 個別更新モード.
		$page_update_mode = get_post_meta( $post->ID, '_mmp_update_mode', true );
		$global_mode = get_option( 'mmp_update_mode', 'notify' );
		?>
		<div class="mmp-metabox-content">
			<!-- 現在の言語表示 -->
			<p class="mmp-current-lang">
				<strong><?php esc_html_e( '言語:', 'my-multilingual-pages' ); ?></strong>
				<?php
				if ( $is_master ) {
					echo esc_html( $this->language_manager->get_language_label( $default_lang ) );
					echo ' <span class="mmp-badge mmp-badge-master">' . esc_html__( 'マスター', 'my-multilingual-pages' ) . '</span>';
				} else {
					echo esc_html( $this->language_manager->get_language_label( $post_lang ) );
					$source_id = get_post_meta( $post->ID, '_mmp_source_post_id', true );
					if ( $source_id ) {
						echo ' <a href="' . esc_url( get_edit_post_link( (int) $source_id ) ) . '">'
							. esc_html__( '→ 元ページ', 'my-multilingual-pages' ) . '</a>';
					}
				}
				?>
			</p>

			<?php if ( $this->language_manager->needs_update( $post->ID ) ) : ?>
				<div class="mmp-notice mmp-notice-warning">
					<strong><?php esc_html_e( '⚠ 元ページが更新されました。翻訳の確認が必要です。', 'my-multilingual-pages' ); ?></strong>
				</div>
			<?php endif; ?>

			<!-- 隠しフィールド -->
			<input type="hidden" name="mmp_post_lang" value="<?php echo esc_attr( $post_lang ?: $default_lang ); ?>">

			<!-- 翻訳ページ一覧 -->
			<div class="mmp-translations-list">
				<h4><?php esc_html_e( '翻訳ページ', 'my-multilingual-pages' ); ?></h4>
				<?php foreach ( $enabled_langs as $lang_code ) : ?>
					<?php
					$lang_label = $this->language_manager->get_language_label( $lang_code );
					$trans_id   = $translations[ $lang_code ] ?? null;
					?>
					<div class="mmp-translation-row" data-lang="<?php echo esc_attr( $lang_code ); ?>">
						<span class="mmp-lang-name"><?php echo esc_html( $lang_label ); ?></span>
						<?php if ( $trans_id ) : ?>
							<?php
							$trans_post = get_post( $trans_id );
							$status     = $trans_post ? $trans_post->post_status : 'deleted';
							$needs_upd  = $this->language_manager->needs_update( $trans_id );
							?>
							<span class="mmp-status-badge <?php echo $needs_upd ? 'mmp-status-warning' : 'mmp-status-ok'; ?>">
								<?php
								if ( $needs_upd ) {
									esc_html_e( '要更新', 'my-multilingual-pages' );
								} else {
									echo esc_html( get_post_status_object( $status )->label ?? $status );
								}
								?>
							</span>
							<a href="<?php echo esc_url( get_edit_post_link( $trans_id ) ); ?>" class="mmp-edit-link">
								<?php esc_html_e( '編集', 'my-multilingual-pages' ); ?>
							</a>
						<?php elseif ( $is_master && $post->ID ) : ?>
							<button type="button"
								class="button button-small mmp-create-translation"
								data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
								data-lang="<?php echo esc_attr( $lang_code ); ?>">
								<?php esc_html_e( 'ページを作成', 'my-multilingual-pages' ); ?>
							</button>
						<?php else : ?>
							<span class="mmp-status-badge mmp-status-none"><?php esc_html_e( '未作成', 'my-multilingual-pages' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- 個別更新モード -->
			<?php if ( $is_master ) : ?>
			<div class="mmp-update-mode">
				<h4><?php esc_html_e( '更新モード（個別設定）', 'my-multilingual-pages' ); ?></h4>
				<select name="mmp_page_update_mode" style="width:100%;">
					<option value="" <?php selected( $page_update_mode, '' ); ?>>
						<?php
						/* translators: %s: global update mode name */
						printf( esc_html__( 'グローバル設定に従う（%s）', 'my-multilingual-pages' ), esc_html( 'auto' === $global_mode ? __( '自動', 'my-multilingual-pages' ) : __( '通知', 'my-multilingual-pages' ) ) );
						?>
					</option>
					<option value="auto" <?php selected( $page_update_mode, 'auto' ); ?>>
						<?php esc_html_e( '自動モード', 'my-multilingual-pages' ); ?>
					</option>
					<option value="notify" <?php selected( $page_update_mode, 'notify' ); ?>>
						<?php esc_html_e( '通知モード', 'my-multilingual-pages' ); ?>
					</option>
				</select>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * メタボックス保存処理.
	 *
	 * @param int      $post_id 投稿ID.
	 * @param \WP_Post $post    投稿オブジェクト.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		// nonce 検証.
		if ( ! isset( $_POST['mmp_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mmp_metabox_nonce'] ) ), 'mmp_metabox_save' ) ) {
			return;
		}

		// 権限チェック.
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		// オートセーブ除外.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 言語メタの保存.
		$post_lang = sanitize_text_field( wp_unslash( $_POST['mmp_post_lang'] ?? '' ) );
		if ( $post_lang ) {
			$current_lang = $this->language_manager->get_post_language( $post_id );
			if ( empty( $current_lang ) ) {
				// 新規ページ: 翻訳グループを生成.
				$group_id = $this->language_manager->generate_group_id();
				$this->language_manager->set_post_translation( $post_id, $group_id, $post_lang );
			}
		}

		// 個別更新モード.
		$update_mode = sanitize_text_field( wp_unslash( $_POST['mmp_page_update_mode'] ?? '' ) );
		update_post_meta( $post_id, '_mmp_update_mode', $update_mode );
	}

	/**
	 * AJAX: 翻訳ページ作成.
	 *
	 * @return void
	 */
	public function ajax_create_translation(): void {
		check_ajax_referer( 'mmp_metabox_nonce', 'nonce' );

		$source_post_id = (int) ( $_POST['post_id'] ?? 0 );
		$target_lang    = sanitize_text_field( wp_unslash( $_POST['lang'] ?? '' ) );

		if ( ! $source_post_id || ! $target_lang ) {
			wp_send_json_error( array( 'message' => __( 'パラメータが不足しています', 'my-multilingual-pages' ) ) );
		}

		if ( ! current_user_can( 'edit_page', $source_post_id ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません', 'my-multilingual-pages' ) ) );
		}

		$source_post = get_post( $source_post_id );
		if ( ! $source_post || 'page' !== $source_post->post_type ) {
			wp_send_json_error( array( 'message' => __( '元ページが見つかりません', 'my-multilingual-pages' ) ) );
		}

		// 既存翻訳チェック.
		$existing = $this->language_manager->get_translated_post( $source_post_id, $target_lang );
		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'この言語のページは既に存在します', 'my-multilingual-pages' ) ) );
		}

		// 翻訳グループID確認・生成.
		$group_id = $this->language_manager->get_translation_group_id( $source_post_id );
		if ( empty( $group_id ) ) {
			$group_id = $this->language_manager->generate_group_id();
			$this->language_manager->set_post_translation(
				$source_post_id,
				$group_id,
				$this->language_manager->get_default_language()
			);
		}

		// 翻訳エンジン取得.
		$translator = $this->translator_factory->get_for_language( $target_lang );
		$source_lang = $this->language_manager->get_default_language();

		// タイトル翻訳.
		$translated_title = $source_post->post_title;
		if ( $translator ) {
			$result = $translator->translate( $source_post->post_title, $source_lang, $target_lang );
			if ( false !== $result ) {
				$translated_title = $result;
			}
		}

		// 本文翻訳（ブロック構造を保持）.
		$translated_content = $this->translate_block_content( $source_post->post_content, $source_lang, $target_lang, $translator );

		// スラッグ生成.
		$translated_slug = sanitize_title( $translated_title );
		if ( empty( $translated_slug ) ) {
			$translated_slug = $source_post->post_name . '-' . $target_lang;
		}

		// 翻訳ページ作成.
		$new_post_id = wp_insert_post(
			array(
				'post_title'   => $translated_title,
				'post_content' => $translated_content,
				'post_name'    => $translated_slug,
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
				'post_parent'  => $source_post->post_parent,
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			wp_send_json_error( array( 'message' => $new_post_id->get_error_message() ) );
		}

		// メタデータ設定.
		$this->language_manager->set_post_translation( $new_post_id, $group_id, $target_lang );
		update_post_meta( $new_post_id, '_mmp_source_post_id', $source_post_id );
		update_post_meta( $new_post_id, '_mmp_last_translated_hash', md5( $translated_content ) );

		// アイキャッチ画像の引き継ぎ.
		$thumbnail_id = get_post_thumbnail_id( $source_post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $new_post_id, $thumbnail_id );
		}

		wp_send_json_success( array(
			'message'  => __( '翻訳ページを作成しました', 'my-multilingual-pages' ),
			'post_id'  => $new_post_id,
			'edit_url' => get_edit_post_link( $new_post_id, 'raw' ),
		) );
	}

	/**
	 * ブロックコンテンツを翻訳.
	 *
	 * ブロック構造を維持しつつ、テキスト部分のみを翻訳.
	 *
	 * @param string                                    $content    元のブロックコンテンツ.
	 * @param string                                    $source_lang ソース言語.
	 * @param string                                    $target_lang ターゲット言語.
	 * @param \MMP\Translators\Translator_Interface|null $translator  翻訳エンジン.
	 * @return string 翻訳済みコンテンツ.
	 */
	private function translate_block_content( string $content, string $source_lang, string $target_lang, ?\MMP\Translators\Translator_Interface $translator ): string {
		if ( ! $translator || empty( $content ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );

		if ( empty( $blocks ) ) {
			// ブロック構造でない場合は全文翻訳.
			$result = $translator->translate( $content, $source_lang, $target_lang );
			return false !== $result ? $result : $content;
		}

		$translated_blocks = $this->translate_blocks_recursive( $blocks, $source_lang, $target_lang, $translator );

		return serialize_blocks( $translated_blocks );
	}

	/**
	 * ブロック配列を再帰的に翻訳.
	 *
	 * @param array                                      $blocks      ブロック配列.
	 * @param string                                     $source_lang ソース言語.
	 * @param string                                     $target_lang ターゲット言語.
	 * @param \MMP\Translators\Translator_Interface       $translator  翻訳エンジン.
	 * @return array 翻訳済みブロック配列.
	 */
	private function translate_blocks_recursive( array $blocks, string $source_lang, string $target_lang, \MMP\Translators\Translator_Interface $translator ): array {
		foreach ( $blocks as &$block ) {
			// innerHTML を翻訳.
			if ( ! empty( $block['innerHTML'] ) && '' !== trim( $block['innerHTML'] ) ) {
				$translated = $translator->translate( $block['innerHTML'], $source_lang, $target_lang );
				if ( false !== $translated ) {
					$block['innerHTML'] = $translated;
				}
			}

			// innerContent を翻訳.
			if ( ! empty( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as &$inner ) {
					if ( null !== $inner && '' !== trim( $inner ) ) {
						$translated = $translator->translate( $inner, $source_lang, $target_lang );
						if ( false !== $translated ) {
							$inner = $translated;
						}
					}
				}
			}

			// 内部ブロック（再帰）.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->translate_blocks_recursive( $block['innerBlocks'], $source_lang, $target_lang, $translator );
			}
		}

		return $blocks;
	}
}
