<?php
/**
 * PHPUnit テスト ブートストラップ.
 *
 * WordPress テスト環境のセットアップ手順:
 *
 * 1. WP-CLI をインストール:
 *    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
 *    chmod +x wp-cli.phar
 *    sudo mv wp-cli.phar /usr/local/bin/wp
 *
 * 2. テストスイートをセットアップ:
 *    wp scaffold plugin-tests my-multilingual-pages
 *
 * 3. テスト用データベースとWordPressテストライブラリをインストール:
 *    bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
 *
 * 4. テスト実行:
 *    cd /path/to/wp-content/plugins/my-multilingual-pages
 *    phpunit
 *
 * @package MyMultilingualPages
 */

// WordPress テストスイートへのパス.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// WordPress テストスイートの存在確認.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found at {$_tests_dir}.\n";
	echo "Please set up the WordPress test environment first.\n";
	echo "See: https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/\n";
	exit( 1 );
}

// テスト用関数の読み込み.
require_once $_tests_dir . '/includes/functions.php';

/**
 * テスト対象プラグインのロード.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/my-multilingual-pages.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// WordPress テストスイートの起動.
require $_tests_dir . '/includes/bootstrap.php';
