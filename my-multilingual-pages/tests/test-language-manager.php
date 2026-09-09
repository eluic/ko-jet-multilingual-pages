<?php
/**
 * Language_Manager のユニットテスト.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

/**
 * Class Test_Language_Manager
 *
 * WP_UnitTestCase を使用した Language_Manager のテスト.
 *
 * 実行方法:
 *   1. WordPress テスト環境をセットアップ
 *      https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/
 *   2. `phpunit` を実行
 */
class Test_Language_Manager extends WP_UnitTestCase {

	/** @var MMP\Language_Manager */
	private MMP\Language_Manager $manager;

	/**
	 * テスト前のセットアップ.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// デフォルト設定.
		update_option( 'mmp_default_language', 'ja' );
		update_option( 'mmp_enabled_languages', array( 'en', 'zh', 'ko', 'zh-TW' ) );

		$this->manager = new MMP\Language_Manager();
	}

	/**
	 * テスト後のクリーンアップ.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'mmp_default_language' );
		delete_option( 'mmp_enabled_languages' );
		delete_option( 'mmp_per_lang_settings' );
		parent::tear_down();
	}

	/**
	 * デフォルト言語が日本語であることを検証.
	 *
	 * @return void
	 */
	public function test_get_default_language(): void {
		$this->assertSame( 'ja', $this->manager->get_default_language() );
	}

	/**
	 * 有効言語一覧の取得を検証.
	 *
	 * @return void
	 */
	public function test_get_enabled_languages(): void {
		$languages = $this->manager->get_enabled_languages();

		$this->assertIsArray( $languages );
		$this->assertContains( 'en', $languages );
		$this->assertContains( 'zh', $languages );
		$this->assertContains( 'ko', $languages );
		$this->assertContains( 'zh-TW', $languages );
		$this->assertNotContains( 'ja', $languages );
	}

	/**
	 * 全言語一覧にデフォルト言語が含まれることを検証.
	 *
	 * @return void
	 */
	public function test_get_all_languages(): void {
		$all = $this->manager->get_all_languages();

		$this->assertContains( 'ja', $all );
		$this->assertContains( 'en', $all );
		$this->assertSame( 'ja', $all[0] );
	}

	/**
	 * 言語情報の取得を検証.
	 *
	 * @return void
	 */
	public function test_get_language_info(): void {
		$info = $this->manager->get_language_info( 'en' );

		$this->assertIsArray( $info );
		$this->assertSame( 'English', $info['name'] );
		$this->assertSame( 'English', $info['native_name'] );
		$this->assertSame( '🇺🇸', $info['flag'] );

		// 不明な言語コード.
		$this->assertNull( $this->manager->get_language_info( 'xx' ) );
	}

	/**
	 * 翻訳グループの設定と取得を検証.
	 *
	 * @return void
	 */
	public function test_translation_group(): void {
		// テスト用ページ作成.
		$ja_page = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'テストページ',
			'post_status' => 'publish',
		) );

		$en_page = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Test Page',
			'post_status' => 'draft',
		) );

		// グループIDを生成.
		$group_id = $this->manager->generate_group_id();
		$this->assertNotEmpty( $group_id );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $group_id );

		// 翻訳グループに登録.
		$this->manager->set_post_translation( $ja_page, $group_id, 'ja' );
		$this->manager->set_post_translation( $en_page, $group_id, 'en' );

		// 言語の取得.
		$this->assertSame( 'ja', $this->manager->get_post_language( $ja_page ) );
		$this->assertSame( 'en', $this->manager->get_post_language( $en_page ) );

		// グループID の取得.
		$this->assertSame( $group_id, $this->manager->get_translation_group_id( $ja_page ) );

		// 翻訳グループの取得.
		$group = $this->manager->get_translation_group( $group_id );
		$this->assertArrayHasKey( 'ja', $group );
		$this->assertArrayHasKey( 'en', $group );
		$this->assertSame( $ja_page, $group['ja'] );
		$this->assertSame( $en_page, $group['en'] );

		// 特定言語の翻訳ページ取得.
		$translated = $this->manager->get_translated_post( $ja_page, 'en' );
		$this->assertSame( $en_page, $translated );

		// 存在しない言語.
		$this->assertNull( $this->manager->get_translated_post( $ja_page, 'ko' ) );
	}

	/**
	 * 翻訳ステータスの判定を検証.
	 *
	 * @return void
	 */
	public function test_translation_status(): void {
		$ja_page = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$en_page = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
		) );

		$group_id = $this->manager->generate_group_id();
		$this->manager->set_post_translation( $ja_page, $group_id, 'ja' );
		$this->manager->set_post_translation( $en_page, $group_id, 'en' );

		// 翻訳済み.
		$this->assertSame( 'translated', $this->manager->get_translation_status( $ja_page, 'en' ) );

		// 未翻訳.
		$this->assertSame( 'not_translated', $this->manager->get_translation_status( $ja_page, 'ko' ) );

		// 要更新.
		update_post_meta( $en_page, '_mmp_needs_update', '1' );
		$this->assertSame( 'needs_update', $this->manager->get_translation_status( $ja_page, 'en' ) );
		$this->assertTrue( $this->manager->needs_update( $en_page ) );
	}
}
