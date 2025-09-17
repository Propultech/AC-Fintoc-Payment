# Architecture and Flows — Fintoc_Payment

This document describes the architecture of the Fintoc_Payment module and the main runtime flows.

High-level architecture
- Presentation:
  - Frontend JS components (Knockout) render the payment method and handle redirect to Fintoc.
  - Adminhtml UI components (grids and detail screens) for transactions and refunds.
- Domain/Services:
  - TransactionService orchestrates creation and updates of transactions and histories.
  - RefundService executes refund operations via RefundsApiClient and updates orders.
  - Webhook pipeline (Validator → Parser → Idempotency → Router → Handlers) processes events from Fintoc.
- Persistence:
  - Transaction entity persisted in fintoc_payment_transactions via ResourceModel.
- Integration:
  - Guzzle HTTP client for calling Fintoc API.
  - Webhook endpoint (Controller) for inbound calls from Fintoc.
- Cross-cutting:
  - Logger with dedicated Monolog channel and custom handler.
  - ConfigurationService to read and normalize module configuration values.

Checkout flow (high-level)
```mermaid
sequenceDiagram
  participant C as Customer (Browser)
  participant KO as KO Renderer (fintoc-method.js)
  participant M as Magento Backend
  participant F as Fintoc Platform

  C->>KO: Selects Fintoc payment and clicks Place Order
  KO->>M: placeOrderAction (REST/GraphQL/internal)
  M-->>KO: Order placed (order ID)
  KO->>M: POST /fintoc/checkout/create
  M->>F: Create checkout session (RefundsApiClient or direct call)
  F-->>M: Redirect URL for checkout
  M-->>KO: { success: true, redirect_url }
  KO->>C: window.location = redirect_url
  C->>F: Complete payment on Fintoc
  F->>M: Webhook event(s)
  M->>M: Webhook validator→parser→idempotency→router→handler
  M-->>F: 200 OK
  F-->>C: Redirect back (Commit)
  C->>M: /fintoc/checkout/commit
  M-->>C: Order success page
```

Webhook processing pipeline
```mermaid
flowchart LR
  A[Webhook HTTP Request] --> B[WebhookRequestValidator\n(signature check)]
  B --> C[WebhookRequestParser\n(build WebhookEvent)]
  C --> D[WebhookIdempotencyService\n(ensure once-only)]
  D --> E[WebhookRouter\n(event type mapping)]
  E --> F[Concrete Handler\n(Refund/PaymentIntent/CheckoutSession)]
  F --> G[TransactionService\n(create/update Txn)]
  F --> H[Magento Order/Invoice\n(update state)]
  G --> I[(DB fintoc_payment_transactions)]
```

Component relationships (overview)
- Api contracts in Fintoc\Payment\Api are bound to concrete implementations in Model/ or Service/ via preferences declared in etc/di.xml.
- Webhook router receives a map of event types → handler instances via <type name="...WebhookRouter"><arguments>...</arguments></type> in etc/di.xml.
- Payment model is injected with a dedicated logger virtual type (FintocPaymentMethodLogger).
- Checkout Create/Commit controllers receive Guzzle client and Logger through typed arguments in etc/di.xml.

See also
- technical-overview.md — component inventory and responsibilities
- webhook-flow.md — deep dive into webhook implementation
- uml-class-diagram.[puml|mmd] — class relationships
