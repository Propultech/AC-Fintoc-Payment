# Webhook Flow — Fintoc_Payment

This document details how incoming webhook events from Fintoc are processed.

Endpoint
- URL pattern (frontend area): {base_url}/fintoc/webhook or {base_url}/fintoc/webhook/index
  - Route: etc/frontend/routes.xml → id="fintoc", frontName="fintoc"
  - Controller: Fintoc\Payment\Controller\Webhook\Index

Security and validation
- CSRF: Controller implements CsrfAwareActionInterface and bypasses CSRF for this endpoint following Magento guidance.
- Signature validation: WebhookRequestValidator reads the raw request body and validates the signature header using the configured webhook secret.
  - Secret config path: payment/fintoc_payment/webhook_secret (encrypted at rest)
  - Utility: Fintoc\Payment\Utils\WebhookSignature may be used to compute/verify the signature.
- Idempotency: WebhookIdempotencyService ensures events are processed only once. Subsequent duplicates are skipped safely.

Parsing
- WebhookRequestParser converts the incoming HTTP request into a WebhookEvent value object with, at minimum:
  - event_type (string)
  - payload (array)
  - object (subset of payload for quick routing)

Routing
- WebhookRouter selects a concrete handler based on event_type. If unknown, it infers a handler by inspecting object.type and status fields.
- Handlers registered in etc/di.xml under the router’s handlers argument:
  - refund.succeeded → Service\Webhook\Handler\RefundSucceededHandler
  - refund.failed → Service\Webhook\Handler\RefundFailedHandler
  - refund.in_progress → Service\Webhook\Handler\RefundInProgressHandler
  - payment_intent.succeeded → Service\Webhook\Handler\PaymentIntentSucceededHandler
  - payment_intent.failed → Service\Webhook\Handler\PaymentIntentFailedHandler
  - payment_intent.pending → Service\Webhook\Handler\PaymentIntentPendingHandler
  - checkout_session.finished → Service\Webhook\Handler\CheckoutSessionFinishedHandler
  - checkout_session.expired → Service\Webhook\Handler\CheckoutSessionExpiredHandler

Handling
- Shared base classes: AbstractWebhookHandler and AbstractPaymentIntentHandler centralize common logic.
- Typical handler responsibilities:
  - Resolve Magento order by reference/order_increment_id.
  - Transition order/payment states (create invoices, mark as processing/failed/closed as appropriate).
  - Persist/append Transaction entries via TransactionService, including webhook_data and status_history.
  - Log execution using the dedicated fintoc logger channel.

Persistence and audit
- Each meaningful webhook updates or inserts a row in fintoc_payment_transactions with the latest status and a growing status_history array.
- webhook_data accumulates payload snapshots for traceability.

Failure modes
- Invalid signature → 400/401 response and logged warning.
- Idempotent duplicate → 200 OK with no-op, logged as duplicate.
- Unhandled event type → 200 OK but logged as "Unhandled Fintoc webhook event" along with available keys to aid diagnosis.

Troubleshooting
- Ensure payment/fintoc_payment/webhook_secret matches the value configured in Fintoc Dashboard.
- Verify the endpoint is reachable from the internet and not blocked by firewalls.
- Check var/log/fintoc*.log for warnings/errors; increase debug_level if needed.
