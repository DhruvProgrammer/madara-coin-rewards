<?php
/**
 * Transaction history page.
 *
 * The table is rendered server-side and stays complete without JavaScript.
 * The client only appends the next page of rows; it never recalculates a
 * balance, because `balance_after` is the ledger's own value.
 *
 * @package MCR
 *
 * @var array $args Template arguments.
 */

defined( 'ABSPATH' ) || exit;

$mcr_rows      = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
$mcr_total     = isset( $args['total'] ) ? max( 0, (int) $args['total'] ) : 0;
$mcr_shown     = isset( $args['shown'] ) ? max( 0, (int) $args['shown'] ) : count( $mcr_rows );
$mcr_has_more  = ! empty( $args['has_more'] );
$mcr_next_page = isset( $args['next_page'] ) ? max( 0, (int) $args['next_page'] ) : 0;
$mcr_next_url  = isset( $args['next_url'] ) ? (string) $args['next_url'] : '';
$mcr_page      = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
$mcr_urls      = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
$mcr_checkin_url = isset( $mcr_urls['checkin'] ) ? (string) $mcr_urls['checkin'] : '';
$mcr_first_url = remove_query_arg( 'mcr_page', \MCR\Templates\Templates::current_url() );

$mcr_in   = 0;
$mcr_out  = 0;

foreach ( $mcr_rows as $mcr_row ) {
	$mcr_amount = isset( $mcr_row['amount'] ) ? (int) $mcr_row['amount'] : 0;

	if ( $mcr_amount >= 0 ) {
		$mcr_in += $mcr_amount;
	} else {
		$mcr_out += abs( $mcr_amount );
	}
}

$mcr_net = $mcr_in - $mcr_out;

static $mcr_txn_render_idx = 0;
$mcr_txn_render_idx++;
$mcr_tbody_id = 1 === $mcr_txn_render_idx ? 'mcr-txn-rows' : 'mcr-txn-rows-' . $mcr_txn_render_idx;
?>
<?php if ( ! empty( $mcr_rows ) ) : ?>
	<section class="mcr-section" id="mcr-txn-summary" aria-labelledby="mcr-txn-summary-title">
		<header class="mcr-section__head">
			<h2 class="mcr-section__title font-heading" id="mcr-txn-summary-title"><?php esc_html_e( 'Summary', 'madara-coin-rewards' ); ?></h2>
			<span class="mcr-muted"><?php esc_html_e( 'Totals for the transactions on this page.', 'madara-coin-rewards' ); ?></span>
		</header>

		<div class="mcr-grid mcr-grid--stats">
			<?php
			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'stat',
				array(
					'label' => __( 'In', 'madara-coin-rewards' ),
					'value' => $mcr_in,
					'coin'  => true,
					'tone'  => 'up',
					'meta'  => __( 'Coins earned', 'madara-coin-rewards' ),
				)
			);

			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'stat',
				array(
					'label' => __( 'Out', 'madara-coin-rewards' ),
					'value' => $mcr_out,
					'coin'  => true,
					'tone'  => 'down',
					'meta'  => __( 'Coins spent', 'madara-coin-rewards' ),
				)
			);

			echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'stat',
				array(
					'label'  => __( 'Net', 'madara-coin-rewards' ),
					'value'  => $mcr_net,
					'coin'   => true,
					'accent' => true,
					'meta'   => sprintf(
						/* translators: 1: rows on this page, 2: total rows */
						__( '%1$s of %2$s transactions', 'madara-coin-rewards' ),
						number_format_i18n( count( $mcr_rows ) ),
						number_format_i18n( $mcr_total )
					),
				)
			);
			?>
		</div>
	</section>
<?php endif; ?>

<section class="mcr-section" id="mcr-txn-list" aria-labelledby="mcr-txn-list-title">
	<header class="mcr-section__head">
		<h2 class="mcr-section__title font-heading" id="mcr-txn-list-title"><?php esc_html_e( 'Transaction history', 'madara-coin-rewards' ); ?></h2>
		<?php if ( $mcr_total > 0 ) : ?>
			<span class="mcr-muted">
				<?php
				printf(
					/* translators: %s: total transactions */
					esc_html__( '%s total', 'madara-coin-rewards' ),
					esc_html( number_format_i18n( $mcr_total ) )
				);
				?>
			</span>
		<?php endif; ?>
	</header>

	<div class="mcr-card mcr-card--flush">
		<?php if ( empty( $mcr_rows ) ) : ?>
			<?php if ( $mcr_total > 0 && $mcr_page > 1 ) : ?>
				<?php
				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'notice',
					array(
						'type'    => 'info',
						'title'   => __( 'No transactions on this page', 'madara-coin-rewards' ),
						'text'    => __( 'You have reached the end of your history.', 'madara-coin-rewards' ),
						'actions' => array(
							array(
								'label' => __( 'Back to the first page', 'madara-coin-rewards' ),
								'url'   => $mcr_first_url,
							),
						),
					)
				);
				?>
			<?php else : ?>
				<?php
				echo \MCR\Templates\Templates::part( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'empty',
					array(
						'icon'    => '&#9679;',
						'title'   => __( 'No transactions yet', 'madara-coin-rewards' ),
						'text'    => __( 'Your coin activity will appear here as soon as you earn or spend coins.', 'madara-coin-rewards' ),
						'actions' => array(
							array(
								'label' => __( 'Earn coins', 'madara-coin-rewards' ),
								'url'   => $mcr_checkin_url,
							),
						),
					)
				);
				?>
			<?php endif; ?>
		<?php else : ?>
			<div class="mcr-table__wrap" tabindex="0">
				<table class="mcr-table mcr-table--stack">
					<caption class="mcr-sr-only"><?php esc_html_e( 'Transaction history', 'madara-coin-rewards' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'madara-coin-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'madara-coin-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Description', 'madara-coin-rewards' ); ?></th>
							<th scope="col" class="mcr-table__num"><?php esc_html_e( 'Amount', 'madara-coin-rewards' ); ?></th>
							<th scope="col" class="mcr-table__num"><?php esc_html_e( 'Balance after', 'madara-coin-rewards' ); ?></th>
						</tr>
					</thead>
					<tbody id="mcr-txn-rows" data-mcr-txn-rows="1">
						<?php foreach ( $mcr_rows as $mcr_row ) : ?>
							<?php
							$mcr_amount  = isset( $mcr_row['amount'] ) ? (int) $mcr_row['amount'] : 0;
							$mcr_credit  = $mcr_amount >= 0;
							$mcr_date    = isset( $mcr_row['created_display'] ) ? (string) $mcr_row['created_display'] : '';
							$mcr_iso     = isset( $mcr_row['created_iso'] ) ? (string) $mcr_row['created_iso'] : '';
							$mcr_type    = isset( $mcr_row['type_label'] ) ? (string) $mcr_row['type_label'] : '';
							$mcr_reason  = isset( $mcr_row['reason'] ) ? (string) $mcr_row['reason'] : '';
							$mcr_balance = isset( $mcr_row['balance_after'] ) ? (int) $mcr_row['balance_after'] : 0;
							$mcr_text    = \MCR\Templates\Templates::amount_text( $mcr_amount );
							?>
							<tr>
								<th scope="row">
									<?php if ( '' !== $mcr_iso ) : ?>
										<time datetime="<?php echo esc_attr( $mcr_iso ); ?>"><?php echo esc_html( $mcr_date ); ?></time>
									<?php else : ?>
										<?php echo esc_html( $mcr_date ); ?>
									<?php endif; ?>
								</th>
								<td data-label="<?php esc_attr_e( 'Type', 'madara-coin-rewards' ); ?>"><?php echo esc_html( $mcr_type ); ?></td>
								<td data-label="<?php esc_attr_e( 'Description', 'madara-coin-rewards' ); ?>" class="mcr-table__muted"><?php echo esc_html( $mcr_reason ); ?></td>
								<td data-label="<?php esc_attr_e( 'Amount', 'madara-coin-rewards' ); ?>" class="mcr-table__num <?php echo esc_attr( $mcr_credit ? 'mcr-table__credit' : 'mcr-table__debit' ); ?>">
									<?php echo esc_html( $mcr_text ); ?>
									<span class="mcr-sr-only"><?php echo $mcr_credit ? esc_html__( 'coins', 'madara-coin-rewards' ) : esc_html__( 'coins spent', 'madara-coin-rewards' ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'Balance after', 'madara-coin-rewards' ); ?>" class="mcr-table__num"><?php echo esc_html( number_format_i18n( $mcr_balance ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! empty( $mcr_rows ) ) : ?>
	<section class="mcr-section" id="mcr-txn-pagination" aria-label="<?php esc_attr_e( 'Transaction pages', 'madara-coin-rewards' ); ?>">
		<nav class="mcr-pagination" aria-label="<?php esc_attr_e( 'Transaction pages', 'madara-coin-rewards' ); ?>">
			<?php if ( $mcr_has_more ) : ?>
				<button type="button" class="mcr-pagination__btn" data-mcr-load-more
					data-mcr-next-page="<?php echo esc_attr( $mcr_next_page ); ?>"
					data-mcr-shown="<?php echo esc_attr( $mcr_shown ); ?>"
					data-mcr-total="<?php echo esc_attr( $mcr_total ); ?>"
					aria-controls="mcr-txn-rows">
					<?php esc_html_e( 'Load more', 'madara-coin-rewards' ); ?>
				</button>
				<?php if ( '' !== $mcr_next_url ) : ?>
					<noscript>
						<a class="mcr-pagination__btn" href="<?php echo esc_url( $mcr_next_url ); ?>"><?php esc_html_e( 'Load more', 'madara-coin-rewards' ); ?></a>
					</noscript>
				<?php endif; ?>
			<?php endif; ?>
			<span class="mcr-pagination__status" aria-live="polite">
				<?php
				if ( $mcr_has_more ) {
					printf(
						/* translators: 1: shown rows, 2: total rows */
						esc_html__( 'Showing %1$s of %2$s', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_shown ) ),
						esc_html( number_format_i18n( $mcr_total ) )
					);
				} else {
					printf(
						/* translators: %s: total transactions */
						esc_html__( 'All %s transactions shown.', 'madara-coin-rewards' ),
						esc_html( number_format_i18n( $mcr_total ) )
					);
				}
				?>
			</span>
		</nav>
	</section>
<?php endif;
