<?php
/**
 * 翻訳キュー処理クラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Translation_Queue
 *
 * WP-Cron によるキューベースの翻訳処理を管理.
 * 大量ページ更新時のタイムアウトを防止する.
 */
class Translation_Queue {

	/** @var Translation_Sync */
	private Translation_Sync $translation_sync;

	/** @var int 1回の Cron 実行で処理する最大件数 */
	private const BATCH_SIZE = 5;

	/** @var int 最大リトライ回数 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * コンストラクタ.
	 *
	 * @param Translation_Sync $translation_sync 翻訳同期.
	 */
	public function __construct( Translation_Sync $translation_sync ) {
		$this->translation_sync = $translation_sync;
	}

	/**
	 * Cron フック登録.
	 *
	 * @return void
	 */
	public function register_cron(): void {
		add_action( 'mmp_process_translation_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * キューを処理.
	 *
	 * @return void
	 */
	public function process_queue(): void {
		$queue = get_option( 'mmp_translation_queue', array() );

		if ( empty( $queue ) ) {
			return;
		}

		$processed = 0;

		foreach ( $queue as $index => &$item ) {
			if ( $processed >= self::BATCH_SIZE ) {
				break;
			}

			if ( 'pending' !== $item['status'] ) {
				continue;
			}

			// 最大リトライ回数超過.
			if ( $item['attempts'] >= self::MAX_ATTEMPTS ) {
				$item['status']  = 'failed';
				$item['message'] = __( '最大リトライ回数を超過しました', 'my-multilingual-pages' );
				continue;
			}

			$item['attempts'] += 1;
			$item['status']    = 'processing';

			// キュー状態を保存（途中状態を残す）.
			update_option( 'mmp_translation_queue', $queue, false );

			// 翻訳同期実行.
			$success = $this->translation_sync->sync_translation(
				$item['source_post_id'],
				$item['target_post_id'],
				$item['target_lang']
			);

			if ( $success ) {
				$item['status']       = 'completed';
				$item['completed_at'] = current_time( 'mysql' );
			} else {
				$item['status'] = 'pending'; // リトライ可能.
				$item['message'] = __( '翻訳に失敗しました、次回再試行します', 'my-multilingual-pages' );

				// 最大リトライに達した場合は通知モードにフォールバック.
				if ( $item['attempts'] >= self::MAX_ATTEMPTS ) {
					$item['status'] = 'failed';
					update_post_meta( $item['target_post_id'], '_mmp_needs_update', '1' );
				}
			}

			++$processed;
		}

		// 完了・失敗したジョブを削除（24時間以上経過したもの）.
		$queue = array_values(
			array_filter(
				$queue,
				function ( array $item ): bool {
					if ( in_array( $item['status'], array( 'completed', 'failed' ), true ) ) {
						$completed_time = strtotime( $item['completed_at'] ?? $item['created_at'] );
						return ( time() - $completed_time ) < DAY_IN_SECONDS;
					}
					return true;
				}
			)
		);

		update_option( 'mmp_translation_queue', $queue, false );
	}

	/**
	 * キューの状態を取得.
	 *
	 * @return array{pending: int, processing: int, completed: int, failed: int}
	 */
	public function get_queue_stats(): array {
		$queue = get_option( 'mmp_translation_queue', array() );

		$stats = array(
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
		);

		foreach ( $queue as $item ) {
			$status = $item['status'] ?? 'pending';
			if ( isset( $stats[ $status ] ) ) {
				++$stats[ $status ];
			}
		}

		return $stats;
	}

	/**
	 * キューをクリア.
	 *
	 * @return void
	 */
	public function clear_queue(): void {
		update_option( 'mmp_translation_queue', array(), false );
	}
}
