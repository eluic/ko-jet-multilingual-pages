# My Multilingual Pages - WordPress 多言語翻訳管理プラグイン

## 概要

日本語の固定ページを「マスター」として管理し、各言語の翻訳ページを自動生成・同期するWordPressプラグインです。

## 動作要件

- WordPress 6.4 以上
- PHP 8.1 以上
- 翻訳APIキー（DeepL / Google Cloud Translation / OpenAI のいずれか）

## インストール手順

### 1. プラグインのインストール

```bash
# ZIPファイルからインストール
# WordPress管理画面 > プラグイン > 新規追加 > プラグインのアップロード

# または直接コピー
cp -r my-multilingual-pages /path/to/wp-content/plugins/
```

### 2. プラグインの有効化

WordPress管理画面「プラグイン」から「My Multilingual Pages」を有効化します。

### 3. APIキーの設定

**方法A: 管理画面から設定**

設定 > 多言語翻訳 で各翻訳エンジンのAPIキーを入力します。

**方法B: wp-config.php で定義（推奨）**

```php
// wp-config.php に追加
define( 'MMP_DEEPL_API_KEY', 'your-deepl-api-key-here' );
define( 'MMP_GOOGLE_API_KEY', 'your-google-api-key-here' );  // オプション
define( 'MMP_OPENAI_API_KEY', 'your-openai-api-key-here' );  // オプション
```

### 4. 言語設定

設定 > 多言語翻訳 で翻訳対象言語を選択します。

## 使い方

### 翻訳ページの作成

1. 固定ページの編集画面を開く
2. 右サイドバーの「多言語翻訳」メタボックスを確認
3. 各言語の「ページを作成」ボタンをクリック
4. 翻訳ページが下書きとして自動生成される
5. 内容を確認して必要に応じて修正し、公開する

### 言語スイッチャーの設置

**ショートコード:**
```
[lang_switcher]
[lang_switcher style="dropdown" show_flags="yes" show_names="yes"]
[lang_switcher style="inline" show_inactive="no"]
```

**Gutenbergブロック:**
ブロックエディタで「言語スイッチャー」ブロックを挿入

**ウィジェット:**
外観 > ウィジェット で「言語スイッチャー」ウィジェットを追加

### 翻訳の連動更新

設定 > 多言語翻訳 で更新モードを選択:

- **自動モード**: 日本語ページ更新時に翻訳ページを自動で再翻訳
- **通知モード**: 「要更新」フラグを立てて通知のみ

各ページ個別に更新モードを上書きすることも可能です。

## ファイル構成

```
my-multilingual-pages/
├── my-multilingual-pages.php       # メインプラグインファイル
├── includes/
│   ├── class-plugin.php            # コンテナクラス
│   ├── class-language-manager.php  # 言語管理
│   ├── class-translation-sync.php  # 翻訳同期処理
│   ├── class-translation-queue.php # WP-Cronキュー
│   ├── class-admin-settings.php    # 設定画面
│   ├── class-admin-metabox.php     # 翻訳メタボックス
│   ├── class-admin-columns.php     # 一覧カスタムカラム
│   ├── class-admin-dashboard.php   # ダッシュボードウィジェット
│   ├── class-frontend.php          # フロントエンド表示
│   ├── class-rewrite-manager.php   # URLリライトルール
│   ├── class-language-switcher-widget.php  # ウィジェット
│   ├── class-block-registrar.php   # Gutenbergブロック登録
│   └── translators/
│       ├── interface-translator.php       # 翻訳インターフェース
│       ├── class-translator-factory.php   # ファクトリー
│       ├── class-deepl-translator.php     # DeepL実装
│       ├── class-google-translator.php    # Google実装
│       └── class-openai-translator.php    # OpenAI実装
├── admin/
│   ├── css/                        # 管理画面CSS
│   ├── js/                         # 管理画面JavaScript
│   └── views/                      # 管理画面テンプレート
├── blocks/
│   ├── lang-switcher/              # 言語スイッチャーブロック
│   └── js/                         # ブロック用JavaScript
├── languages/                      # 翻訳ファイル (.po/.pot)
├── tests/                          # PHPUnit テスト
├── uninstall.php                   # アンインストール処理
├── readme.txt                      # WordPress.org 形式
└── phpunit.xml.dist                # テスト設定
```

## データモデル

### 投稿メタ (post_meta)

| メタキー | 用途 |
|---|---|
| `_mmp_translation_group_id` | 翻訳グループID (UUID) |
| `_mmp_translation_lang` | ページの言語コード |
| `_mmp_source_post_id` | 元の日本語ページID |
| `_mmp_last_translated_hash` | 翻訳時の本文ハッシュ（手動編集検出用）|
| `_mmp_needs_update` | 要更新フラグ |
| `_mmp_update_mode` | ページ個別の更新モード |

### URL構成

サブディレクトリ形式:
- 日本語（デフォルト）: `/page-slug/`
- 英語: `/en/page-slug/`
- 中国語: `/zh/page-slug/`

## 翻訳エンジンの拡張

新しい翻訳エンジンを追加するには `Translator_Interface` を実装し、ファクトリーに登録します:

```php
// カスタム翻訳エンジンの例
class My_Custom_Translator implements \MMP\Translators\Translator_Interface {
    public function get_name(): string { return 'My Custom'; }
    public function get_slug(): string { return 'my_custom'; }
    public function translate( string $text, string $source_lang, string $target_lang ): string|false {
        // 翻訳ロジック
    }
    // ... 他のメソッドも実装
}

// プラグインで登録
add_action( 'plugins_loaded', function() {
    mmp()->get_translator_factory()->register( 'my_custom', My_Custom_Translator::class );
}, 20 );
```

## テストの実行

```bash
# WordPress テスト環境のセットアップ
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# テスト実行
phpunit
```

## ライセンス

GPL-2.0-or-later
