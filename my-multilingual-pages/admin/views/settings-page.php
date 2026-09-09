<?php
/**
 * 設定ページテンプレート.
 *
 * @package MyMultilingualPages
 * @var string[] $enabled_languages 有効言語.
 * @var array    $preset_languages  プリセット言語.
 * @var array    $engines           翻訳エンジン一覧.
 * @var array    $per_lang_settings 言語別設定.
 * @var array    $error_log         エラーログ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( '多言語翻訳設定', 'my-multilingual-pages' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'mmp_settings' ); ?>

		<!-- 言語管理 -->
		<h2 class="title"><?php esc_html_e( '言語管理', 'my-multilingual-pages' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'デフォルト言語', 'my-multilingual-pages' ); ?></th>
				<td>
					<strong>🇯🇵 <?php esc_html_e( '日本語 (ja)', 'my-multilingual-pages' ); ?></strong>
					<p class="description"><?php esc_html_e( 'デフォルト言語は日本語に固定されています。', 'my-multilingual-pages' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '翻訳対象言語', 'my-multilingual-pages' ); ?></th>
				<td>
					<?php foreach ( $preset_languages as $code => $lang ) : ?>
						<?php if ( 'ja' === $code ) continue; ?>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox"
								name="mmp_enabled_languages[]"
								value="<?php echo esc_attr( $code ); ?>"
								<?php checked( in_array( $code, $enabled_languages, true ) ); ?>>
							<?php echo esc_html( $lang['flag'] . ' ' . $lang['native_name'] . ' (' . $code . ')' ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<!-- 翻訳エンジン設定 -->
		<h2 class="title"><?php esc_html_e( '翻訳エンジン設定', 'my-multilingual-pages' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'デフォルト翻訳エンジン', 'my-multilingual-pages' ); ?></th>
				<td>
					<select name="mmp_translation_engine">
						<?php foreach ( $engines as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"
								<?php selected( get_option( 'mmp_translation_engine', 'deepl' ), $slug ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<!-- DeepL APIキー -->
			<tr>
				<th scope="row"><?php esc_html_e( 'DeepL APIキー', 'my-multilingual-pages' ); ?></th>
				<td>
					<?php if ( defined( 'MMP_DEEPL_API_KEY' ) ) : ?>
						<p class="description"><?php esc_html_e( 'wp-config.php で定義済み', 'my-multilingual-pages' ); ?></p>
					<?php else : ?>
						<input type="password" name="mmp_deepl_api_key"
							value="<?php echo esc_attr( get_option( 'mmp_deepl_api_key', '' ) ); ?>"
							class="regular-text" id="mmp-deepl-key">
						<button type="button" class="button mmp-validate-key" data-engine="deepl" data-field="mmp-deepl-key">
							<?php esc_html_e( 'キーを検証', 'my-multilingual-pages' ); ?>
						</button>
						<span class="mmp-key-status" data-engine="deepl"></span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Google Cloud Translation APIキー -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Google Cloud Translation APIキー', 'my-multilingual-pages' ); ?></th>
				<td>
					<?php if ( defined( 'MMP_GOOGLE_API_KEY' ) ) : ?>
						<p class="description"><?php esc_html_e( 'wp-config.php で定義済み', 'my-multilingual-pages' ); ?></p>
					<?php else : ?>
						<input type="password" name="mmp_google_api_key"
							value="<?php echo esc_attr( get_option( 'mmp_google_api_key', '' ) ); ?>"
							class="regular-text" id="mmp-google-key">
						<button type="button" class="button mmp-validate-key" data-engine="google" data-field="mmp-google-key">
							<?php esc_html_e( 'キーを検証', 'my-multilingual-pages' ); ?>
						</button>
						<span class="mmp-key-status" data-engine="google"></span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- OpenAI APIキー -->
			<tr>
				<th scope="row"><?php esc_html_e( 'OpenAI APIキー', 'my-multilingual-pages' ); ?></th>
				<td>
					<?php if ( defined( 'MMP_OPENAI_API_KEY' ) ) : ?>
						<p class="description"><?php esc_html_e( 'wp-config.php で定義済み', 'my-multilingual-pages' ); ?></p>
					<?php else : ?>
						<input type="password" name="mmp_openai_api_key"
							value="<?php echo esc_attr( get_option( 'mmp_openai_api_key', '' ) ); ?>"
							class="regular-text" id="mmp-openai-key">
						<button type="button" class="button mmp-validate-key" data-engine="openai" data-field="mmp-openai-key">
							<?php esc_html_e( 'キーを検証', 'my-multilingual-pages' ); ?>
						</button>
						<span class="mmp-key-status" data-engine="openai"></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- 言語別設定 -->
		<h2 class="title"><?php esc_html_e( '言語別設定', 'my-multilingual-pages' ); ?></h2>
		<table class="widefat mmp-lang-settings-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '言語', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( '翻訳エンジン', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( '自動翻訳', 'my-multilingual-pages' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $enabled_languages as $code ) :
					$lang_info = $preset_languages[ $code ] ?? null;
					if ( ! $lang_info ) continue;
					$settings = $per_lang_settings[ $code ] ?? array( 'engine' => '', 'auto_translate' => true );
				?>
				<tr>
					<td><?php echo esc_html( $lang_info['flag'] . ' ' . $lang_info['native_name'] ); ?></td>
					<td>
						<select name="mmp_per_lang_settings[<?php echo esc_attr( $code ); ?>][engine]">
							<option value=""><?php esc_html_e( 'デフォルトを使用', 'my-multilingual-pages' ); ?></option>
							<?php foreach ( $engines as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"
									<?php selected( $settings['engine'] ?? '', $slug ); ?>>
									<?php echo esc_html( $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="checkbox"
							name="mmp_per_lang_settings[<?php echo esc_attr( $code ); ?>][auto_translate]"
							value="1"
							<?php checked( ! empty( $settings['auto_translate'] ) ); ?>>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- 更新モード -->
		<h2 class="title"><?php esc_html_e( '翻訳更新設定', 'my-multilingual-pages' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'デフォルト更新モード', 'my-multilingual-pages' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="mmp_update_mode" value="auto"
								<?php checked( get_option( 'mmp_update_mode', 'notify' ), 'auto' ); ?>>
							<?php esc_html_e( '自動モード: 元ページ更新時に自動で翻訳を更新', 'my-multilingual-pages' ); ?>
						</label><br>
						<label>
							<input type="radio" name="mmp_update_mode" value="notify"
								<?php checked( get_option( 'mmp_update_mode', 'notify' ), 'notify' ); ?>>
							<?php esc_html_e( '通知モード: 元ページ更新時に「要更新」フラグを立てるのみ', 'my-multilingual-pages' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<!-- アンインストール設定 -->
		<h2 class="title"><?php esc_html_e( 'アンインストール設定', 'my-multilingual-pages' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'データ削除', 'my-multilingual-pages' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mmp_delete_on_uninstall" value="1"
							<?php checked( get_option( 'mmp_delete_on_uninstall', '0' ), '1' ); ?>>
						<?php esc_html_e( 'プラグイン削除時にすべての翻訳データ・設定を削除する', 'my-multilingual-pages' ); ?>
					</label>
					<p class="description"><?php esc_html_e( '注意: 翻訳ページ・メタデータ・プラグイン設定がすべて削除されます。', 'my-multilingual-pages' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<!-- API エラーログ -->
	<h2 class="title"><?php esc_html_e( 'API エラーログ（直近100件）', 'my-multilingual-pages' ); ?></h2>
	<?php if ( empty( $error_log ) ) : ?>
		<p><?php esc_html_e( 'エラーはありません。', 'my-multilingual-pages' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( '日時', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( 'エンジン', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( 'コンテキスト', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( 'メッセージ', 'my-multilingual-pages' ); ?></th>
					<th><?php esc_html_e( '試行', 'my-multilingual-pages' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $error_log ) as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['timestamp'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['engine'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['context'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $entry['message'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $entry['attempt'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
