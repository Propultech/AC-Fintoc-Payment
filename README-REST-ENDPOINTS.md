# Fintoc Payment REST API Endpoints

This document describes the public REST API endpoints exposed by the Fintoc_Payment module in this Magento project.

Date generated: 2025-09-10 22:36

Notes
- These endpoints are available once the module is enabled and Magento caches are refreshed.
- Authentication is required. Use an admin integration token or admin user token with the required ACL permissions.
- All examples assume JSON requests/responses and use curl for demonstration.

Authentication
- Get an admin token:
  curl -X POST "https://your-magento-base/rest/V1/integration/admin/token" \
       -H "Content-Type: application/json" \
       -d '{"username":"admin","password":"your_password"}'

- Use the token in subsequent requests:
  -H "Authorization: Bearer <token>"

Required ACL Permissions
- Create refund: Fintoc_Refunds::create_refund
- List refund transactions: Fintoc_Refunds::view_transactions

Base paths
- REST base: https://your-magento-base/rest
- Store view code segment may be required depending on configuration (e.g., /default or /all). Examples below omit the store code for brevity.

Endpoints

1) Create a Refund
- Method: POST
- URL: /V1/fintoc/refunds
- Description: Creates a refund for a Magento order by its visible increment ID. If amount is omitted (null), a full refund is requested; otherwise a partial refund (if enabled).
- Request body fields:
  - orderIncrementId (string) Required. The order increment ID (e.g., 000000123)
  - amount (number|null) Optional. Null or omit for full refund. Provide a positive value for partial refund.
  - currency (string|null) Optional. Defaults to the order currency (e.g., CLP, USD).
  - metadata (object) Optional. Arbitrary key/value map (e.g., {"reason":"Customer request"}).

Example (full refund)
  curl -X POST "https://your-magento-base/rest/V1/fintoc/refunds" \
       -H "Content-Type: application/json" \
       -H "Authorization: Bearer <token>" \
       -d '{
             "orderIncrementId": "000000123",
             "amount": null,
             "currency": "CLP",
             "metadata": {"reason": "Customer request"}
           }'

Example (partial refund)
  curl -X POST "https://your-magento-base/rest/V1/fintoc/refunds" \
       -H "Content-Type: application/json" \
       -H "Authorization: Bearer <token>" \
       -d '{
             "orderIncrementId": "000000123",
             "amount": 1500.00,
             "currency": "CLP",
             "metadata": {"reason": "Damaged item"}
           }'

Successful response (example)
  {
    "entity_id": 1234,
    "transaction_id": "rf_2A8fYBz9...",           // External refund ID from Fintoc
    "type": "refund",
    "status": "pending",                          // Will update via webhooks
    "order_id": 5678,
    "order_increment_id": "000000123",
    "amount": 1500.0,
    "currency": "CLP",
    "payload": {"payment_intent_id": "pi_abc123", "metadata": {"reason": "Damaged item"}},
    "response": {},
    "created_at": "2025-09-10 22:36:00",
    "updated_at": "2025-09-10 22:36:00",
    "extension_attributes": {}
  }

Possible error responses (examples)
- 400 Bad Request: {"message": "Refund amount must be greater than zero"}
- 400 Bad Request: {"message": "Partial refunds are disabled; you must refund the full refundable amount (10000)"}
- 400 Bad Request: {"message": "Refund amount exceeds refundable amount (10000)"}
- 400 Bad Request: {"message": "Order is not paid with Fintoc payment method"}
- 400 Bad Request: {"message": "Refunds are disabled in configuration"}
- 404 Not Found: {"message": "Order with increment ID \"000000999\" not found"}

2) List Transactions
- Method: GET
- URL: /V1/fintoc/transactions
- Description: Lists refund transactions. Uses Magento's standard searchCriteria format for filtering, sorting, and pagination. The underlying repository returns Fintoc payment transactions; filter by type=refund to get refunds.

Common query params
- searchCriteria[currentPage]=1
- searchCriteria[pageSize]=20
- searchCriteria[sortOrders][0][field]=created_at
- searchCriteria[sortOrders][0][direction]=DESC
- Filter by type=refund:
  searchCriteria[filter_groups][0][filters][0][field]=type
  searchCriteria[filter_groups][0][filters][0][value]=refund
  searchCriteria[filter_groups][0][filters][0][condition_type]=eq
- Filter by order increment id:
  searchCriteria[filter_groups][1][filters][0][field]=order_increment_id
  searchCriteria[filter_groups][1][filters][0][value]=000000123
  searchCriteria[filter_groups][1][filters][0][condition_type]=eq

Example
  curl -X GET "https://your-magento-base/rest/V1/fintoc/transactions?searchCriteria[filter_groups][0][filters][0][field]=type&searchCriteria[filter_groups][0][filters][0][value]=refund&searchCriteria[filter_groups][0][filters][0][condition_type]=eq&searchCriteria[sortOrders][0][field]=created_at&searchCriteria[sortOrders][0][direction]=DESC&searchCriteria[currentPage]=1&searchCriteria[pageSize]=20" \
       -H "Authorization: Bearer <token>"

Successful response (example)
  {
    "items": [
      {
        "entity_id": 1234,
        "transaction_id": "rf_2A8fYBz9...",
        "type": "refund",
        "status": "pending",
        "order_id": 5678,
        "order_increment_id": "000000123",
        "amount": 1500.0,
        "currency": "CLP",
        "payload": {"payment_intent_id": "pi_abc123"},
        "response": {},
        "created_at": "2025-09-10 22:36:00",
        "updated_at": "2025-09-10 22:36:00",
        "extension_attributes": {}
      }
    ],
    "search_criteria": { /* echoed criteria */ },
    "total_count": 1
  }

Notes and Behavior
- Refund creation returns a transaction with status "pending"; final status is updated via Fintoc webhooks (refund.succeeded, refund.failed, refund.in_progress).
- For full refunds, omit amount or explicitly set it to null; for partial refunds ensure configuration allows partial refunds.
- The module enforces that the order was paid using the Fintoc payment method (code: fintoc_payment).
- The module prevents over-refunding by accounting for previously pending/succeeded refund transactions.

Troubleshooting
- Ensure the Fintoc module is enabled and configured (API secret, webhook secret, refunds enabled, etc.).
- Verify the admin token has the required ACL permissions listed above.
- Inspect var/log/fintoc.log for module logs if logging is enabled.
- Check transaction history in the Magento admin to see current statuses.
