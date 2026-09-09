<?php
/**
 * Translator_Factory のユニットテスト.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

/**
 * Class Test_Translator_Factory
 *
 * 翻訳エンジンファクトリーのテスト.
 */
class Test_Translator_Factory extends WP_UnitTestCase {

	/** @var MMP\Translators\Translator_Factory */
	private MMP\Translators\Translator_Factory $factory;

	/**
	 * テスト前のセットアップ.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'mmp_translation_engine', 'deepl' );
		$this->factory = new MMP\Translators\Translator_Factory();
	}

	/**
	 * テスト後のクリーンアップ.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'mmp_translation_engine' );
		delete_option( 'mmp_per_lang_settings' );
		parent::tear_down();
	}

	/**
	 * デフォルトエンジン（DeepL）が取得できることを検証.
	 *
	 * @return void
	 */
	public function test_create_deepl(): void {
		$translator = $this->factory->create( 'deepl' );

		$this->assertNotNull( $translator );
		$this->assertInstanceOf( MMP\Translators\Translator_Interface::class, $translator );
		$this->assertInstanceOf( MMP\Translators\DeepL_Translator::class, $translator );
		$this->assertSame( 'DeepL', $translator->get_name() );
		$this->assertSame( 'deepl', $translator->get_slug() );
	}

	/**
	 * Google 翻訳エンジンが取得できることを検証.
	 *
	 * @return void
	 */
	public function test_create_google(): void {
		$translator = $this->factory->create( 'google' );

		$this->assertNotNull( $translator );
		$this->assertInstanceOf( MMP\Translators\Google_Translator::class, $translator );
		$this->assertSame( 'Google Cloud Translation', $translator->get_name() );
	}

	/**
	 * OpenAI 翻訳エンジンが取得できることを検証.
	 *
	 * @return void
	 */
	public function test_create_openai(): void {
		$translator = $this->factory->create( 'openai' );

		$this->assertNotNull( $translator );
		$this->assertInstanceOf( MMP\Translators\OpenAI_Translator::class, $translator );
		$this->assertSame( 'OpenAI', $translator->get_name() );
	}

	/**
	 * 存在しないエンジンで null が返ることを検証.
	 *
	 * @return void
	 */
	public function test_create_unknown_returns_null(): void {
		$translator = $this->factory->create( 'nonexistent' );
		$this->assertNull( $translator );
	}

	/**
	 * デフォルトエンジンの取得を検証.
	 *
	 * @return void
	 */
	public function test_get_default(): void {
		$translator = $this->factory->get_default();

		$this->assertNotNull( $translator );
		$this->assertSame( 'deepl', $translator->get_slug() );
	}

	/**
	 * 言語別エンジン設定の取得を検証.
	 *
	 * @return void
	 */
	public function test_get_for_language(): void {
		// 英語に Google を設定.
		update_option( 'mmp_per_lang_settings', array(
			'en' => array( 'engine' => 'google', 'auto_translate' => true ),
		) );

		$en_translator = $this->factory->get_for_language( 'en' );
		$this->assertSame( 'google', $en_translator->get_slug() );

		// 設定なしの言語はデフォルト.
		$ko_translator = $this->factory->get_for_language( 'ko' );
		$this->assertSame( 'deepl', $ko_translator->get_slug() );
	}

	/**
	 * 利用可能エンジン一覧の取得を検証.
	 *
	 * @return void
	 */
	public function test_get_available_engines(): void {
		$engines = $this->factory->get_available_engines();

		$this->assertIsArray( $engines );
		$this->assertArrayHasKey( 'deepl', $engines );
		$this->assertArrayHasKey( 'google', $engines );
		$this->assertArrayHasKey( 'openai', $engines );
		$this->assertSame( 'DeepL', $engines['deepl'] );
	}

	/**
	 * カスタムエンジンの登録を検証.
	 *
	 * @return void
	 */
	public function test_register_custom_engine(): void {
		// モック翻訳エンジン用のクラス定義（テスト内のみ）.
		if ( ! class_exists( 'Mock_Translator' ) ) {
			// phpcs:disable
			eval( '
				class Mock_Translator implements MMP\Translators\Translator_Interface {
					public function get_name(): string { return "Mock"; }
					public function get_slug(): string { return "mock"; }
					public function translate( string $text, string $source_lang, string $target_lang ): string|false { return "translated: " . $text; }
					public function translate_batch( array $texts, string $source_lang, string $target_lang ): array|false { return array_map( fn($t) => "translated: " . $t, $texts ); }
					public function validate_api_key( string $api_key ): bool { return true; }
					public function set_api_key( string $api_key ): void {}
				}
			' );
			// phpcs:enable
		}

		$this->factory->register( 'mock', 'Mock_Translator' );

		$translator = $this->factory->create( 'mock' );
		$this->assertNotNull( $translator );
		$this->assertSame( 'Mock', $translator->get_name() );

		// 翻訳テスト.
		$result = $translator->translate( 'Hello', 'en', 'ja' );
		$this->assertSame( 'translated: Hello', $result );
	}

	/**
	 * インスタンスキャッシュの動作を検証.
	 *
	 * @return void
	 */
	public function test_instance_caching(): void {
		$first  = $this->factory->create( 'deepl' );
		$second = $this->factory->create( 'deepl' );

		$this->assertSame( $first, $second );
	}
}
