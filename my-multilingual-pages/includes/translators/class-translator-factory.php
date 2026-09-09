<?php
/**
 * 翻訳エンジンファクトリー.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Translator_Factory
 *
 * 翻訳エンジンインスタンスの生成を管理するファクトリークラス.
 */
class Translator_Factory {

	/**
	 * 登録済みエンジンのスラッグ => クラス名マップ.
	 *
	 * @var array<string, class-string<Translator_Interface>>
	 */
	private array $engines = array();

	/**
	 * インスタンスキャッシュ.
	 *
	 * @var array<string, Translator_Interface>
	 */
	private array $instances = array();

	/**
	 * コンストラクタ.
	 *
	 * デフォルトエンジンを登録.
	 */
	public function __construct() {
		$this->register( 'deepl', DeepL_Translator::class );
		$this->register( 'google', Google_Translator::class );
		$this->register( 'openai', OpenAI_Translator::class );
	}

	/**
	 * 翻訳エンジンを登録.
	 *
	 * @param string                              $slug  エンジンスラッグ.
	 * @param class-string<Translator_Interface>  $class クラス名.
	 * @return void
	 */
	public function register( string $slug, string $class ): void {
		$this->engines[ $slug ] = $class;
	}

	/**
	 * 翻訳エンジンのインスタンスを取得.
	 *
	 * @param string $slug エンジンスラッグ.
	 * @return Translator_Interface|null
	 */
	public function create( string $slug ): ?Translator_Interface {
		if ( isset( $this->instances[ $slug ] ) ) {
			return $this->instances[ $slug ];
		}

		if ( ! isset( $this->engines[ $slug ] ) ) {
			return null;
		}

		$class = $this->engines[ $slug ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		$instance = new $class();

		if ( ! $instance instanceof Translator_Interface ) {
			return null;
		}

		$this->instances[ $slug ] = $instance;

		return $instance;
	}

	/**
	 * デフォルトの翻訳エンジンを取得.
	 *
	 * @return Translator_Interface|null
	 */
	public function get_default(): ?Translator_Interface {
		$engine = get_option( 'mmp_translation_engine', 'deepl' );
		return $this->create( $engine );
	}

	/**
	 * 特定言語用の翻訳エンジンを取得.
	 *
	 * @param string $lang_code 言語コード.
	 * @return Translator_Interface|null
	 */
	public function get_for_language( string $lang_code ): ?Translator_Interface {
		$per_lang = get_option( 'mmp_per_lang_settings', array() );

		if ( isset( $per_lang[ $lang_code ]['engine'] ) ) {
			$engine = $per_lang[ $lang_code ]['engine'];
			$translator = $this->create( $engine );
			if ( $translator ) {
				return $translator;
			}
		}

		return $this->get_default();
	}

	/**
	 * 登録済みエンジン一覧を取得.
	 *
	 * @return array<string, string> スラッグ => 表示名.
	 */
	public function get_available_engines(): array {
		$list = array();
		foreach ( $this->engines as $slug => $class ) {
			$instance = $this->create( $slug );
			if ( $instance ) {
				$list[ $slug ] = $instance->get_name();
			}
		}
		return $list;
	}
}
