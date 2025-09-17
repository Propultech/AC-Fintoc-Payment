# Database Schema — Fintoc_Payment

The module defines a single table to persist detailed transaction records and histories.

Table: fintoc_payment_transactions
- Engine: InnoDB
- Comment: Fintoc Payment Transactions

Columns
- entity_id (int, PK, auto-increment) — Internal identifier
- transaction_id (varchar 255, unique) — Fintoc Transaction ID
- order_id (int, nullable, FK → sales_order.entity_id) — Magento Order internal ID
- order_increment_id (varchar 32, nullable) — Magento Order Increment ID
- reference (varchar 255, nullable) — Internal reference/correlation
- type (varchar 32, not null) — Transaction type (see Model\Source\TransactionType)
- status (varchar 32, not null) — Transaction status (see Model\Source\TransactionStatus)
- previous_status (varchar 32, nullable) — Previous transaction status
- amount (decimal(20,4), not null, default 0) — Monetary amount for the transaction
- currency (varchar 3, not null, default 'USD') — ISO currency code
- request_data (text, nullable) — Outbound API request payload (JSON)
- response_data (text, nullable) — Inbound API response payload (JSON)
- webhook_data (text, nullable) — Aggregated webhook payloads (JSON)
- status_history (text, nullable) — Chronological status changes (JSON)
- error_code (varchar 32, nullable) — Error code if failed
- error_message (text, nullable) — Error message if failed
- retry_attempts (smallint unsigned, not null, default 0) — Retry counter
- created_by (varchar 255, nullable) — Actor/system creating
- updated_by (varchar 255, nullable) — Actor/system updating
- ip_address (varchar 45, nullable) — Remote IP
- user_agent (varchar 255, nullable) — HTTP User-Agent
- created_at (timestamp, not null, default CURRENT_TIMESTAMP) — Creation timestamp
- updated_at (timestamp, not null, on update CURRENT_TIMESTAMP) — Last update timestamp

Constraints and indexes
- Primary key: entity_id
- Unique index: transaction_id
- Foreign key: order_id → sales_order(entity_id), onDelete = SET NULL
- Secondary indexes: order_increment_id, type, status, created_at

Model mapping
- Entity model: Fintoc\Payment\Model\Transaction (implements Api\Data\TransactionInterface)
- Resource model: Fintoc\Payment\Model\ResourceModel\Transaction
- Collection: Fintoc\Payment\Model\ResourceModel\Transaction\Collection
- Search results: Fintoc\Payment\Model\TransactionSearchResults

Grids
- Virtual type for grid collection configured in etc/di.xml as Fintoc\Payment\Model\ResourceModel\Transaction\Grid\Collection.

Notes
- status_history is maintained by TransactionService::updateStatusHistory and related methods.
- webhook_data may accumulate multiple webhook entries via TransactionService::appendWebhookData.
