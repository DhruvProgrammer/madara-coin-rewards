<?php
/**
 * Seven day streak strip.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type array $calendar  Check-in calendar rows (date, claimed, streak, amount, today).
 *     @type array $days_data Explicit seven day rows (overrides $calendar).
 *     @type array $bonuses   Threshold => bonus amount map.
 *     @type int   $days      Number of trailing calendar cells (default 7).
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_calendar = isset( $args['calendar'] ) && is_array( $args['calendar'] ) ? $args['calendar'] : array();
$mcr_bonuses  = isset( $args['bonuses'] ) && is_array( $args['bonuses'] ) ? $args['bonuses'] : array();
$mcr_days     = isset( $args['days'] ) ? max( 1, absint( $args['days'] ) ) : 7;
$mcr_claimable = ! empty( $args['claimable'] );

$mcr_items = array();

if ( ! empty( $args['days_data'] ) && is_array( $args['days_data'] ) ) {
	$mcr_items = array_values( $args['days_data'] );
} elseif ( ! empty( $mcr_calendar ) ) {
	$mcr_items = array_slice( $mcr_calendar, -$mcr_days );
}

if ( empty( $mcr_items ) ) {
	return;
}
?>
<ol class="mcr-streak">
	<?php foreach ( $mcr_items as $mcr_day ) : ?>
		<?php
		if ( ! is_array( $mcr_day ) ) {
			continue;
		}

		$mcr_date     = isset( $mcr_day['date'] ) ? (string) $mcr_day['date'] : '';
		$mcr_claimed  = ! empty( $mcr_day['claimed'] );
		$mcr_today    = ! empty( $mcr_day['today'] );
		$mcr_future   = ! empty( $mcr_day['future'] );
		$mcr_streak   = isset( $mcr_day['streak'] ) ? (int) $mcr_day['streak'] : 0;
		$mcr_amount   = isset( $mcr_day['amount'] ) ? (int) $mcr_day['amount'] : 0;
		$mcr_is_bonus = $mcr_claimed && $mcr_streak > 0 && isset( $mcr_bonuses[ $mcr_streak ] );

		$mcr_classes = 'mcr-streak__day';

		if ( $mcr_claimed ) {
			$mcr_classes .= ' is-done';
		} elseif ( $mcr_future ) {
			$mcr_classes .= ' is-locked';
		} else {
			$mcr_classes .= ' is-missed';
		}

		if ( $mcr_today ) {
			$mcr_classes .= ' is-today';

			/*
			 * Today is AVAILABLE when the reward has not been claimed
			 * yet. The reader has to be able to tell "available now"
			 * apart from "already claimed" and from "still to come".
			 */
			if ( $mcr_claimable ) {
				$mcr_classes .= ' is-available';
			}
		}

		if ( $mcr_is_bonus ) {
			$mcr_classes .= ' is-bonus';
		}

		$mcr_date_label = '' !== $mcr_date ? \MCR\Templates\Templates::format_date( $mcr_date ) : '';
		/*
		 * The weekday follows the site's language. gmdate() is always
		 * English, so it is only the last resort.
		 */
		$mcr_weekday = isset( $mcr_day['label'] ) && '' !== (string) $mcr_day['label']
			? (string) $mcr_day['label']
			: ( '' !== $mcr_date ? date_i18n( 'D', (int) strtotime( $mcr_date . ' 00:00:00' ) ) : '' );

		if ( $mcr_claimed ) {
			$mcr_sr = sprintf(
				/* translators: 1: date, 2: coin amount */
				__( '%1$s: claimed, %2$s coins.', 'madara-coin-rewards' ),
				$mcr_date_label,
				number_format_i18n( $mcr_amount )
			);
		} elseif ( $mcr_future ) {
			$mcr_sr = sprintf(
				/* translators: %s: date */
				__( '%s: upcoming.', 'madara-coin-rewards' ),
				$mcr_date_label
			);
		} elseif ( $mcr_today && $mcr_claimable ) {
			$mcr_sr = sprintf(
				/* translators: %s: date */
				__( '%s: available, ready to claim.', 'madara-coin-rewards' ),
				$mcr_date_label
			);
		} else {
			$mcr_sr = sprintf(
				/* translators: %s: date */
				__( '%s: not claimed.', 'madara-coin-rewards' ),
				$mcr_date_label
			);
		}
		?>
		<li class="<?php echo esc_attr( $mcr_classes ); ?>"<?php if ( $mcr_today ) : ?> aria-current="date"<?php endif; ?>>
			<span class="mcr-streak__label" aria-hidden="true"><?php echo esc_html( $mcr_weekday ); ?></span>
			<span class="mcr-streak__dot" aria-hidden="true"><?php echo esc_html( $mcr_claimed ? '✓' : '·' ); ?></span>
			<span class="mcr-sr-only"><?php echo esc_html( $mcr_sr ); ?></span>
		</li>
	<?php endforeach; ?>
</ol>
