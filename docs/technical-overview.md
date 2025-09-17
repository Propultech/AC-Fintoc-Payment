# Fintoc_Payment — Technical Overview

This document provides a high-level but comprehensive technical overview of the Fintoc_Payment Magento 2 module.

Module name: Fintoc_Payment
Depends on: Magento_Sales, Magento_Payment, Magento_Checkout (see etc/module.xml)

Purpose
- Provide a redirect-based payment initiation using Fintoc.
- Persist rich transaction history for payments, captures, voids, refunds, and webhooks.
- Offer robust webhook processing with validation, idempotency, and routing to specialized handlers.
- Expose admin UI for transactions and refunds management.

Key packages and components
- Api/ (service and data interfaces)
  - ConfigurationServiceInterface: Resolve configuration values for the module and features.
  - LoggerServiceInterface: Abstract logging facade to centralize logging behavior.
  - RefundServiceInterface, RefundsApiClientInterface: Refund operations and HTTP client to Fintoc refunds API.
  - TransactionServiceInterface: High-level operations for creating/updating transaction records and histories.
  - TransactionRepositoryInterface: CRUD/searches over Transaction aggregate.
  - Data/TransactionInterface, Data/TransactionSearchResultsInterface: Entity contracts.
  - Webhook/*: WebhookRequestValidatorInterface, WebhookRequestParserInterface, WebhookIdempotencyServiceInterface, WebhookRouterInterface, WebhookHandlerInterface.

- Model/
  - Payment: Magento payment method model (extends Magento\Payment\Model\Method\AbstractMethod). Controls availability, initialization, store scoping.
  - Transaction: EAV-like flat entity for Fintoc transactions (implements Api\Data\TransactionInterface).
  - TransactionRepository: Implements Api\TransactionRepositoryInterface using ResourceModel for persistence and collection factories for queries.
  - TransactionSearchResults: Implements Api\Data\TransactionSearchResultsInterface.
  - ResourceModel/Transaction (+ Collection): DB mappers; Grid virtual types configured in etc/di.xml.
  - Ui/ConfigProvider: Adds module config to checkout JS config (registered via Magento\Checkout\Model\CompositeConfigProvider in etc/di.xml).
  - Config/Source/*: DebugLevel, PaymentAction option sources.
  - Source/*: TransactionStatus, TransactionType option sources.

- Service/
  - ConfigurationService: Centralized configuration access (e.g., API keys, toggles, thresholds).
  - LoggerService + Logger/* (monolog handler/logger wired in etc/di.xml via virtual type FintocPaymentMethodLogger).
  - TransactionService: Core domain service to create transactions (pre/post authorization, capture, refund, void, webhook), maintain status history, and append webhook data.
  - RefundsApiClient: Low-level HTTP client (Guzzle) for Fintoc refund endpoints (configured in etc/di.xml with timeout/http_errors).
  - RefundService: Business service orchestrating refund operations and Magento order state transitions.
  - Webhook/*: WebhookEvent (value object), WebhookRequestValidator, WebhookRequestParser, WebhookIdempotencyService, WebhookRouter, and Handler/* concrete handlers + shared AbstractWebhookHandler and AbstractPaymentIntentHandler.

- Controller/
  - Checkout/Create: Creates the Fintoc checkout session and responds with redirect_url to the frontend.
  - Checkout/Commit: Finalizes checkout session and handles success return.
  - Webhook/Index: CSRF-aware webhook endpoint performing signature validation, idempotency check, parse, then dispatches to router/handlers.
  - Adminhtml/*: Transactions grids and detail view; Refund create/cancel/save endpoints; Orders index (refunds eligible orders grid).

- Block/
  - Info/Fintoc: Payment info block for order/transaction details.
  - Checkout/Success: Displays payment info on success page.
  - Adminhtml/Order/View/TransactionInfo and Adminhtml/Transactions/View/*: Admin view layers for transactions and webhook history.

- Ui/Component
  - Listing/Column: Custom UI component columns for transaction/refund actions.

- Exceptions/
  - Rich exception set (ApiException, AuthenticationException, InvalidRequestException, RateLimitException, ResourceNotFoundException, ValidationException, WebhookSignatureError) to model API and webhook failures.

- Utils/
  - WebhookSignature: Utility for validating webhook signatures.

- view/
  - frontend/web/js/view/payment/method-renderer/fintoc-method.js: Checkout renderer that places order then calls fintoc/checkout/create to obtain redirect URL.
  - frontend/web/js/view/payment/fintoc-payments.js + template/payment/fintoc-form.html: UI binding and form template.
  - adminhtml/ui_component/*.xml: Data sources and grids for transactions and refundable orders.
  - adminhtml/layout/*.xml + templates: Admin pages and blocks wiring.

Configuration and DI highlights
- Preferences (etc/di.xml):
  - Api contracts are bound to Service/* or Model/* implementations.
  - Webhook interfaces are bound to Service/Webhook implementations. Router receives a map of event type → handler via <type ...><arguments><argument name="handlers" ... />.
  - CompositeConfigProvider registers Model\Ui\ConfigProvider for checkout configuration.
  - Logger is configured via a custom monolog handler and a virtual logger type injected into Payment model.
  - Controllers (Checkout/Create, Checkout/Commit, Webhook/Index) receive explicit constructor arguments (Guzzle client, Logger) via type arguments.

- System configuration (etc/adminhtml/system.xml + includes):
  - payment/fintoc_payment/* holds module config: activation, title, order_status, country restrictions, max_order_amount, sort_order, refunds toggles, logging and debug flags, API/Webhook secrets.

Database schema
- Table: fintoc_payment_transactions (see ./database-schema.md)
  - Unique(transaction_id)
  - Foreign key(order_id → sales_order.entity_id)
  - Indices on order_increment_id, type, status, created_at

Frontend checkout integration
- Renderer: Fintoc_Payment/payment/fintoc-form template via JS component. Flow:
  1) placeOrderAction → order placed in Magento.
  2) AJAX POST to fintoc/checkout/create → returns redirect_url.
  3) Browser redirects to Fintoc checkout.
  4) Fintoc redirects back to Commit controller on completion; Success page block shows summary.

Webhook pipeline
- Webhook/Index controller:
  - Validates CSRF bypass.
  - Reads raw request, validates signature via WebhookRequestValidator (uses configured webhook secret), enforces idempotency via WebhookIdempotencyService, parses payload into WebhookEvent via WebhookRequestParser, and dispatches to WebhookRouter.
  - Router selects specific handler by event type or falls back by examining payload object/status.
  - Handlers update Magento order/payment and persist Transaction entries via TransactionService.

Admin UI
- Menu: Sales → Fintoc Transactions and Refunds grids, with detail views, webhook history tab, and refund create form.

Logging
- Dedicated monolog channel "fintoc" with custom Handler; configurable verbosity via admin settings (debug, debug_level, log_sensitive_data, logging_enabled).

Testing
- Unit test example included: Test/Unit/Block/Info/FintocTest.php and basic TransactionStorageTest.php.

Related documents
- architecture.md — visual flow and component relationships
- configuration.md — detailed system config keys
- database-schema.md — table/columns/indexes
- webhook-flow.md — webhook API integration deep dive
- developer-guide.md — extending, events, code examples
