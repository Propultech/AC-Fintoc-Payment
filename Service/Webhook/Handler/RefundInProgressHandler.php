<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class RefundInProgressHandler extends AbstractWebhookHandler
{
    /**
     * @param OrderFactory $orderFactory
     * @param \Fintoc\Payment\Api\TransactionServiceInterface $transactionService
     * @param \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderFactory $orderFactory,
        \Fintoc\Payment\Api\TransactionServiceInterface $transactionService,
        \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository,
        Json $json,
        LoggerInterface $logger
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, null);
    }

    /**
     * @param WebhookEvent $event
     * @return void
     * @throws LocalizedException
     */
    public function handle(WebhookEvent $event): void
    {
        $refund = $event->getObject();
        $orderId = $this->extractOrderIncrementId($refund);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in refund metadata'));
        }
        $order = $this->loadOrderOrFail($orderId);

        $refundId = (string)($refund['id'] ?? '');
        if ($refundId !== '') {
            try {
                $tx = $this->transactionRepository->getByTransactionId($refundId);
                $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_IN_PROGRESS);
            } catch (\Throwable $e) {
                $tx = $this->getFirstTransactionByOrder($orderId);
                if ($tx) {
                    $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_IN_PROGRESS);
                }
            }
        }

        $amount = $refund['amount'] ?? null;
        $currency = $refund['currency'] ?? null;
        $order->addCommentToStatusHistory(
            __(
                'Fintoc refund in progress. Refund ID: %1%2',
                $refundId ?: 'N/A',
                ($amount !== null && $currency) ? __(', Amount: %1 %2', $amount, $currency) : ''
            )
        );
        $order->save();

        $this->logger->info('Refund in progress traced', [
            'order_id' => $orderId,
            'refund_id' => $refundId,
        ]);
    }
}
