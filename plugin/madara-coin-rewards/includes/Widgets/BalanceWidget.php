<?php
/**
 * Coin balance widget.
 *
 * @package MCR
 */

namespace MCR\Widgets;

use MCR\Ledger;
use MCR\Rewards\CheckIn;
use MCR\Templates\Templates;
use WP_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the current user's coin balance in a sidebar.
 */
class BalanceWidget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'mcr_balance',
			__( 'Madara Coin Balance', 'madara-coin-rewards' ),
			array(
				'classname'                   => 'widget_mcr_balance',
				'description'                 => __( 'Shows the logged-in user\'s coin balance, streak and a link to the rewards dashboard.', 'madara-coin-rewards' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Your Coins', 'madara-coin-rewards' );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' !== $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! is_user_logged_in() ) {
			printf(
				'<p class="mcr-muted">%s</p>',
				esc_html__( 'Log in to see your coin balance.', 'madara-coin-rewards' )
			);
		} else {
			echo $this->render_panel( $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Builds the widget markup.
	 *
	 * @param array $instance Widget settings.
	 * @return string
	 */
	private function render_panel( $instance ) {
		$user_id = get_current_user_id();
		$stats   = Ledger::instance()->get_stats( $user_id );

		$show_streak = ! empty( $instance['show_streak'] );
		$show_link   = ! isset( $instance['show_link'] ) || $instance['show_link'];

		$streak = 0;

		if ( $show_streak && class_exists( '\MCR\Rewards\CheckIn' ) ) {
			$status = CheckIn::instance()->status( $user_id );
			$streak = isset( $status['streak'] ) ? (int) $status['streak'] : 0;
		}

		$html  = '<div class="mcr-widget-balance mcr-scope">';
		$html .= '<div class="mcr-amount mcr-amount--lg" data-mcr-balance>';
		$html .= '<span class="mcr-amount__value">' . esc_html( number_format_i18n( $stats['balance'] ) ) . '</span>';
		$html .= '<span class="mcr-sr-only">' . esc_html__( 'coins', 'madara-coin-rewards' ) . '</span>';
		$html .= '</div>';

		if ( $show_streak ) {
			$html .= '<p class="mcr-muted">';
			$html .= esc_html(
				sprintf(
					/* translators: %d: number of consecutive check-in days */
					_n( 'Day %d of your streak', 'Day %d of your streak', $streak, 'madara-coin-rewards' ),
					$streak
				)
			);
			$html .= '</p>';
		}

		if ( $show_link ) {
			$urls = Templates::urls();
			$url  = isset( $urls['dashboard'] ) ? (string) $urls['dashboard'] : '';

			if ( '' !== $url ) {
				$html .= '<a class="mcr-btn mcr-btn--sm" href="' . esc_url( $url ) . '">';
				$html .= esc_html__( 'View rewards', 'madara-coin-rewards' );
				$html .= '</a>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Sanitises the widget settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Previous settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title']      = isset( $new_instance['title'] ) ? sanitize_text_field( (string) $new_instance['title'] ) : '';
		$instance['show_streak'] = ! empty( $new_instance['show_streak'] );
		$instance['show_link']   = ! isset( $new_instance['show_link'] ) || ! empty( $new_instance['show_link'] );

		return $instance;
	}

	/**
	 * Renders the widget settings form.
	 *
	 * @param array $instance Widget settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Your Coins', 'madara-coin-rewards' );
		$show_streak = ! empty( $instance['show_streak'] );
		$show_link   = ! isset( $instance['show_link'] ) || ! empty( $instance['show_link'] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'madara-coin-rewards' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<input class="checkbox" type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_streak' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_streak' ) ); ?>"
				value="1" <?php checked( $show_streak ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_streak' ) ); ?>"><?php esc_html_e( 'Show the check-in streak', 'madara-coin-rewards' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_link' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_link' ) ); ?>"
				value="1" <?php checked( $show_link ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_link' ) ); ?>"><?php esc_html_e( 'Show the "View rewards" link', 'madara-coin-rewards' ); ?></label>
		</p>
		<?php
	}
}
