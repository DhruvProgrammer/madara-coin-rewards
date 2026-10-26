<?php
/**
 * Ledger transaction row.
 *
 * @package MCR
 *
 * @var array $args {
 *     @type array $txn          Formatted transaction (amount, type_label, reason, created_display, balance_after).
 *     @type bool  $show_balance Whether to print the running balance (default true).
 * }
 */

defined( 'ABSPATH' ) || exit;

$mcr_txn = isset( $args['txn'] ) && is_array( $args['txn'] ) ? $args['txn'] : array();

if ( empty( $mcr_txn ) ) {
	return;
}

$mcr_amount = isset( $mcr_txn['amount'] ) ? (int) $mcr_txn['amount'] : 0;
$mcr_credit = $mcr_amount >= 0;

$mcr_reason = isset( $mcr_txn['reason'] ) && '' !== (string) $mcr_txn['reason']
	? (string) $mcr_txn['reason']
	: ( isset( $mcr_txn['type_label'] ) ? (string) $mcr_txn['type_label'] : '' );

$mcr_meta = isset( $mcr_txn['created_display'] ) ? (string) $mcr_txn['created_display'] : '';
$mcr_balance = isset( $mcr_txn['balance_after'] ) ? (int) $mcr_txn['balance_after'] : 0;
$mcr_show_balance = ! isset( $args['show_balance'] ) || $args['show_balance'];

$mcr_amount_text = \MCR\Templates\Templates::amount_text( $mcr_amount );
?>
<li class="mcr-txn">
	<span class="mcr-txn__icon mcr-txn__icon--<?php echo esc_attr( $mcr_credit ? 'credit' : 'debit' ); ?>" aria-hidden="true"><?php echo esc_html( $mcr_credit ? '+' : '−' ); ?></span>
	<div class="mcr-txn__main">
		<span class="mcr-txn__reason"><?php echo esc_html( $mcr_reason ); ?></span>
		<span class="mcr-txn__meta"><?php echo esc_html( $mcr_meta ); ?></span>
	</div>
	<div class="mcr-txn__amount mcr-txn__amount--<?php echo esc_attr( $mcr_credit ? 'credit' : 'debit' ); ?>">
		<?php echo esc_html( $mcr_amount_text ); ?>
		<span class="mcr-sr-only"><?php echo $mcr_credit ? esc_html__( 'coins', 'madara-coin-rewards' ) : esc_html__( 'coins spent', 'madara-coin-rewards' ); ?></span>
	</div>
	<?php if ( $mcr_show_balance ) : ?>
		<div class="mcr-txn__balance" data-label="<?php esc_attr_e( 'Balance', 'madara-coin-rewards' ); ?>"><?php echo esc_html( number_format_i18n( $mcr_balance ) ); ?></div>
	<?php endif; ?>
</li>
