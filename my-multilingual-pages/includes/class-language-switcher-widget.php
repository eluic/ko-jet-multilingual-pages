<?php
/**
 * 言語スイッチャーウィジェットクラス.
 *
 * @package MyMultilingualPages
 */

declare(strict_types=1);

namespace MMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Language_Switcher_Widget
 *
 * 言語スイッチャーをウィジェットとして提供.
 */
class Language_Switcher_Widget extends \WP_Widget {

	/**
	 * コンストラクタ.
	 */
	public function __construct() {
		parent::__construct(
			'mmp_language_switcher',
			__( '言語スイッチャー', 'my-multilingual-pages' ),
			array(
				'description' => __( '多言語翻訳プラグインの言語切り替えウィジェット', 'my-multilingual-pages' ),
				'classname'   => 'mmp-language-switcher-widget',
			)
		);
	}

	/**
	 * ウィジェットのフロントエンド表示.
	 *
	 * @param array $args     ウィジェット引数.
	 * @param array $instance インスタンス設定.
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$title = ! empty( $instance['title'] )
			? apply_filters( 'widget_title', $instance['title'] )
			: '';

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$frontend = new Frontend( mmp()->get_language_manager() );
		echo $frontend->render_language_switcher( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'style'         => $instance['style'] ?? 'list',
			'show_inactive' => ! empty( $instance['show_inactive'] ) ? 'yes' : 'no',
			'show_flags'    => ! empty( $instance['show_flags'] ) ? 'yes' : 'no',
			'show_names'    => ! empty( $instance['show_names'] ) ? 'yes' : 'no',
		) );

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * ウィジェット設定フォーム.
	 *
	 * @param array $instance インスタンス設定.
	 * @return void
	 */
	public function form( $instance ): void {
		$title         = $instance['title'] ?? __( '言語', 'my-multilingual-pages' );
		$style         = $instance['style'] ?? 'list';
		$show_inactive = ! empty( $instance['show_inactive'] );
		$show_flags    = $instance['show_flags'] ?? true;
		$show_names    = $instance['show_names'] ?? true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'タイトル:', 'my-multilingual-pages' ); ?>
			</label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
				<?php esc_html_e( '表示スタイル:', 'my-multilingual-pages' ); ?>
			</label>
			<select class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>">
				<option value="list" <?php selected( $style, 'list' ); ?>><?php esc_html_e( 'リスト', 'my-multilingual-pages' ); ?></option>
				<option value="dropdown" <?php selected( $style, 'dropdown' ); ?>><?php esc_html_e( 'ドロップダウン', 'my-multilingual-pages' ); ?></option>
				<option value="inline" <?php selected( $style, 'inline' ); ?>><?php esc_html_e( 'インライン', 'my-multilingual-pages' ); ?></option>
			</select>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_flags' ) ); ?>"
				<?php checked( $show_flags ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>">
				<?php esc_html_e( '国旗を表示', 'my-multilingual-pages' ); ?>
			</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_names' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_names' ) ); ?>"
				<?php checked( $show_names ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_names' ) ); ?>">
				<?php esc_html_e( '言語名を表示', 'my-multilingual-pages' ); ?>
			</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_inactive' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_inactive' ) ); ?>"
				<?php checked( $show_inactive ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_inactive' ) ); ?>">
				<?php esc_html_e( '未翻訳言語も表示（無効表示）', 'my-multilingual-pages' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * ウィジェット設定の保存.
	 *
	 * @param array $new_instance 新しい設定.
	 * @param array $old_instance 古い設定.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ): array {
		return array(
			'title'         => sanitize_text_field( $new_instance['title'] ?? '' ),
			'style'         => sanitize_text_field( $new_instance['style'] ?? 'list' ),
			'show_flags'    => ! empty( $new_instance['show_flags'] ),
			'show_names'    => ! empty( $new_instance['show_names'] ),
			'show_inactive' => ! empty( $new_instance['show_inactive'] ),
		);
	}
}
