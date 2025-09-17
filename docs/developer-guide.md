# Developer Guide — Fintoc_Payment

This guide is intended for Magento developers integrating, extending, or maintaining the Fintoc_Payment module.

Installation and setup
- Composer (example):
  - composer require fintoc/module-payment
  - bin/magento module:enable Fintoc_Payment
  - bin/magento setup:upgrade
  - bin/magento cache:flush
- Configure API keys and payment settings under Stores → Configuration → Sales → Payment Methods → Fintoc. See configuration.md.

Code entry points
- Payment method: Fintoc\Payment\Model\Payment (extends AbstractMethod)
- Checkout:
  - JS renderer: view/frontend/web/js/view/payment/method-renderer/fintoc-method.js
  - Create endpoint: Fintoc\Payment\Controller\Checkout\Create
  - Commit endpoint: Fintoc\Payment\Controller\Checkout\Commit
- Webhooks: Fintoc\Payment\Controller\Webhook\Index → dispatches to Service\Webhook.

Key services and contracts
- TransactionServiceInterface → Service\TransactionService
  - Methods to create transactions for pre/post-authorization, capture, refund, void, and webhook events.
- TransactionRepositoryInterface → Model\TransactionRepository
  - CRUD and search operations for transactions.
- RefundServiceInterface → Service\RefundService
  - Orchestrates refunds and order status updates.
- RefundsApiClientInterface → Service\RefundsApiClient
  - Low-level HTTP client around GuzzleHttp\Client.
- LoggerServiceInterface → Service\LoggerService
  - Facade over a dedicated Monolog channel (see etc/di.xml).
- Webhook interfaces (Validator, Parser, Idempotency, Router, Handler) → Service\Webhook implementations

Extensibility
- Add a new webhook event handler:
  1) Implement Fintoc\Payment\Api\Webhook\WebhookHandlerInterface (or extend AbstractWebhookHandler).
  2) Register it in etc/di.xml inside <type name="Fintoc\\Payment\\Service\\Webhook\\WebhookRouter"> handlers array with the event type key.
- Override configuration logic or add computed settings:
  - Implement your own ConfigurationServiceInterface and set a preference in your module’s etc/di.xml.
- Replace logging behavior:
  - Bind LoggerServiceInterface to your implementation via etc/di.xml, or provide your own Monolog handler.
- Modify checkout renderer behavior:
  - Override the Knockout component via layout XML or requirejs-config mapping in a dependent module.

Logging
- Channel name: "fintoc"
- Configurable via:
  - payment/fintoc_payment/logging_enabled
  - payment/fintoc_payment/debug_level
  - payment/fintoc_payment/log_sensitive_data
  - payment/fintoc_payment/debug
- File location (typical): var/log/fintoc.log (handler may produce per-day files depending on configuration).

Database
- See database-schema.md for the fintoc_payment_transactions table definition.
- Use TransactionRepositoryInterface for data access; avoid direct queries.

Admin UI
- Grids defined in view/adminhtml/ui_component/:
  - fintoc_payment_transactions_grid.xml — Transaction history
  - fintoc_refunds_orders_grid.xml — Refundable orders
- Pages wired via view/adminhtml/layout/*.xml and Blocks under Block/Adminhtml.

Testing
- Unit tests examples under app/code/Fintoc/Payment/Test.
- Recommended commands:
  - bin/magento dev:tests:run unit
  - vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Fintoc/Payment/Test

Troubleshooting
- Payment method not visible:
  - Verify it’s enabled, within max_order_amount threshold, and allowed for the current country.
- Webhook returns 401/400:
  - Check webhook_secret and signature header from Fintoc.
- Refunds do not update order state:
  - Verify refunds_* configuration and that webhook events are reaching the store.

References
- See technical-overview.md and architecture.md for a broader context.
- UML diagrams: uml-class-diagram.puml and uml-class-diagram.mmd
