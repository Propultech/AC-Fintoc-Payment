<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class PaymentIntentPendingHandler extends AbstractPaymentIntentHandler
{
    public function __construct(
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        \Fintoc\Payment\Api\TransactionServiceInterface $transactionService,
        \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository,
        Json $json
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, null);
    }

    public function handle(WebhookEvent $event): void
    {
        $pi = $this->normalizePaymentIntent($event->getObject());
        $orderId = $this->extractOrderIncrementId($pi);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($pi['metadata'] ?? '')));
        }
        $order = $this->loadOrderOrFail($orderId);

        // Upsert/update transaction as PENDING and append payload
        $this->upsertAndAppendPiTransaction($order, $pi, TransactionInterface::STATUS_PENDING, $event);

        // Payment info (data-driven)
        $this->setPiPaymentInfo($order, $pi, [
            'fintoc_pending_at' => date('Y-m-d H:i:s'),
        ]);

        // Trace comment
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment pending. ID: %1%2',
                $pi['id'] ?? 'N/A',
                isset($pi['amount'], $pi['currency']) ? __(', Amount: %1 %2', $pi['amount'], $pi['currency']) : ''
            )
        );
        $order->save();

        $this->logger->info('Payment intent pending recorded', ['order' => $orderId]);
    }
}
