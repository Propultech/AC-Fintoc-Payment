<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class RefundSucceededHandler extends AbstractWebhookHandler
{
    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;
    /**
     * @var CreditmemoManagementInterface
     */
    private CreditmemoManagementInterface $creditmemoManagement;

    /**
     * @param OrderFactory $orderFactory
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderFactory $orderFactory,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        TransactionServiceInterface $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        Json $json,
        LoggerInterface $logger
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, null);
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
    }

    /**
     * Handles a webhook event for a refund succeeded.
     *
     * @param WebhookEvent $event The webhook event containing refund data.
     * @return void
     * @throws LocalizedException If no order ID is found in refund metadata or in case of processing errors.
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
        $amount = $refund['amount'] ?? null;
        $currency = $refund['currency'] ?? null;

        // Update transaction to success and append payload
        $tx = null;
        if ($refundId !== '') {
            try {
                $tx = $this->transactionRepository->getByTransactionId($refundId);
                $this->transactionService->updateTransactionStatus($tx, TransactionInterface::STATUS_SUCCESS, [
                    'updated_by' => 'webhook'
                ]);
                $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_SUCCEEDED);
            } catch (\Throwable $e) {
                $this->logger->warning('Refund transaction not found to mark as succeeded', [
                    'refund_id' => $refundId,
                    'order_id' => $orderId,
                ]);
                $tx = $this->getFirstTransactionByOrder($orderId);
                if ($tx) {
                    $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_REFUND_SUCCEEDED);
                }
            }
        }

        // Create credit memo according to metadata
        $cmId = null;
        try {
            if ($order->canCreditmemo()) {
                $metadata = is_array($refund['metadata'] ?? null) ? $refund['metadata'] : [];
                $mode = $metadata['mode'] ?? ($metadata['Mode'] ?? null);
                $qtysRaw = $metadata['qtys'] ?? ($metadata['Qtys'] ?? null);
                $qtys = [];
                if (is_string($qtysRaw) && $qtysRaw !== '') {
                    try { $qtys = $this->json->unserialize($qtysRaw); } catch (\Throwable $e) { $qtys = []; }
                } elseif (is_array($qtysRaw)) {
                    $qtys = $qtysRaw;
                }
                // Normalize qtys to int=>float and filter > 0
                $qtysNorm = [];
                foreach ((array)$qtys as $k => $v) {
                    $q = (float)$v;
                    if ($q > 0) { $qtysNorm[(int)$k] = $q; }
                }

                if ($mode === 'items' && !empty($qtysNorm)) {
                    $data = ['qtys' => $qtysNorm];
                    // Include shipping and adjustments if provided in metadata
                    if (isset($metadata['shipping_amount']) && is_numeric($metadata['shipping_amount'])) {
                        $data['shipping_amount'] = (float)$metadata['shipping_amount'];
                    }
                    if (isset($metadata['adjustment_positive']) && is_numeric($metadata['adjustment_positive'])) {
                        $data['adjustment_positive'] = (float)$metadata['adjustment_positive'];
                    }
                    if (isset($metadata['adjustment_negative']) && is_numeric($metadata['adjustment_negative'])) {
                        $data['adjustment_negative'] = (float)$metadata['adjustment_negative'];
                    }
                    $cm = $this->creditmemoFactory->createByOrder($order, $data);
                    $this->creditmemoManagement->refund($cm, true);
                    $cmId = $cm->getIncrementId();
                } else {
                    $cm = $this->creditmemoFactory->createByOrder($order, []);
                    $this->creditmemoManagement->refund($cm, true);
                    $cmId = $cm->getIncrementId();
                }
            }
        } catch (\Throwable $ce) {
            $this->logger->critical('Credit memo creation failed on refund.succeeded', [
                'order_id' => $orderId,
                'refund_id' => $refundId,
                'error' => $ce->getMessage(),
            ]);
        }

        // Order history comment
        $order->addCommentToStatusHistory(
            __(
                'Fintoc refund succeeded. Refund ID: %1%2%3%4',
                $refundId ?: 'N/A',
                ($amount !== null && $currency) ? __(', Amount: %1 %2', $amount, $currency) : '',
                $cmId ? __(', Credit Memo: #%1', $cmId) : '',
                ($tx && $tx->getStatus()) ? __(', Transaction: %1', $tx->getStatus()) : ''
            )
        );
        $order->save();

        $this->logger->info('Refund succeeded processed', [
            'order_id' => $orderId,
            'refund_id' => $refundId,
            'creditmemo' => $cmId,
        ]);
    }
}
