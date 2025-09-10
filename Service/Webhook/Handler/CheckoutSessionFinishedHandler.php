<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class CheckoutSessionFinishedHandler extends AbstractWebhookHandler
{
    public function __construct(
        OrderFactory $orderFactory,
        \Fintoc\Payment\Api\TransactionServiceInterface $transactionService,
        \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository,
        Json $json,
        LoggerInterface $logger
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, null);
    }

    public function handle(WebhookEvent $event): void
    {
        $session = $event->getObject();
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }
        $order = $this->loadOrderOrFail($orderId);

        $order->addCommentToStatusHistory(
            __(
                'Fintoc checkout session finished. Session ID: %1, Status: %2',
                $session['id'] ?? 'N/A',
                $session['status'] ?? 'finished'
            )
        );
        $order->save();

        $tx = $this->getFirstTransactionByOrder($orderId);
        if ($tx) {
            $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_CS_FINISHED);
        }

        $this->logger->debug('Checkout session finished traced', ['order' => $orderId]);
    }
}
