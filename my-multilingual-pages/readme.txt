=== My Multilingual Pages ===
Contributors: yourname
Tags: multilingual, translation, deepl, i18n, language
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

日本語の固定ページをマスターとして管理し、各言語の翻訳ページを自動生成・同期する多言語翻訳管理プラグイン。

== Description ==

**My Multilingual Pages** は、WordPress の固定ページを多言語対応にするためのプラグインです。

= 主な機能 =

* **日本語マスター管理**: 日本語ページを「マスター」として管理し、他言語ページを自動生成
* **翻訳エンジン対応**: DeepL API、Google Cloud Translation、OpenAI API に対応（アダプター構造で拡張可能）
* **自動翻訳同期**: 日本語ページ更新時に翻訳ページを自動更新（自動モード/通知モード選択可能）
* **ブロックエディタ対応**: Gutenberg のブロック構造を保持したまま翻訳
* **言語スイッチャー**: ウィジェット・ブロック・ショートコードの3方式で提供
* **SEO対応**: hreflang タグの自動出力、サブディレクトリ形式のURL構成
* **翻訳状況管理**: ダッシュボードウィジェットで翻訳進捗を一目で確認
* **手動編集保護**: 翻訳ページが手動編集された場合は自動上書きを防止

= 対応言語（プリセット） =

* 日本語（デフォルト・マスター）
* 英語 (en)
* 中国語簡体字 (zh)
* 韓国語 (ko)
* 中国語繁体字 (zh-TW)

== Installation ==

1. プラグインフォルダ `my-multilingual-pages` を `/wp-content/plugins/` にアップロード
2. WordPress 管理画面「プラグイン」から有効化
3. 「設定 > 多言語翻訳」で翻訳エンジンのAPIキーを設定
4. 有効にする言語を選択して保存
5. 固定ページの編集画面から翻訳ページを作成

= APIキーの設定方法 =

**管理画面から設定:**
設定 > 多言語翻訳 で各翻訳エンジンのAPIキーを入力できます。

**wp-config.php で定義（推奨）:**

`define( 'MMP_DEEPL_API_KEY', 'your-deepl-api-key' );`
`define( 'MMP_GOOGLE_API_KEY', 'your-google-api-key' );`
`define( 'MMP_OPENAI_API_KEY', 'your-openai-api-key' );`

== Frequently Asked Questions ==

= どの翻訳エンジンがおすすめですか？ =

翻訳品質とコストのバランスから DeepL API を推奨します。特に日本語からの翻訳精度が高いです。

= 翻訳ページを手動で編集した場合はどうなりますか？ =

手動編集された翻訳ページは、元ページ更新時に自動上書きされません。代わりに「要更新」通知が表示されます。

= URL構成を変更できますか？ =

現在はサブディレクトリ形式（/en/page-slug/）のみ対応しています。

== Changelog ==

= 1.0.0 =
* 初回リリース
* DeepL / Google Cloud Translation / OpenAI API 対応
* 固定ページの多言語翻訳管理
* 言語スイッチャー（ウィジェット・ブロック・ショートコード）
* hreflang タグ自動出力
* 翻訳状況ダッシュボード
* WP-Cron によるキュー処理

== Upgrade Notice ==

= 1.0.0 =
初回リリースです。
