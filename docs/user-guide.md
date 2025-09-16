# User Guide — Fintoc_Payment

This user guide is intended for Magento administrators and support agents who configure and operate the Fintoc payment method.

Overview
- Fintoc_Payment adds a redirect-based payment method to your Magento 2 store. Customers place their order and are redirected to Fintoc to complete payment. Status updates are synchronized back to Magento via webhooks.

Prerequisites
- A Fintoc account with API and Webhook credentials.
- Magento 2 store admin access.

Installation (if not preinstalled)
1) Ensure the module is installed and enabled by your developer or partner.
   - Example (for developers):
     - composer require fintoc/module-payment
     - bin/magento module:enable Fintoc_Payment
     - bin/magento setup:upgrade
     - bin/magento cache:flush

Configuration
1) Log in to Magento Admin.
2) Go to Stores → Configuration → Sales → Payment Methods → Fintoc Redirect.
   - [PLACEHOLDER FOR SCREENSHOT — admin-navigation-to-payment-methods]
3) In the Basic Settings group, enter your Fintoc credentials:
   - Secret API Key.
   - Webhook Secret.
   - Optional: Enable Logging, Debug Mode, set Debug Level, and toggle Log Sensitive Data.
   - [PLACEHOLDER FOR SCREENSHOT — fintoc-basic-settings]
4) In the Payment Initiation group, set:
   - Enabled = Yes.
   - Title = the name shown to customers (e.g., "Pay with Fintoc").
   - Automatically Invoice All Items (as desired).
   - New Order Status (usually Pending or Processing depending on your invoicing policy).
   - Applicable Countries and Specific Countries (if restricting availability).
   - Maximum Order Amount (leave blank for no limit).
   - Sort Order (display order in checkout).
   - [PLACEHOLDER FOR SCREENSHOT — fintoc-payment-initiation]
5) In the Refunds group (optional), configure:
   - Enable Refunds and Allow Partial Refunds.
   - Auto-create Credit Memo on Refund Succeeded.
   - Set order status transitions for refund pending/succeeded/failed/canceled.
   - Select refundable order statuses.
   - [PLACEHOLDER FOR SCREENSHOT — fintoc-refunds-settings]
6) Save Config and clear caches if prompted.

Webhook setup
1) In the Fintoc Dashboard, create a webhook pointing to:
   - {BASE_URL}/fintoc/webhook
   - Method: POST
2) Copy the Webhook Secret from Fintoc and paste it into Magento (Stores → Configuration → Sales → Payment Methods → Fintoc → Basic Settings → Webhook Secret).
3) Test delivery from Fintoc and confirm Magento responds with 200 OK.
   - [PLACEHOLDER FOR SCREENSHOT — fintoc-dashboard-webhook]

Customer checkout experience
1) Customer adds items to cart and proceeds to checkout.
2) On the Payment Method step, the customer selects Fintoc and clicks Place Order.
   - [PLACEHOLDER FOR SCREENSHOT — checkout-select-fintoc]
3) The customer is redirected to Fintoc to complete the payment.
   - [PLACEHOLDER FOR SCREENSHOT — fintoc-redirect-page]
4) After completion, the customer returns to your store and sees the Order Success page.
   - [PLACEHOLDER FOR SCREENSHOT — order-success-page]

Viewing transactions (Admin)
1) Go to Sales → Fintoc → Transactions.
2) Use filters to find a specific order or transaction.
3) Click into a transaction to view details and webhook history.
   - [PLACEHOLDER FOR SCREENSHOT — admin-transactions-grid]
   - [PLACEHOLDER FOR SCREENSHOT — admin-transaction-view]

Refunds (Admin)
- From Sales → Fintoc → Refundable Orders, open a refund form for an order and submit.
- Alternatively, use the standard Magento Credit Memo flow if enabled by your configuration.
  - [PLACEHOLDER FOR SCREENSHOT — admin-refundable-orders-grid]
  - [PLACEHOLDER FOR SCREENSHOT — admin-refund-create]

Tips and best practices
- Keep logging enabled in production at a reasonable Debug Level. Avoid logging sensitive data unless requested by support.
- Verify that your server is publicly reachable by Fintoc for webhooks (firewalls, maintenance mode, IP allowlists).
- If customers report missing payment method, check country restrictions and maximum order amount settings.

Troubleshooting
- Webhook errors (401/400): Re-check the Webhook Secret and ensure Fintoc sends the correct signature header.
- Payment method not visible: Ensure it is enabled, not exceeding Max Order Amount, and allowed for the current country.
- Refund not reflected: Confirm Refunds are enabled and that the webhook events for refund were delivered.

Support
- Provide recent entries from var/log/fintoc*.log when contacting support.
- Share the order increment ID and (if available) the Fintoc transaction_id for faster diagnosis.
