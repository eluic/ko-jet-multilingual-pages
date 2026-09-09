<?php
/**
 * Plugin Name:       My Multilingual Pages
 * Plugin URI:        https://example.com/my-multilingual-pages
 * Description:       日本語の固定ページをマスターとして管理し、各言語の翻訳ページを自動生成・同期する多言語翻訳管理プラグイン。
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-multilingual-pages
 * Domain Path:       /languages
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// プラグイン定数.
define( 'MMP_VERSION', '1.0.0' );
define( 'MMP_PLUGIN_FILE', __FILE__ );
define( 'MMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MMP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MMP_TEXT_DOMAIN', 'my-multilingual-pages' );

// オートローダー.
spl_autoload_register(
	function ( string $class_name ): void {
		$prefix    = 'MMP\\';
		$base_dir  = MMP_PLUGIN_DIR . 'includes/';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$parts          = explode( '\\', $relative_class );
		$class_file     = array_pop( $parts );

		// クラス名をファイル名に変換: Language_Manager -> class-language-manager.php
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_file ) ) . '.php';

		// インターフェースの場合.
		if ( str_starts_with( $class_file, 'Translator_Interface' ) || str_ends_with( $class_file, '_Interface' ) ) {
			$file_name = 'interface-' . str_replace( '_', '-', strtolower( str_replace( '_Interface', '', $class_file ) ) ) . '.php';
		}

		// サブディレクトリ対応.
		$sub_dir = '';
		if ( ! empty( $parts ) ) {
			$sub_dir = strtolower( implode( '/', $parts ) ) . '/';
		}

		$file = $base_dir . $sub_dir . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * プラグインのメインインスタンスを返す.
 *
 * @return MMP\Plugin
 */
function mmp(): MMP\Plugin {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new MMP\Plugin();
	}
	return $instance;
}

// プラグイン有効化・無効化フック.
register_activation_hook( __FILE__, array( mmp(), 'activate' ) );
register_deactivation_hook( __FILE__, array( mmp(), 'deactivate' ) );

// 初期化.
add_action( 'plugins_loaded', array( mmp(), 'init' ) );
