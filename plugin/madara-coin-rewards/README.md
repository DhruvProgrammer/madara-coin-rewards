# Madara Coin & Rewards

A coin economy for WordPress, built to look and feel native to the **Madara**
manga/comic/novel theme — and to work on any other theme too.

Users earn coins by checking in daily, by watching rewarded ads, and by buying
them. Every coin movement is written to an append-only ledger with an
atomic, idempotent balance, so the same reward can never be granted twice
and a balance can never go negative.

---

## Requirements

| | |
|---|---|
| WordPress | 6.0 or newer |
| PHP | 7.4 or newer (tested on 8.3) |
| Required plugin | **Madara – Core** 2.2.7.1 (bundled, auto-installed) |
| Optional plugin | **Madara – Shortcodes** 1.6.2 (bundled) |
| Optional plugin | WooCommerce (for the WooCommerce purchase bridge) |
| Optional plugin | A rewarded-ad network (see *Ad providers*) |

The plugin degrades gracefully: without Madara-Core the frontend pages still
render, they just have no manga listings to show.

---

## Installing

1. Copy the `madara-coin-rewards` folder into `wp-content/plugins/`.
2. Activate it. The activation hook creates six tables, seeds the default
   settings, and creates the five frontend pages.
3. Visit **Madara Coins → Dashboard**.

The two required plugins ship as ZIPs inside
`app/plugins/packages/` and are installed by the theme's own
TGM Plugin Activation flow.

---

## Features

### Daily check-in

One claim per calendar day, in the **site's** timezone. The claim is gated
by a `UNIQUE (user_id, checkin_date)` index, so a double-clicked button, a
replayed request or a second tab cannot produce a second reward — the
database refuses the second row before any coins move.

The day bucket comes from the server clock, never from the browser, so
changing the device clock changes nothing.

Streak bonuses are configurable: a map of *day → bonus coins* (3/7/30 by
default) with an optional cap.

### Watch ads and earn

Coins are granted **only after server-side verification**. The flow is:

1. The client asks for a session. The server checks the daily cap and the
   cooldown, then issues a random 32-byte session token and an HMAC-signed
   challenge.
2. The ad plays. The provider's SDK reports completion.
3. The client posts the provider's completion proof back.
4. The server asks the **provider** to confirm it. Only a confirmed
   completion is credited.

A replayed token is rejected (the session is single-use and the ledger key
is derived from the session id). The daily cap is enforced at credit time
against the *verification* timestamp, so sessions opened before midnight
cannot be used to exceed the limit.

**No provider configured means no coins.** The page says so, and the grant
endpoint refuses.

### Buy coins

Packages are configured in the admin: coin amount, bonus percentage, price,
currency, badge, sort order and optional stock limit. Prices are handled in
**integer minor units** end to end, never as floats.

Coins are credited only by a gateway that has **verified the payment** —
never by the browser, and never by the order page returning.

### Rewards dashboard and profile

Current balance, lifetime earned, lifetime spent, purchased coins, daily
check-in state, streak, ad-reward totals, purchase history, and a full
transaction ledger with the balance after every change.

A user's data is visible only to that user (and to administrators with the
`mcr_view_users` capability). The IDOR check is on every read path.

---

## Payment gateways

Gateways plug in through the `mcr_register_gateways` filter and implement
`MCR\Gateways\Gateway`.

| Gateway | State | What you need |
|---|---|---|
| **Stripe** | Production-ready | Publishable key, secret key, webhook secret, and a webhook URL pointing at the plugin's REST route. |
| **WooCommerce** | Production-ready | WooCommerce active, and a product mapped to a coin package. The line-item price is re-read from the order, so a tampered cart cannot change what is credited. |
| **Manual** | Production-ready | An administrator marks the order paid. Capability-checked, nonce-checked, and written to the ledger with the administrator's name as the reason. |
| **Your own** | — | See `docs/04-PROVIDERS.md` for a complete worked example. |

The webhook receiver verifies the provider's signature before anything is
credited, enforces a timestamp tolerance, and is idempotent on the
provider's transaction id. A duplicate webhook is a no-op, not a second
credit.

> **The Stripe gateway does not work until you supply your own keys.** There
> is no demo mode and no simulation: with no keys configured, checkout
> refuses with a clear error.

## Ad providers

Providers plug in through the `mcr_register_ad_providers` filter and
implement `MCR\AdProviders\Provider`.

| Provider | State | Trust | What you need |
|---|---|---|---|
| **Generic server-to-server postback** | Production-ready | **High** | A callback URL on your network's side that POSTs a signed completion to the plugin, plus a shared secret. This is the recommended path for networks that provide server-to-server verification. |
| **Google Ad Manager (rewarded video)** | Client-side only | Low | Your GAM account and tag ids. The completion event is client-reported, so the guarantee is weaker. |
| **Meta Audience Network (rewarded video)** | Client-side only | Low | Your placement id. `RewardedVideoAd` reports completion client-side. |
| **Google AdSense** | **Not supported for rewards** | — | AdSense has no rewarded-video completion API, so the plugin refuses to grant coins from it. Use a network that supports rewarded video. |

> **AdSense cannot be used for coin rewards.** Rather than pretend, the
> plugin documents the limitation and offers a separate, clearly-labelled
> time-gated "view" reward instead.

---

## Shortcodes and blocks

| Shortcode | Block | Page |
|---|---|---|
| `[mcr_checkin]` | `madara-coin-rewards/checkin` | Daily check-in |
| `[mcr_dashboard]` | `madara-coin-rewards/dashboard` | Rewards dashboard |
| `[mcr_ads]` | `madara-coin-rewards/ads` | Watch ads & earn |
| `[mcr_buy_coins]` | `madara-coin-rewards/buy-coins` | Buy coins |
| `[mcr_transactions]` | `madara-coin-rewards/transactions` | Transaction history |
| `[mcr_balance]` | `madara-coin-rewards/balance` | Balance panel |

All of them accept `layout` and `columns`. A **Madara Coin Balance** widget
is also provided for sidebars.

Templates live in `templates/` and every one of them can be overridden by
copying it into `your-child-theme/madara-coin-rewards/`.

---

## Extending

```php
// Add a payment gateway.
add_filter( 'mcr_register_gateways', function ( $gateways ) {
    $gateways[] = new My_Gateway();
    return $gateways;
} );

// Change the daily reward.
add_filter( 'mcr_credit_amount', function ( $amount, $type, $user_id, $ctx ) {
    return 'checkin' === $type ? 25 : $amount;
}, 10, 4 );

// Block a credit entirely.
add_filter( 'mcr_before_credit', function ( $allowed, $user_id, $amount, $type ) {
    return $allowed && ! is_user_member( $user_id, 'banned' );
}, 10, 4 );
```

Other filters: `mcr_transaction_types`, `mcr_default_settings`,
`mcr_sanitize_settings`, `mcr_register_rest_routes`,
`mcr_after_checkin`, `mcr_after_ad_reward`, `mcr_after_purchase_credited`,
`mcr_after_credit`, `mcr_after_debit`.

---

## Security model

| Concern | How it is handled |
|---|---|
| Double claim | `UNIQUE (user_id, checkin_date)` — the database refuses the second row before any coins move. |
| Replayed reward | The ledger's `UNIQUE idempotency_key` makes a repeated grant a no-op. |
| Forged ad reward | The provider verifies server-to-server; without a provider the grant endpoint refuses. |
| Forged payment | Webhook signature + amount + currency re-verification, idempotent on the provider id. |
| Overdraft | The balance guard is in SQL (`WHERE balance >= amount`), not in PHP, so a race cannot overdraw. |
| IDOR | Every read path checks that the requested user is the current user. |
| CSRF | Nonce and capability checks on every privileged action. |
| XSS | Output escaped per context; admin tables escape CSV formula injection. |
| Clock manipulation | The server owns the day bucket; the client clock is never trusted. |
| Privacy | IP and user agent logging are off by default; the privacy exporter and eraser are registered. |

### The ledger

Every balance change is one row in `mcr_transactions`. The balance table
is a cache of the authoritative sum and is updated inside a transaction
with a conditional `UPDATE`:

```sql
UPDATE wp_mcr_balances
   SET balance = balance - 100
 WHERE user_id = 5
   AND balance >= 100
```

If the guard does not match, **zero rows** are affected and the debit is
refused — the check is in the database, so two concurrent spends cannot
both succeed. `Ledger::reconcile_user()` recomputes a balance from the
ledger and repairs it if they ever diverge.

---

## Testing

The plugin ships with its own test suite. It needs no PHPUnit, no
Composer and no WordPress install:

```bash
cd wp-content/plugins/madara-coin-rewards

php tests/run-tests.php          # 105 unit assertions
php tests/run-ledger-tests.php   #  47 ledger / idempotency assertions
php tests/boot-test.php          #  16 boot + activation assertions
php tests/audit-ux.php           #  23 accessibility & UX assertions
php tests/parse-check.php        # parses every PHP file, checks for BOMs
php tests/lint-plugin.php        # unprepared SQL, raw echoes, missing guards
php tests/check-consistency.php  # class/method/hook/schema cross-references
php tests/check-global-classes.php # namespace resolution of global classes
```

All eight exit `0` on a healthy tree. The boot test is the one that
catches the class of bug the others cannot: it loads the plugin the way
WordPress does and asserts that it comes up without a fatal error.

---

## Database

| Table | Purpose |
|---|---|
| `mcr_balances` | One row per user: the balance cache and lifetime counters. |
| `mcr_transactions` | Append-only ledger. Every balance change, with the balance after it. |
| `mcr_checkins` | One row per user per day. `UNIQUE (user_id, checkin_date)`. |
| `mcr_ad_sessions` | Rewarded-ad sessions, their state and their single-use token. |
| `mcr_payments` | Purchase orders, with `UNIQUE (gateway, provider_tx_id)`. |
| `mcr_claims` | Generic idempotency table for future reward types. |

All tables are InnoDB. The plugin checks the engine on install and warns
in the admin if it is not, because MyISAM silently ignores transactions.

---

## Licence

GPL-2.0-or-later.
