# System Configuration — Fintoc_Payment

This guide documents all relevant configuration keys for the Fintoc_Payment module and where to find them in Magento Admin.

Admin location
- Stores → Configuration → Sales → Payment Methods → Fintoc
- Also exposes a Refunds configuration group within the same Fintoc section.

Groups and fields (config paths)
- Payment Initiation (redirect)
  - payment/fintoc_payment/active — Enable the payment method (Yes/No)
  - payment/fintoc_payment/title — Method title shown to customers
  - payment/fintoc_payment/payment_action — Automatically Invoice All Items (Magento\Payment\Model\Source\Invoice)
  - payment/fintoc_payment/order_status — New Order Status (Magento\Sales\Model\Config\Source\Order\Status\NewStatus)
  - payment/fintoc_payment/allowspecific — Payment from Applicable Countries (All/Specific)
  - payment/fintoc_payment/specificcountry — Specific Countries (if allowspecific = Specific)
  - payment/fintoc_payment/max_order_amount — Maximum Order Amount (blank for no limit)
  - payment/fintoc_payment/sort_order — Sort order of method in checkout

- Basic Settings (settings)
  - payment/fintoc_payment/api_secret — Secret API Key (encrypted at rest)
  - payment/fintoc_payment/webhook_secret — Webhook Secret (encrypted at rest)
  - payment/fintoc_payment/logging_enabled — Enable Logging (Yes/No)
  - payment/fintoc_payment/debug_level — Debug Level (Fintoc\Payment\Model\Config\Source\DebugLevel)
  - payment/fintoc_payment/log_sensitive_data — Log Sensitive Data (Yes/No; only when logging_enabled)
  - payment/fintoc_payment/debug — Debug Mode (Yes/No)

- Refunds
  - payment/fintoc_payment/refunds_enabled — Enable Refunds (Yes/No)
  - payment/fintoc_payment/refunds_allow_partial — Allow Partial Refunds (Yes/No)
  - payment/fintoc_payment/refunds_auto_creditmemo — Auto-create Credit Memo on Refund Succeeded (Yes/No)
  - payment/fintoc_payment/refunds_status_pending — Order Status on Refund Pending
  - payment/fintoc_payment/refunds_status_succeeded — Order Status on Refund Succeeded
  - payment/fintoc_payment/refunds_status_failed — Order Status on Refund Failed
  - payment/fintoc_payment/refunds_status_canceled — Order Status on Refund Canceled
  - payment/fintoc_payment/refunds_refundable_statuses — Order statuses eligible to be refunded (multi-select)

Related XML
- etc/adminhtml/system.xml — Declares the Fintoc section under payment.
- etc/adminhtml/config/payment_methods/redirect.xml — Fields for Payment Initiation group.
- etc/adminhtml/config/settings.xml — API key, webhook secret, and logging/debug fields.
- etc/adminhtml/config/refunds.xml — Refunds-related fields.

Other platform configuration
- CSP (etc/csp_whitelist.xml): Ensures any Fintoc-hosted assets are allowed by Magento’s CSP. Adjust if needed based on the latest Fintoc domains.

Notes
- API and Webhook secrets are stored encrypted using Magento’s crypt key and the Encrypted backend model.
- Changing payment_action may affect whether orders are invoiced automatically and initial state transitions.
- If the method does not appear at checkout, verify: active = Yes, country restrictions, max_order_amount, and store view scope overrides.
