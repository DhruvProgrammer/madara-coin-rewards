# Madara Coin & Rewards

A server-authoritative coin economy for WordPress, built for the **Madara** manga/comic theme.

Readers earn coins by checking in every day, by watching rewarded ads, and by buying
packages. Every coin is written to an append-only ledger, every balance is derived from
that ledger, and nothing is ever granted on the client's word.

This repository contains two things:

| | |
|---|---|
| `plugin/madara-coin-rewards` | The WordPress plugin. Drop it into `wp-content/plugins/`. |
| `theme/madara` | The Madara theme, as shipped. Drop it into `wp-content/themes/`. |

---

## What the plugin does

### Daily check-in

- One reward per day, resolved against the **site's** timezone, not the visitor's.
- A streak that survives a missed day only if the site says so, with configurable
  milestone bonuses (3, 7, 30 days by default).
- A reward calendar showing which days are **claimed**, which are **available now** and
  which are still **to come**.
- A live countdown to the next check-in, and a clear state for every outcome: ready,
  claimed, not yet available, disabled, failed.

### Watch ads & earn coins

- A rewarded-video flow that only pays out after the ad network **confirms the
  completion server-side**. The browser never decides how many coins were earned.
- A per-user daily cap, a site-wide cap and a cooldown between ads, all enforced in
  the database rather than in the session.
- When no provider is configured the panel says so and **offers no button** - there is
  no state in which a reward button is shown but can never pay.

### Buying coins

- Coin packages with a price, a coin amount, an optional bonus percentage and an
  optional stock limit.
- Payment through **Stripe**, **WooCommerce**, or a **manual** grant by an
  administrator.
- API keys and shared secrets are encrypted at rest and are never rendered back into
  the form.

### The ledger

- Every balance change is a row: what, how much, why, which user, which source.
- The balance is never negative, and the guard lives in the SQL, so two requests
  racing for the same coins cannot both win.
- Each mutation carries an idempotency key, so a double click, a retried webhook and
  a replayed postback each pay exactly once.
- A refund is a **reversal**, not a spend: it gives the coins back and restores the
  lifetime total, and the stock counter follows.
- An administrator can reconcile a user's balance from the ledger at any time.

### Dashboard and account

- A rewards dashboard with the balance, the streak, the lifetime figures, the recent
  transactions and the ad rewards.
- A transaction history the user can page through.
- A **balance widget** for the sidebar, with a link to the dashboard.
- A WordPress privacy exporter and eraser for the coin data.

### Administration

Eight screens under **Madara Coins**: dashboard, settings, packages, users,
transactions, logs and tools, with a recalculation tool, a grant/deduct tool and a
one-click data purge.

---

## Requirements

| | |
|---|---|
| WordPress | 6.0 or newer |
| PHP | 7.4 or newer |
| Database | MySQL 5.7+ / 8.0+, or SQLite through the official drop-in |
| Theme | Madara 2.2.x (the plugin also works on any theme) |

The plugin needs no Composer packages, no build step and no external services. The
frontend assets are plain CSS and vanilla JavaScript.

---

## Installation

### The plugin

1. Copy `plugin/madara-coin-rewards` into `wp-content/plugins/`.
2. Activate **Madara Coin & Rewards** from the Plugins screen.

Activation creates the tables, grants the capabilities, publishes the five plugin
pages, schedules the maintenance jobs and installs the default settings.

### The theme

1. Copy `theme/madara` into `wp-content/themes/`.
2. Activate **Madara** from the Appearance → Themes screen.

The Madara theme requires its companion plugins (Madara Core and Madara Shortcodes);
the theme's own screen will offer to install them.

---

## Configuration

Everything is under **Madara Coins → Settings**.

### Daily check-in

- **Base reward** - the coins a plain check-in pays.
- **Streak bonuses** - a map of day → bonus coins, for example `3: 50, 7: 120, 30: 500`.
- **Day rollover** - the site timezone decides when the day resets.

### Ads

- **Provider** - `Generic S2S` (the built-in server-to-server provider) or your own
  through the `mcr_register_ad_providers` filter.
- **Coins per ad**, **daily cap** and **cooldown**.
- **The shared secret** - paste the same value into your ad network's server-to-server
  callback settings. The plugin's postback URL is shown on the settings screen.

The built-in provider signs each callback with an HMAC over the raw body, checks a
timestamp against a replay window, and treats a transaction id as single-use.

### Packages and payments

- **Packages** - the coins on sale, their price and their bonus.
- **Gateway** - Stripe, WooCommerce, or manual.

For Stripe, paste the publishable key, the secret key and the webhook signing secret.
The webhook URL is shown on the settings screen; subscribe it to
`checkout.session.completed`, `checkout.session.expired` and `charge.refunded`.

---

## Shortcodes

| Shortcode | What it renders |
|---|---|
| `[mcr_checkin]` | The daily check-in panel. |
| `[mcr_dashboard]` | The rewards dashboard. |
| `[mcr_ads]` | The watch-ads panel. |
| `[mcr_buy_coins]` | The coin packages. |
| `[mcr_transactions]` | The transaction history. |
| `[mcr_balance]` | A compact balance summary. |

Each one also exists as a block, under the **Madara Coin & Rewards** block category.

---

## The plugin's pages

The plugin publishes five pages on activation, so the features are reachable
immediately:

| Page | URL |
|---|---|
| Daily Rewards | `/mcr-checkin/` |
| My Coins | `/mcr-dashboard/` |
| Watch Ads & Earn | `/mcr-ads/` |
| Buy Coins | `/mcr-buy-coins/` |
| Coin History | `/mcr-transactions/` |

Rename any of them in the Pages screen; the plugin follows the page, not the slug.
Add them to a menu to put them in the site navigation.

---

## Development

The test suite is plain PHP. There is no PHPUnit, no Composer and no database
requirement: the suite stubs WordPress and drives the real plugin classes.

```bash
cd plugin/madara-coin-rewards

php tests/run-tests.php          # the unit assertions
php tests/run-ledger-tests.php   # the ledger and the idempotency
php tests/audit-ux.php           # the markup and the accessibility
php tests/boot-test.php          # a normal WordPress request
php tests/activation-test.php    # an activation
php tests/admin-test.php         # every admin screen renders
php tests/frontend-test.php      # every shortcode renders
php tests/parse-check.php        # every file parses
php tests/lint-plugin.php        # the coding standards
php tests/check-consistency.php  # the classes, the methods, the schema
php tests/check-prepare.php      # every query is prepared
```

Any PHP 7.4+ binary will do. The suite needs no WordPress install.

### Repository layout

```
plugin/madara-coin-rewards/
├── madara-coin-rewards.php     the plugin header and the bootstrap
├── uninstall.php               the uninstall handler
├── includes/
│   ├── Ledger.php              the append-only ledger
│   ├── Rewards/                the check-in, the streak, the ad sessions
│   ├── Gateways/               Stripe, WooCommerce, manual
│   ├── AdProviders/            the server-to-server ad provider
│   ├── Templates/              the shortcodes and the page templates
│   ├── Admin/                  the eight admin screens
│   └── Support/                the clock, the rate limiter, the secrets
├── templates/                  the frontend markup, and the shared parts
├── assets/                     the design system, the admin styles, the script
├── languages/                  the translation catalogue
├── tests/                      the test suite
└── tools/                      the build and the packaging scripts

theme/madara/                   the Madara theme, as shipped
```

---

## Extending it

The plugin is filterable throughout.

| Filter | What it changes |
|---|---|
| `mcr_register_gateways` | The payment gateways. |
| `mcr_register_ad_providers` | The ad providers. |
| `mcr_register_templates` | The frontend pages. |
| `mcr_get_template_args` | The data a page template receives. |
| `mcr_credit_amount` | The amount a credit pays. |
| `mcr_before_credit` | Whether a credit may proceed at all. |
| `mcr_sanitize_settings` | The settings, before they are stored. |
| `mcr_page_args` | The data a page template receives. |

A gateway extends `MCR\Gateways\Gateway`; an ad provider extends
`MCR\AdProviders\Provider`.

---

## Licence

**The plugin** is licensed under the **GPL-2.0-or-later**, the licence WordPress
plugins use. See [LICENSE](LICENSE).

**The theme** is © Madara Inc. and is licensed under the **MangaBooth License**.
The copy of the theme in this repository does not include the theme's `license.txt`,
so obtain the licence from your purchase. It is a commercial product: keep this
repository private unless you hold the rights to redistribute it.

---

## Support

Open an issue on this repository. For a live site, turn on `WP_DEBUG` in
`wp-config.php` first: `wp-content/debug.log` then names the exact file and line.
