<?php
declare(strict_types=1);

namespace Fintoc\Payment\Plugin;

use Fintoc\Payment\Api\LoggerServiceInterface as LoggerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;

/**
 * Restores the shopping cart when a customer returns to the store
 * without completing a Fintoc payment (e.g. browser back button).
 *
 * Magento consumes the quote when the order is placed, so if the
 * customer never reaches Fintoc's cancel_url the cart appears empty.
 * This plugin detects that situation and restores the quote.
 */
class RestoreQuoteOnCartLoad
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Before the checkout cart page is rendered, check whether the last
     * order is a pending Fintoc payment whose quote should be restored.
     *
     * @param \Magento\Checkout\Controller\Cart\Index $subject
     */
    public function beforeExecute(\Magento\Checkout\Controller\Cart\Index $subject): void
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                return;
            }

            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() !== 'fintoc_payment') {
                return;
            }

            // Only restore if the order is still in a non-terminal pending state
            $state = $order->getState();
            if (!in_array($state, [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT], true)) {
                return;
            }

            // Skip if the Commit controller already marked this payment as successful
            // (covers the race window before the webhook transitions order to PROCESSING)
            $txStatus = $payment->getAdditionalInformation('fintoc_transaction_status');
            if ($txStatus === 'success') {
                return;
            }

            // Check that the quote hasn't already been restored / a new quote started
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId() && $quote->getItemsCount() > 0) {
                return;
            }

            $this->checkoutSession->restoreQuote();
            $this->logger->info('RestoreQuoteOnCartLoad: restored quote for pending Fintoc order ' . $order->getIncrementId());
        } catch (\Exception $e) {
            $this->logger->warning('RestoreQuoteOnCartLoad: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
}
