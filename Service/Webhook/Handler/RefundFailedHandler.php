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

class RefundFailedHandler extends AbstractWebhookHandler
{

    /**
     * Handles a webhook event for a refund failure, updating the transaction status,
     * appending webhook payloads, and adding a comment to the order's status history.
     *
     * @param WebhookEvent $event The event object containing the refund data and metadata.
     * @return void
     *
     * @throws LocalizedException If no order ID is found in the refund metadata.
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
        $failureCode = $refund['failure_code'] ?? ($refund['error_code'] ?? null);
        $failureMsg = $refund['error_reason'] ?? ($refund['error_message'] ?? null);

        try {
            if ($refundId !== '') {
                $tx = $this->transactionRepository->getByTransactionId($refundId);
                $this->transactionService->updateTransactionStatus($tx, TransactionInterface::STATUS_FAILED, [
                    'updated_by' => 'webhook',
                    'error_code' => is_string($failureCode) ? $failureCode : null,
                    'error_message' => is_string($failureMsg) ? $failureMsg : null,
                ]);
                $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_FAILED);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Refund transaction not found to mark as failed', [
                'refund_id' => $refundId,
                'order_id' => $orderId,
            ]);
            $tx = $this->getFirstTransactionByOrder($orderId);
            if ($tx) {
                $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_FAILED);
            }
        }

        $amount = $refund['amount'] ?? null;
        $currency = $refund['currency'] ?? null;
        $order->addCommentToStatusHistory(
            __(
                'Fintoc refund failed. Refund ID: %1%2%3',
                $refundId ?: 'N/A',
                ($amount !== null && $currency) ? __(', Amount: %1 %2', $amount, $currency) : '',
                ($failureMsg || $failureCode) ? __(', Reason: %1%2', (string)($failureMsg ?? ''), $failureCode ? (' (' . $failureCode . ')') : '') : ''
            )
        );
        $order->save();

        $this->logger->info('Refund failed recorded', [
            'order_id' => $orderId,
            'refund_id' => $refundId,
            'failure_code' => $failureCode,
        ]);
    }
}
