<?php
/**
 * Gutenberg ブロック登録クラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Registrar
 *
 * カスタム Gutenberg ブロックの登録を管理.
 */
class Block_Registrar {

	/**
	 * コンストラクタ.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * ブロック登録.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		// 言語スイッチャーブロック.
		if ( file_exists( MMP_PLUGIN_DIR . 'blocks/lang-switcher/block.json' ) ) {
			register_block_type( MMP_PLUGIN_DIR . 'blocks/lang-switcher' );
		}
	}
}
