<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class CheckoutSessionExpiredHandler extends AbstractWebhookHandler
{
    public function __construct(
        OrderFactory $orderFactory,
        \Fintoc\Payment\Api\TransactionServiceInterface $transactionService,
        \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository,
        Json $json,
        LoggerInterface $logger,
        CartRepositoryInterface $cartRepository
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, $cartRepository);
    }

    public function handle(WebhookEvent $event): void
    {
        $session = $event->getObject();
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }
        $order = $this->loadOrderOrFail($orderId);

        // Upsert or update transaction as canceled and append payload
        $externalId = (string)($session['id'] ?? ('cs_' . uniqid()));
        $amount = isset($session['amount']) ? ((float)$session['amount']) / 100 : null;
        $currency = $session['currency'] ?? $order->getOrderCurrencyCode();
        $this->upsertAndAppendTransactionRaw(
            $order,
            $externalId,
            $amount,
            $currency,
            TransactionInterface::STATUS_CANCELED,
            $event,
            WebhookConstants::EV_CS_EXPIRED,
            [
                'reference' => $session['referenceId'] ?? ($session['reference_id'] ?? null),
                'error_message' => 'Checkout session expired'
            ],
            $session
        );

        // Update payment info (data-driven)
        $this->setPaymentAdditionalInformation($order, [
            'fintoc_checkout_session_id' => $session['id'] ?? null,
            'fintoc_checkout_session_status' => 'expired',
            'fintoc_checkout_session_expired_at' => date('Y-m-d H:i:s'),
        ]);

        // Cancel order and add comment
        if ($order->getState() !== Order::STATE_CANCELED) {
            $order->cancel();
        }
        $order->addCommentToStatusHistory(
            __('Fintoc checkout session expired. Session ID: %1', $session['id'] ?? 'N/A')
        );
        $order->save();

        // Restore quote so the customer can retry checkout
        $this->restoreQuoteForOrder($order);

        $this->logger->info('Checkout session expired and order canceled', ['order' => $orderId]);
    }
}
