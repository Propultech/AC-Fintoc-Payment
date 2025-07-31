# Fintoc Payment Module for Adobe Commerce

This module integrates Fintoc payment gateway with Adobe Commerce (Magento 2), allowing merchants to accept payments through Fintoc's payment services.

## Installation

### Composer Installation
```bash
composer require fintoc/magento2-payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

### Manual Installation
1. Create the following directory structure in your Magento installation: `app/code/Fintoc/Payment`
2. Download the module files and place them in the directory
3. Run the following commands:
```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

## Configuration

1. Log in to your Magento Admin Panel
2. Navigate to **Stores > Configuration > Sales > Payment Methods**
3. Find the **Fintoc Payment** section
4. Configure the following settings:
   - **Enabled**: Set to "Yes" to enable the payment method
   - **Title**: The title displayed to customers during checkout
   - **API Key**: Your Fintoc API key (available in your Fintoc dashboard)
   - **Secret Key**: Your Fintoc Secret key
   - **Debug Mode**: Enable for troubleshooting (logs additional information)
   - **Payment Action**: Choose between "Authorize Only" or "Authorize and Capture"
   - **Sort Order**: Position in the list of payment methods

## Features

- Seamless integration with Fintoc payment gateway
- Support for various payment methods through Fintoc
- Real-time payment status updates via webhooks
- Detailed transaction management in Magento admin
- Comprehensive logging for troubleshooting
- Support for order status synchronization

## Transaction Management

The module stores transaction information in a dedicated database table, allowing merchants to:
- View transaction details in the order view
- Track payment statuses
- Monitor payment errors
- Reconcile transactions with Fintoc dashboard

## Webhook Support

The module supports Fintoc webhooks for real-time payment status updates. To configure webhooks:

1. In your Fintoc dashboard, set up a webhook endpoint pointing to:
   `https://your-store-url.com/fintoc/webhook/index`
2. Ensure the following events are enabled:
   - Payment Intent Succeeded
   - Payment Intent Failed

## Troubleshooting

If you encounter issues with the module:

1. Enable Debug Mode in the module configuration
2. Check the Magento logs at `var/log/fintoc_payment.log`
3. Verify your API credentials are correct
4. Ensure your server can communicate with Fintoc's API servers

## Support

For support, please contact:
- Email: support@fintoc.com
- Documentation: https://docs.fintoc.com

## License

This module is licensed under the GPL-3.0 License - see the LICENSE file for details.
