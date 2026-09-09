<?php
/**
 * 翻訳同期クラス.
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
 * Class Translation_Sync
 *
 * 日本語ページ更新時の翻訳ページ同期処理を管理.
 */
class Translation_Sync {

	/** @var Language_Manager */
	private Language_Manager $language_manager;

	/** @var Translator_Factory */
	private Translator_Factory $translator_factory;

	/** @var bool 再帰呼び出し防止フラグ */
	private bool $is_syncing = false;

	/**
	 * コンストラクタ.
	 *
	 * @param Language_Manager   $language_manager   言語マネージャー.
	 * @param Translator_Factory $translator_factory 翻訳ファクトリー.
	 */
	public function __construct( Language_Manager $language_manager, Translator_Factory $translator_factory ) {
		$this->language_manager   = $language_manager;
		$this->translator_factory = $translator_factory;

		add_action( 'save_post_page', array( $this, 'on_page_save' ), 20, 2 );
	}

	/**
	 * ページ保存時のフック.
	 *
	 * @param int      $post_id 投稿ID.
	 * @param \WP_Post $post    投稿オブジェクト.
	 * @return void
	 */
	public function on_page_save( int $post_id, \WP_Post $post ): void {
		// 再帰防止.
		if ( $this->is_syncing ) {
			return;
		}

		// オートセーブ除外.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// リビジョン除外.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// マスター（日本語）ページのみ対象.
		$post_lang = $this->language_manager->get_post_language( $post_id );
		if ( $post_lang !== $this->language_manager->get_default_language() ) {
			return;
		}

		// 公開済みページの更新時のみ.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// 翻訳グループ取得.
		$group_id = $this->language_manager->get_translation_group_id( $post_id );
		if ( empty( $group_id ) ) {
			return;
		}

		$translations = $this->language_manager->get_translation_group( $group_id );

		// 更新モード判定（ページ個別 → グローバル設定）.
		$update_mode = get_post_meta( $post_id, '_mmp_update_mode', true );
		if ( empty( $update_mode ) ) {
			$update_mode = get_option( 'mmp_update_mode', 'notify' );
		}

		foreach ( $translations as $lang => $trans_id ) {
			// マスター自身はスキップ.
			if ( $lang === $this->language_manager->get_default_language() ) {
				continue;
			}

			if ( 'auto' === $update_mode ) {
				// 手動編集チェック.
				if ( $this->is_manually_edited( $trans_id ) ) {
					// 手動編集済みの場合は通知モードにフォールバック.
					$this->set_needs_update( $trans_id );
				} else {
					// キューに追加（即時ではなくWP-Cron処理）.
					$this->enqueue_sync( $post_id, $trans_id, $lang );
				}
			} else {
				// 通知モード: フラグを立てるのみ.
				$this->set_needs_update( $trans_id );
			}
		}
	}

	/**
	 * 翻訳ページが手動編集されているか判定.
	 *
	 * @param int $post_id 投稿ID.
	 * @return bool
	 */
	private function is_manually_edited( int $post_id ): bool {
		$last_hash = get_post_meta( $post_id, '_mmp_last_translated_hash', true );
		if ( empty( $last_hash ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$current_hash = md5( $post->post_content );

		return $last_hash !== $current_hash;
	}

	/**
	 * 要更新フラグを設定.
	 *
	 * @param int $post_id 投稿ID.
	 * @return void
	 */
	private function set_needs_update( int $post_id ): void {
		update_post_meta( $post_id, '_mmp_needs_update', '1' );
	}

	/**
	 * 同期キューに追加.
	 *
	 * @param int    $source_post_id ソース投稿ID.
	 * @param int    $target_post_id ターゲット投稿ID.
	 * @param string $target_lang    ターゲット言語.
	 * @return void
	 */
	private function enqueue_sync( int $source_post_id, int $target_post_id, string $target_lang ): void {
		$queue = get_option( 'mmp_translation_queue', array() );

		$queue[] = array(
			'source_post_id' => $source_post_id,
			'target_post_id' => $target_post_id,
			'target_lang'    => $target_lang,
			'created_at'     => current_time( 'mysql' ),
			'status'         => 'pending',
			'attempts'       => 0,
		);

		update_option( 'mmp_translation_queue', $queue, false );
	}

	/**
	 * 翻訳の同期実行.
	 *
	 * @param int    $source_post_id ソース投稿ID.
	 * @param int    $target_post_id ターゲット投稿ID.
	 * @param string $target_lang    ターゲット言語.
	 * @return bool 成功したか.
	 */
	public function sync_translation( int $source_post_id, int $target_post_id, string $target_lang ): bool {
		$this->is_syncing = true;

		$source_post = get_post( $source_post_id );
		$target_post = get_post( $target_post_id );

		if ( ! $source_post || ! $target_post ) {
			$this->is_syncing = false;
			return false;
		}

		$source_lang = $this->language_manager->get_default_language();
		$translator  = $this->translator_factory->get_for_language( $target_lang );

		if ( ! $translator ) {
			$this->is_syncing = false;
			return false;
		}

		// タイトル翻訳.
		$translated_title = $translator->translate( $source_post->post_title, $source_lang, $target_lang );
		if ( false === $translated_title ) {
			$this->is_syncing = false;
			return false;
		}

		// 本文翻訳（ブロック構造を保持）.
		$translated_content = $this->translate_content( $source_post->post_content, $source_lang, $target_lang, $translator );
		if ( false === $translated_content ) {
			$this->is_syncing = false;
			return false;
		}

		// 更新.
		$result = wp_update_post(
			array(
				'ID'           => $target_post_id,
				'post_title'   => $translated_title,
				'post_content' => $translated_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			$this->is_syncing = false;
			return false;
		}

		// ハッシュ更新 & 要更新フラグクリア.
		update_post_meta( $target_post_id, '_mmp_last_translated_hash', md5( $translated_content ) );
		delete_post_meta( $target_post_id, '_mmp_needs_update' );

		$this->is_syncing = false;
		return true;
	}

	/**
	 * コンテンツ翻訳.
	 *
	 * @param string                                       $content     元のコンテンツ.
	 * @param string                                       $source_lang ソース言語.
	 * @param string                                       $target_lang ターゲット言語.
	 * @param \MMP\Translators\Translator_Interface         $translator  翻訳エンジン.
	 * @return string|false 翻訳済みコンテンツ、失敗時は false.
	 */
	private function translate_content( string $content, string $source_lang, string $target_lang, \MMP\Translators\Translator_Interface $translator ): string|false {
		if ( empty( $content ) ) {
			return '';
		}

		$blocks = parse_blocks( $content );

		if ( empty( $blocks ) ) {
			return $translator->translate( $content, $source_lang, $target_lang );
		}

		$translated_blocks = $this->translate_blocks( $blocks, $source_lang, $target_lang, $translator );
		if ( false === $translated_blocks ) {
			return false;
		}

		return serialize_blocks( $translated_blocks );
	}

	/**
	 * ブロック配列を翻訳.
	 *
	 * @param array                                   $blocks      ブロック配列.
	 * @param string                                  $source_lang ソース言語.
	 * @param string                                  $target_lang ターゲット言語.
	 * @param \MMP\Translators\Translator_Interface    $translator  翻訳エンジン.
	 * @return array|false 翻訳済みブロック配列、失敗時は false.
	 */
	private function translate_blocks( array $blocks, string $source_lang, string $target_lang, \MMP\Translators\Translator_Interface $translator ): array|false {
		foreach ( $blocks as &$block ) {
			if ( ! empty( $block['innerHTML'] ) && '' !== trim( $block['innerHTML'] ) ) {
				$translated = $translator->translate( $block['innerHTML'], $source_lang, $target_lang );
				if ( false === $translated ) {
					return false;
				}
				$block['innerHTML'] = $translated;
			}

			if ( ! empty( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as &$inner ) {
					if ( null !== $inner && '' !== trim( $inner ) ) {
						$translated = $translator->translate( $inner, $source_lang, $target_lang );
						if ( false === $translated ) {
							return false;
						}
						$inner = $translated;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = $this->translate_blocks( $block['innerBlocks'], $source_lang, $target_lang, $translator );
				if ( false === $result ) {
					return false;
				}
				$block['innerBlocks'] = $result;
			}
		}

		return $blocks;
	}

	/**
	 * AJAX: 手動で翻訳同期.
	 *
	 * @return void
	 */
	public function ajax_sync_translation(): void {
		check_ajax_referer( 'mmp_metabox_nonce', 'nonce' );

		$source_post_id = (int) ( $_POST['source_post_id'] ?? 0 );
		$target_post_id = (int) ( $_POST['target_post_id'] ?? 0 );
		$target_lang    = sanitize_text_field( wp_unslash( $_POST['target_lang'] ?? '' ) );

		if ( ! $source_post_id || ! $target_post_id || ! $target_lang ) {
			wp_send_json_error( array( 'message' => __( 'パラメータが不足しています', 'my-multilingual-pages' ) ) );
		}

		if ( ! current_user_can( 'edit_page', $target_post_id ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません', 'my-multilingual-pages' ) ) );
		}

		$success = $this->sync_translation( $source_post_id, $target_post_id, $target_lang );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( '翻訳を更新しました', 'my-multilingual-pages' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( '翻訳の更新に失敗しました', 'my-multilingual-pages' ) ) );
		}
	}
}
