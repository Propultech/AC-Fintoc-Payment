# Fintoc Payment Module for Adobe Commerce (Magento 2)

Fintoc_Payment integrates the Fintoc Redirect Page flow into Magento 2 to collect payments via online bank transfers. It creates a checkout session at Fintoc, redirects the customer to Fintoc’s hosted page, and updates the Magento order using return URLs and webhooks.

- Front name: `fintoc`
- Admin menu: Sales → Fintoc → Transactions (grid of `fintoc_payment_transactions`)
- Log file: `var/log/fintoc.log`

## Requirements
- Magento 2.4.x
- PHP 7.4, 8.1, 8.2, 8.3 (aligned with your Magento version)
- Publicly accessible base URL to allow Fintoc to call your webhooks and for customers to return after payment

## Installation, Configuration and Tests suggested workflow
1) Add Fintoc_Payment to your Magento 2 store.
2) Get your test credentials from Fintoc.
   1) Create a test account.
   2) Create a test API key.
   3) Create a test Webhook and get the secret.
      1) Be sure to enable the `payment_intent.succeeded` and `payment_intent.failed` events.
      2) Be sure to use a publicly accessible URL for the webhook.
3) Configure the module.
4) Place an order with Fintoc as the payment method.
5) Check the order status in the admin.
6) Check the order status in the frontend.
7) Check the order status in the Fintoc dashboard.
8) Check the transaction details in the admin.
9) Go Live

## Installation (strongly recommended steps)

### Composer installation (recommended)

```
composer require fintoc/magento2-payment
bin/magento module:enable Fintoc_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

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

## Multistore / Multi-website Configuration
If your Magento serves multiple storefronts, configure Fintoc per scope so each website can use the correct credentials and webhook URL.

- Scope switcher: In Stores → Configuration, use the top-left Scope selector to choose the Website or Store View before opening Sales → Payment Methods → Fintoc. Uncheck “Use Default/Use Website” to override.
- Per-website credentials: Set the Secret API Key and Webhook Secret that belong to the website’s Fintoc project. It’s possible to reuse keys, but per-website keys/secrets are recommended for isolation and auditing.
- Webhook per base URL: Create a webhook in Fintoc for each website’s base URL and point it to:
  - {WEBSITE_BASE_URL}/fintoc/webhook (equivalent endpoint: {WEBSITE_BASE_URL}/fintoc/webhook/index)
  Paste the matching Webhook Secret into that website’s configuration.
- Environment separation: Keep Sandbox and Production distinct (different domains/keys/secrets and separate webhooks). Use Magento scope overrides to assign sandbox keys to staging and production keys to live.
- Tips: If you only vary language per Store View under one website, configure at Website scope and optionally override presentation-only fields (Title/Sort order) per Store View.

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

### Invoicing lifecycle
- New order: When the customer places an order with Fintoc, the order is created with the configured New Order Status (typically Pending) and a Fintoc transaction row is stored.
- Payment succeeded: Upon receiving `payment_intent.succeeded`, the module validates the signature and creates a paid invoice for the order. If an invoice already exists, the handler is idempotent and will not create a duplicate.
- Payment failed/rejected/expired: The module cancels the order (when applicable), marks the transaction as failed, and restores the original quote so the shopper can retry checkout.
- Timing considerations: If the shopper returns to your site before the webhook arrives, the order remains in its initial status until the webhook is delivered. The webhook is the source of truth that advances the order and creates the invoice.
- Logging and auditing: All webhook payloads and status transitions are recorded in the `fintoc_payment_transactions` table and `var/log/fintoc.log` for traceability.

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
    - `amount`: integer
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


## Admin: Transaction View

The transaction details page (Sales → Fintoc → Transactions → click a row) is organized into tabs:

- Information: Shows Fintoc Transaction Details (ID, order, type/status, amounts, metadata, etc.) and the Status History rendered as formatted JSON.
- Webhook History: Shows the Webhook Data grouped by event type in an accordion. Each event type (e.g., payment_intent.succeeded, checkout_session.finished) expands to a list of received payloads, each prettified for readability.

### Transaction View Buttons
The transaction details page (Sales → Fintoc → Transactions → click a row) now includes quick actions:

- Back: Returns to the Transactions grid.
- Refund: Shown only when the transaction Type is Authorization. Opens the Fintoc Refund form for the related order.
- Request Cancellation: Shown only when the transaction Type is Refund and the Status is Pending (i.e., the refund can be canceled). Submits a POST (with form key) to cancel the refund request.

Developer notes (routes):
- Grid: `fintoc/transactions/index`
- Transaction View: `fintoc/transactions/view` (param `id`)
- Refund Create (GET): `fintoc_refunds/refund/create` (param `order_id`)
- Refund Save (POST): `fintoc_refunds/refund/save` (form POST; requires form key)
- Refund Cancel (POST): `fintoc_refunds/refund/cancel` (POST params `refund_id`, optional `order_id`, requires form key)

---

Additional resources
- Fintoc Payment REST endpoints: README-REST-ENDPOINTS.md



### Reporting issues

Please use our GitHub Issue template to report problems with the Fintoc_Payment module so we can triage and fix them efficiently.

How to create an issue:

1. Open the repository in GitHub and go to the Issues tab.
2. Click "New issue".
3. Choose the template named "Fintoc_Payment: Bug report".
4. Fill out the form completely, providing:
    - Magento 2 version (required)
    - Fintoc_Payment module version (required)
    - A very detailed explanation of the issue (required)
    - Steps to reproduce, expected vs. actual behavior (required)
    - As much evidence as possible: screenshots, screen recordings, stack traces, full error logs (from var/log, webserver, browser console), API requests/responses (with secrets redacted), relevant configuration snippets, or a minimal reproducible example
    - Environment details (PHP, DB engine and version, deployment type)
5. Submit the issue.

Our issue form is defined at .github/ISSUE_TEMPLATE/fintoc_payment_bug_report.yml.

Notes:
- Please search existing issues before opening a new one to avoid duplicates.
- Do not include secrets or personal data in your report. Redact credentials, tokens, and customer information.
- For security vulnerabilities, follow our security policy instead of opening a public issue (see SECURITY.md).

