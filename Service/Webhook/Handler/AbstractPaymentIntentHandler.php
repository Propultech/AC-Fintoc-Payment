<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Sales\Model\Order;

abstract class AbstractPaymentIntentHandler extends AbstractWebhookHandler
{
    /**
        Normalize PI fields from snake_case to camelCase for known keys.
    */
    protected function normalizePaymentIntent(array $pi): array
    {
        if (!isset($pi['paymentType']) && isset($pi['payment_type'])) {
            $pi['paymentType'] = $pi['payment_type'];
        }
        if (!isset($pi['referenceId']) && isset($pi['reference_id'])) {
            $pi['referenceId'] = $pi['reference_id'];
        }
        if (!isset($pi['transactionDate']) && isset($pi['transaction_date'])) {
            $pi['transactionDate'] = $pi['transaction_date'];
        }
        if (!isset($pi['senderAccount']) && isset($pi['sender_account'])) {
            $pi['senderAccount'] = $pi['sender_account'];
        }
        if (!isset($pi['errorReason']) && isset($pi['error_reason'])) {
            $pi['errorReason'] = $pi['error_reason'];
        }
        return $pi;
    }

    /**
     * Upsert a PI transaction with a target status and append webhook payload.
     * Returns the transaction instance used.
     */
    protected function upsertAndAppendPiTransaction(
        Order $order,
        array $pi,
        string $status,
        WebhookEvent $event,
        array $extraMeta = []
    ): TransactionInterface {
        $existing = $this->getFirstTransactionByOrder($order->getIncrementId());
        if ($existing) {
            $meta = array_merge(['updated_by' => 'webhook'], $extraMeta);
            $this->transactionService->updateTransactionStatus($existing, $status, $meta);
            $this->appendWebhookPayload($existing, $event, $this->defaultEventForStatus($status));
            return $existing;
        }
        $created = $this->transactionService->createWebhookTransaction(
            (string)($pi['id'] ?? ('pi_' . uniqid())),
            $order,
            $pi['amount'] ?? null,
            $pi['currency'] ?? $order->getOrderCurrencyCode(),
            [],
            $status,
            array_merge(['created_by' => 'webhook'], $extraMeta)
        );
        $this->appendWebhookPayload($created, $event, $this->defaultEventForStatus($status));
        return $created;
    }

    /**
     * Map TransactionInterface::STATUS_* to a default event type
     */
    private function defaultEventForStatus(string $status): string
    {
        switch ($status) {
            case TransactionInterface::STATUS_SUCCESS:
                return WebhookConstants::EV_PI_SUCCEEDED;
            case TransactionInterface::STATUS_FAILED:
                return WebhookConstants::EV_PI_FAILED;
            case TransactionInterface::STATUS_PENDING:
            default:
                return WebhookConstants::EV_PI_PENDING;
        }
    }

    /**
     * Data-driven update of payment additional information for PI.
     */
    protected function setPiPaymentInfo(Order $order, array $pi, array $extra = []): void
    {
        $data = array_merge([
            'fintoc_payment_id' => $pi['id'] ?? null,
            'fintoc_payment_status' => $pi['status'] ?? null,
            'fintoc_payment_amount' => $pi['amount'] ?? null,
            'fintoc_payment_currency' => $pi['currency'] ?? null,
            'fintoc_payment_type' => $pi['paymentType'] ?? null,
            'fintoc_reference_id' => $pi['referenceId'] ?? null,
            'fintoc_transaction_date' => $pi['transactionDate'] ?? null,
        ], $extra);
        $payment = $order->getPayment();
        if ($payment) {
            foreach ($data as $key => $value) {
                if ($value !== null) {
                    $payment->setAdditionalInformation($key, $value);
                }
            }
            if (!empty($pi['id'])) {
                $payment->setLastTransId((string)$pi['id']);
            }
            $payment->save();
        }
    }
}
