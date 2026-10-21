# Changelog

All notable changes to the Madara Coin & Rewards plugin are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-21

The first release.

### Added

- **Daily check-in** with a per-day reward, a streak, configurable milestone
  bonuses, a reward calendar and a countdown to the next check-in.
- **Watch ads and earn coins** with a server-to-server provider, a per-user daily
  cap, a site-wide cap, a cooldown and a reward calendar.
- **Coin packages** with a price, a coin amount, an optional bonus percentage and
  an optional stock limit.
- **Payment gateways** for Stripe, WooCommerce and manual administrator grants.
- **An append-only ledger** with atomic, idempotent balance changes, a SQL-level
  overdraft guard, reversals for refunds and a reconciliation tool.
- **A rewards dashboard**, a transaction history, a balance widget, and eight
  administration screens.
- **A WordPress privacy exporter and eraser** for the coin data.
- **A design system** built on the Madara tokens, scoped to the plugin's own
  markup so it cannot reach the theme.

### Security

- Secrets are encrypted at rest with a key derived from the site's auth salt.
- Every state-changing endpoint verifies a nonce and a capability.
- Rewards are granted only from server-side state; the browser never decides how
  many coins were earned.
- Ad callbacks are HMAC-signed, timestamp-checked and single-use.
- Ledger writes are atomic and idempotent, so a double click, a retried webhook
  and a replayed postback each pay exactly once.

### Notes

- Requires WordPress 6.0+ and PHP 7.4+.
- No Composer packages, no build step and no external services.
- The test suite runs on a plain PHP binary: no PHPUnit, no database and no
  WordPress install.
