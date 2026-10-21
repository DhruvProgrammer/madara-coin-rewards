<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$base = WP_PLUGIN_DIR . '/madara-coin-rewards/includes/';
require_once $base . 'Ledger.php';
require_once $base . 'Settings.php';
$ledger = new MCR\Ledger();
$user = 99;
$ledger->credit( $user, 500, 'purchase', 'Coin package purchase', array(
	'idempotency_key' => 'verify-purchase-1', 'source' => 'purchase',
	'ref_type' => 'payment', 'ref_id' => 1, 'purchased' => true ) );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mcr_balances WHERE user_id = %d", $user ), ARRAY_A );
printf( "  after the purchase : balance=%d earned=%d spent=%d purchased=%d\n", (int) $row['balance'], (int) $row['lifetime_earned'], (int) $row['lifetime_spent'], (int) $row['purchased_total'] );
$ledger->debit( $user, 500, 'refund', 'Payment refunded', array(
	'idempotency_key' => 'verify-refund-1', 'source' => 'refund',
	'ref_type' => 'payment', 'ref_id' => 1, 'reversal' => true, 'restore_purchased' => true ) );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mcr_balances WHERE user_id = %d", $user ), ARRAY_A );
printf( "  after the refund   : balance=%d earned=%d spent=%d purchased=%d\n", (int) $row['balance'], (int) $row['lifetime_earned'], (int) $row['lifetime_spent'], (int) $row['purchased_total'] );
$ok = 0 === (int) $row['balance'] && 0 === (int) $row['lifetime_earned'] && 0 === (int) $row['lifetime_spent'] && 0 === (int) $row['purchased_total'];
echo "  " . ( $ok ? 'PASS' : 'FAIL' ) . ": a refund undoes the purchase completely.\n";
$before = $row;
$ledger->reconcile_user( $user );
$after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mcr_balances WHERE user_id = %d", $user ), ARRAY_A );
$stable = (int) $before['balance'] === (int) $after['balance'] && (int) $before['lifetime_earned'] === (int) $after['lifetime_earned'] && (int) $before['lifetime_spent'] === (int) $after['lifetime_spent'] && (int) $before['purchased_total'] === (int) $after['purchased_total'];
echo "  " . ( $stable ? 'PASS' : 'FAIL' ) . ": the reconciliation leaves a correct balance alone.\n";
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mcr_transactions WHERE user_id = %d", $user ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mcr_balances WHERE user_id = %d", $user ) );
exit( ( $ok && $stable ) ? 0 : 1 );