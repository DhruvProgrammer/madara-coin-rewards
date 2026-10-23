<?php
/**
 * Schema installer / migrator.
 *
 * @package MCR
 */

namespace MCR;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's database schema.
 */
class Installer {

	/**
	 * Option holding the installed schema version.
	 */
	const DB_VERSION_OPTION = 'mcr_db_version';

	/**
	 * Option holding the installed data version.
	 */
	const DATA_VERSION_OPTION = 'mcr_data_version';

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_schema();

		Capabilities::add_to_roles();

		if ( false === get_option( self::DB_VERSION_OPTION ) ) {
			add_option( self::DB_VERSION_OPTION, MCR_DB_VERSION );
		}

		if ( false === get_option( self::DATA_VERSION_OPTION ) ) {
			add_option( self::DATA_VERSION_OPTION, MCR_DB_VERSION );
		}

		Settings::install_defaults();
		Pages::create_default_pages();

		Cron::schedule_events();

		set_transient( 'mcr_activation_redirect', 1, 60 );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Data is intentionally preserved; the uninstall handler removes it.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cron::unschedule_events();
		Capabilities::remove_from_roles();
		flush_rewrite_rules();
	}

	/**
	 * Creates or upgrades the schema when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed, MCR_DB_VERSION, '>=' ) ) {
			return;
		}

		self::install_schema();
		update_option( self::DB_VERSION_OPTION, MCR_DB_VERSION );
	}

	/**
	 * Creates all plugin tables.
	 *
	 * @return void
	 */
	public static function install_schema() {
		global $wpdb;

		// dbDelta() lives in the admin upgrade helpers. It is guarded so the
		// schema can also be created from a front-end or CLI context.
		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( is_readable( $upgrade_file ) ) {
			require_once $upgrade_file;
		} elseif ( ! function_exists( 'dbDelta' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'mcr_';

		/*
		 * dbDelta requires: two spaces after PRIMARY KEY, one space after
		 * KEY / UNIQUE KEY, lowercase types, one column per line, and no
		 * backticks around column names.
		 */
		$schema = array();

		$schema[] = "CREATE TABLE {$prefix}balances (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  balance bigint(20) NOT NULL DEFAULT 0,
  lifetime_earned bigint(20) NOT NULL DEFAULT 0,
  lifetime_spent bigint(20) NOT NULL DEFAULT 0,
  purchased_total bigint(20) NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  KEY balance (balance)
) $charset_collate ENGINE=InnoDB;";

		$schema[] = "CREATE TABLE {$prefix}transactions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  txn_uid char(36) NOT NULL DEFAULT '',
  type varchar(32) NOT NULL DEFAULT '',
  amount bigint(20) NOT NULL DEFAULT 0,
  balance_after bigint(20) NOT NULL DEFAULT 0,
  reason varchar(191) NOT NULL DEFAULT '',
  source varchar(32) NOT NULL DEFAULT '',
  ref_type varchar(32) NOT NULL DEFAULT '',
  ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
  idempotency_key varchar(191) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY txn_uid (txn_uid),
  UNIQUE KEY idempotency_key (idempotency_key),
  KEY user_created (user_id,created_at),
  KEY type_created (type,created_at),
  KEY ref (ref_type,ref_id)
) $charset_collate ENGINE=InnoDB;";

		$schema[] = "CREATE TABLE {$prefix}checkins (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  checkin_date date NOT NULL DEFAULT '1970-01-01',
  streak_count int(10) unsigned NOT NULL DEFAULT 1,
  base_amount bigint(20) NOT NULL DEFAULT 0,
  bonus_amount bigint(20) NOT NULL DEFAULT 0,
  total_amount bigint(20) NOT NULL DEFAULT 0,
  transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY user_day (user_id,checkin_date),
  KEY checkin_date (checkin_date)
) $charset_collate ENGINE=InnoDB;";

		$schema[] = "CREATE TABLE {$prefix}ad_sessions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  ad_id varchar(64) NOT NULL DEFAULT '',
  provider varchar(32) NOT NULL DEFAULT '',
  session_token char(64) NOT NULL DEFAULT '',
  completion_token varchar(191) NOT NULL DEFAULT '',
  day_key date NOT NULL DEFAULT '1970-01-01',
  status varchar(20) NOT NULL DEFAULT 'pending',
  coins int(11) NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  expires_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  verified_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY session_token (session_token),
  KEY user_day (user_id,day_key),
  KEY status_expires (status,expires_at)
) $charset_collate ENGINE=InnoDB;";

		$schema[] = "CREATE TABLE {$prefix}payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  package_id varchar(64) NOT NULL DEFAULT '',
  gateway varchar(32) NOT NULL DEFAULT '',
  order_id varchar(64) NOT NULL DEFAULT '',
  provider_tx_id varchar(128) NOT NULL DEFAULT '',
  amount decimal(12,2) NOT NULL DEFAULT 0.00,
  currency varchar(8) NOT NULL DEFAULT '',
  coins_total bigint(20) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pending',
  user_ip varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  paid_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  expires_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY order_id (order_id),
  UNIQUE KEY gateway_provider_tx (gateway,provider_tx_id),
  KEY user_status (user_id,status),
  KEY status_expires (status,expires_at)
) $charset_collate ENGINE=InnoDB;";

		$schema[] = "CREATE TABLE {$prefix}claims (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  claim_key varchar(191) NOT NULL DEFAULT '',
  claim_type varchar(32) NOT NULL DEFAULT '',
  amount bigint(20) NOT NULL DEFAULT 0,
  transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY claim_key (claim_key),
  KEY user_type (user_id,claim_type)
) $charset_collate ENGINE=InnoDB;";

		foreach ( $schema as $table ) {
			dbDelta( $table );
		}

		self::verify_engine();
	}

	/**
	 * Returns the storage engine of a table.
	 *
	 * `SHOW TABLE STATUS` returns one row per table. wpdb::get_var() on
	 * that query yields the FIRST COLUMN of the first row, which is the
	 * table name, not the engine - reading `->Engine` from it silently
	 * yields nothing. get_results() gives the whole row.
	 *
	 * @param string $table Table name.
	 * @return string Uppercase engine name, or an empty string.
	 */
	public static function table_engine( $table ) {
		global $wpdb;

		$rows = $wpdb->get_results( "SHOW TABLE STATUS LIKE '" . $wpdb->esc_like( $table ) . "'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! empty( $rows ) && is_object( $rows[0] ) && isset( $rows[0]->Engine ) ) {
			return strtoupper( (string) $rows[0]->Engine );
		}

		// Fall back to a direct query for drivers that do not return rows.
		$engine = $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $wpdb->esc_like( $table ) . "'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return strtoupper( (string) $engine );
	}

	/**
	 * Verifies the plugin tables use a transactional engine.
	 *
	 * MyISAM silently ignores transactions, which would break the ledger's
	 * atomicity guarantees. We detect it and warn the administrator rather
	 * than pretending the ledger is safe.
	 *
	 * @return void
	 */
	private static function verify_engine() {
		$table  = $GLOBALS['wpdb']->prefix . 'mcr_transactions';
		$engine = self::table_engine( $table );

		if ( '' === $engine ) {
			return;
		}

		if ( 'INNODB' !== $engine ) {
			update_option(
				'mcr_engine_warning',
				sprintf(
					/* translators: %s: table engine name */
					__( 'Madara Coin & Rewards detected the %s storage engine. Coin balances require InnoDB for transactional safety. Please convert the mcr_ tables to InnoDB.', 'madara-coin-rewards' ),
					$engine
				)
			);
		} else {
			delete_option( 'mcr_engine_warning' );
		}
	}

	/**
	 * Drops every plugin table. Used by uninstall.php only.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			'balances',
			'transactions',
			'checkins',
			'ad_sessions',
			'payments',
			'claims',
		);

		foreach ( $tables as $table ) {
			$name = $wpdb->prefix . 'mcr_' . $table;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}
	}
}
