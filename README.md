# Fintoc Payment Module for Adobe Commerce (Magento 2)

Fintoc_Payment integrates the Fintoc Redirect Page flow into Magento 2 to collect payments via online bank transfers. It creates a checkout session at Fintoc, redirects the customer to Fintoc’s hosted page, and updates the Magento order using return URLs and webhooks.

- Front name: `fintoc`
- Admin menu: Sales → Fintoc → Transactions (grid of `fintoc_payment_transactions`)
- Log file: `var/log/fintoc.log`

## Requirements
- Magento 2.4.x
- PHP 7.4, 8.1, 8.2, 8.3 (aligned with your Magento version)
- Publicly accessible base URL to allow Fintoc to call your webhooks and for customers to return after payment

## Installation (strongly recommended steps)

This repository is a local Magento module (app/code). Use the manual installation unless you have a private Composer repository for it.

### Manual installation
1) Copy the module into your Magento installation at:
   `app/code/Fintoc/Payment`

2) Enable and install the module (this also creates the DB table):
```bash
bin/magento module:enable Fintoc_Payment
bin/magento setup:upgrade
```

3) Build DI and static assets (on production modes):
```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

4) Flush caches:
```bash
bin/magento cache:flush
```

5) Verify that the table `fintoc_payment_transactions` exists and the admin menu Sales → Fintoc → Transactions loads.

### Composer installation (optional)
If you host the module in a VCS or private repository, add it to your project and install:
```bash
composer require fintoc/magento2-payment
bin/magento module:enable Fintoc_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```
Note: This repo is commonly used as app/code; ensure your package/repository is resolvable before using Composer.

## Configuration
Go to Stores → Configuration → Sales → Payment Methods → Fintoc.

Key settings:
- Enabled: Yes/No
- Title: Checkout label (default: “Paga con tu Banco”)
- Public API Key
- Secret API Key (obscured storage): used as Authorization when creating the checkout session. It is stored encrypted and is automatically decrypted by the module.
- Webhook Secret (obscured storage): used to verify the `Fintoc-Signature` header.
- Order Status (for initialization)
- Allowed Countries (All/Specific)
- Maximum Order Amount: if the quote grand total exceeds this value, the payment method is hidden at checkout.
- Debug mode / Logging options / Sort order

Refunds-specific settings (under Fintoc → Refunds):
- Enable Refunds: `payment/fintoc_payment/refunds_enabled`
- Allow Partial Refunds: `payment/fintoc_payment/refunds_allow_partial`
- Auto-create Credit Memo on Refund Succeeded: `payment/fintoc_payment/refunds_auto_creditmemo`
- Order Status on Refund Pending/Succeeded/Failed/Canceled
- Refundable Order Statuses (multiselect): `payment/fintoc_payment/refundable_statuses` (limits the “Refundable Orders” grid and actions to selected statuses)
- API Base URL: `payment/fintoc_payment/api_base_url` (default `https://api.fintoc.com`)
- Refund Create Path: `payment/fintoc_payment/refunds_create_path` (default `/v1/refunds`)
- Refund Cancel Path: `payment/fintoc_payment/refunds_cancel_path` (default `/v1/refunds/{id}/cancel`)

Supported currency: CLP.

## How the flow works
1) Customer places an order with Fintoc payment method.
2) Module creates a pre-authorization transaction record and posts to `https://api.fintoc.com/v1/checkout_sessions` using GuzzleHttp with:
   - amount
   - currency
   - customer_email
   - metadata: carries the Magento order increment ID
   - success_url and cancel_url:
     - `{{baseUrl}}fintoc/checkout/commit/action/success/tr/<encrypted-transaction-id>`
     - `{{baseUrl}}fintoc/checkout/commit/action/cancel/tr/<encrypted-transaction-id>`
3) If the API responds with `redirect_url`, the customer is redirected to Fintoc’s hosted page.
4) Return from Fintoc (commit controller):
   - action=success → marks transaction success, adds order history + payment additional information, and redirects to Magento success page.
   - action=cancel → marks transaction canceled, cancels the order (if not already), restores the original quote so the customer can retry, and redirects to Magento failure page.

## Webhooks
Endpoint: `https://<your-domain>/fintoc/webhook/index`

Events handled (with order status history traces):
- payment_intent.succeeded → creates paid invoice, saves payment info (ids, amount/currency, reference, sender account), updates transaction to success.
- payment_intent.failed, payment_intent.rejected, payment_intent.expired → cancels the order, updates transaction to failed, restores the original quote so the customer can retry.
- payment_intent.pending → keeps the order pending and records a trace.
- checkout_session.finished → adds a trace comment.
- checkout_session.expired → cancels the order, records trace, restores the quote.

Refund webhooks:
- refund.pending → sets refund transaction to pending
- refund.succeeded/completed → sets refund transaction to success (can trigger credit memo via project observers if configured)
- refund.failed/error → sets refund transaction to failed
- refund.canceled/cancelled → sets refund transaction to canceled

Signature verification:
- If header `Fintoc-Signature` is present, it is verified using the configured Webhook Secret.
- On verification failure the module responds with 5xx and also leaves a status history comment on the related order (when determinable) and stores the error under payment additional information (`fintoc_webhook_signature_error`).

Error responses:
- This webhook is built for machine-to-machine calls. Unhandled errors return HTTP 5xx, as required for webhooks.

## Transactions & storage
- DB table: `fintoc_payment_transactions` (created via `etc/db_schema.xml`).
- The module stores request/response/webhook payloads (JSON), status changes, and meta fields (reference, errors, IP, user-agent).
- Service interfaces: `TransactionServiceInterface` and `TransactionRepositoryInterface` manage persistence and status history.

## Frontend & Admin UI
- Success page: shows a Payment Information table (block `Fintoc\Payment\Block\Checkout\Success`).
- Order view (admin and frontend): payment info block `Fintoc\Payment\Block\Info\Fintoc` displays IDs, status, amount/currency, reference, dates, and masked sender account details.
- Admin grid: Sales → Fintoc → Transactions lists all rows from `fintoc_payment_transactions` with filters and export button.
- Refundable Orders grid: shows orders paid with Fintoc that are eligible per configured refundable statuses; from here, admins can request refunds and cancel pending ones.
- Refund form UX: includes form key, amount with currency and max note, per-item quantities, and client-side validation. When partial refunds are disabled, partial modes are not allowed.

## Logging & troubleshooting
- Logs: `var/log/fintoc.log` (Monolog). Enable Debug Mode in config for verbose output.
- If redirect does not occur, check the Create controller response and ensure `redirect_url` is present.
- If webhooks don’t advance orders:
  - Ensure your store URL is publicly reachable.
  - Check `Fintoc-Signature` and the configured Webhook Secret.
  - Review order history comments and `var/log/fintoc.log` for signature or payload errors.
- If the payment method is not visible at checkout:
  - Confirm it’s enabled.
  - Ensure the quote currency is CLP and grand total ≤ Maximum Order Amount (if set).
- If refunds fail with client errors, check the payload mapping (resource_id/resource_type) and that API Base URL and endpoints match your Fintoc environment.

## Development notes
- HTTP client: GuzzleHttp (configured via DI with sensible timeouts and disabled `http_errors`).
- DI preference maps `GuzzleHttp\\ClientInterface` to `GuzzleHttp\\Client` to support both Guzzle 6 and 7; only `ClientInterface` is type-hinted in services.
- Configuration is centralized in `Fintoc\Payment\Service\ConfigurationService` (do not inject `ScopeConfigInterface` directly).
- Commit and Webhook controllers restore the quote on failures/cancellations so customers can retry checkout.

## Refunds (API details)
The module includes a real Refunds API client using Guzzle.

- Create refund
  - Method/URL: POST `${api_base_url}${refunds_create_path}` (default `https://api.fintoc.com/v1/refunds`)
  - Headers: `Authorization: Bearer <secret>`, `Accept: application/json`, `Content-Type: application/json`, `Idempotency-Key: magento-<hash>`
  - JSON body:
    - `resource_id`: Payment Intent ID
    - `resource_type`: `payment_intent`
    - `amount`: integer (minor units/cents)
    - `currency`: e.g., `CLP`
    - `metadata`: optional
  - Response handling: stores external refund id `id` and initial `status` (usually `pending`). Errors surface detailed messages from Fintoc in logs and exceptions.

- Cancel refund
  - Method/URL: POST `${api_base_url}${refunds_cancel_path}` with `{id}` replaced by the external refund id (default `/v1/refunds/{id}/cancel`)
  - Headers: `Authorization: Bearer <secret>`, `Accept: application/json`
  - Response handling: marks as canceled when status is `canceled/cancelled` or `canceled: true`.

- Idempotency
  - The client builds an idempotency key using payment intent, amount, currency, and optional metadata mode to avoid duplicate refunds.

- Status lifecycle
  - pending → succeeded/failed/canceled. Webhooks update the local transaction accordingly.

- Partial refunds
  - Enforced by config: if disabled, the admin form disallows partial modes and the service requires the full refundable amount.

## Tests
This module includes unit tests following Magento 2 testing conventions.

- Location: `app/code/Fintoc/Payment/Test/Unit`
  - Example: `Block/Info/FintocTest.php` validates the payment info block output.

- Prerequisites
  - Install dev dependencies (ensure `phpunit/phpunit` is installed):
    - Composer-based projects: `composer install` (without `--no-dev`), or `composer install --dev` depending on your setup.
  - Ensure Magento’s unit test framework config is present at `dev/tests/unit/phpunit.xml.dist`.

- Running tests
  - From project root, run:
    - `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Fintoc/Payment/Test/Unit`
  - To run a single test file:
    - `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Fintoc/Payment/Test/Unit/Block/Info/FintocTest.php`

## License
GPL-3.0. See LICENSE.
