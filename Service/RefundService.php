<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Exception;
use Fintoc\Payment\Api\ConfigurationServiceInterface as PaymentConfigServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\RefundsApiClientInterface;
use Fintoc\Payment\Api\RefundServiceInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class RefundService implements RefundServiceInterface
{
    private RefundsApiClientInterface $apiClient;
    private TransactionServiceInterface $transactionService;
    private TransactionRepositoryInterface $transactionRepository;
    private PaymentConfigServiceInterface $configService;
    private Json $json;
    private LoggerInterface $logger;

    public function __construct(
        RefundsApiClientInterface      $apiClient,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        PaymentConfigServiceInterface  $configService,
        Json                           $json,
        LoggerInterface                $logger
    ) {
        $this->apiClient = $apiClient;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->configService = $configService;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @throws LocalizedException
     */
    public function requestRefund(OrderInterface $order, float $amount, ?string $currency = null, array $metadata = []): TransactionInterface
    {
        $currency = $currency ?: (string)$order->getOrderCurrencyCode();

        if (!(bool)$this->configService->getConfig('payment/fintoc_payment/refunds_enabled')) {
            throw new LocalizedException(__('Refunds are disabled in configuration'));
        }

        // Validate payment method
        $paymentObj = $order->getPayment();
        $method = '';
        if ($paymentObj) {
            $method = (string)$paymentObj->getMethod();
        }
        if ($method !== 'fintoc_payment') {
            throw new LocalizedException(__('Order is not paid with Fintoc payment method'));
        }

        // Amount validations
        if ($amount <= 0) {
            throw new LocalizedException(__('Refund amount must be greater than zero'));
        }

        $allowPartial = (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_allow_partial');
        $refundable = $this->getRefundableAmount($order);
        if (!$allowPartial && $amount < $refundable) {
            throw new LocalizedException(__('Partial refunds are disabled; you must refund the full refundable amount (%1)', $refundable));
        }
        if ($amount - $refundable > 0.0001) {
            throw new LocalizedException(__('Refund amount exceeds refundable amount (%1)', $refundable));
        }

        // Obtain Fintoc payment identifier to refund
        $paymentIntentId = $this->getPaymentIntentIdForOrder($order);
        if (!$paymentIntentId) {
            throw new LocalizedException(__('Missing Fintoc payment identifier for order'));
        }

        $amountCents = (int)round($amount * 100);

        // Call API client
        $apiResult = $this->apiClient->createRefund($paymentIntentId, $amountCents, $currency, $metadata);
        $externalId = (string)($apiResult['external_id'] ?? '');
        if ($externalId === '') {
            throw new LocalizedException(__('Refund API did not return a valid external ID'));
        }

        // Create local refund transaction as pending
        return $this->transactionService->createRefundTransaction(
            $externalId,
            $order,
            $amount,
            $currency,
            ['payment_intent_id' => $paymentIntentId, 'metadata' => $metadata],
            (array)($apiResult['response'] ?? []),
            TransactionInterface::STATUS_PENDING,
            ['created_by' => 'admin']
        );
    }

    private function getRefundableAmount(OrderInterface $order): float
    {
        $totalPaid = (float)$order->getTotalPaid();
        if ($totalPaid <= 0) {
            // Fallback to grand total if paid not set yet
            $totalPaid = (float)$order->getGrandTotal();
        }
        $history = $this->transactionService->getTransactionHistoryForOrder($order);
        $refunded = 0.0;
        foreach ($history as $t) {
            if ($t->getType() === TransactionInterface::TYPE_REFUND) {
                $status = $t->getStatus();
                if (in_array($status, [TransactionInterface::STATUS_SUCCESS, TransactionInterface::STATUS_PENDING], true)) {
                    $refunded += (float)$t->getAmount();
                }
            }
        }
        $refundable = max(0.0, $totalPaid - $refunded);
        return round($refundable, 2);
    }

    private function getPaymentIntentIdForOrder(OrderInterface $order): ?string
    {
        $payment = $order->getPayment();
        if ($payment) {
            $pi = $payment->getAdditionalInformation('fintoc_payment_id');
            if (is_string($pi) && $pi !== '') {
                return $pi;
            }
        }
        // Fallback to latest non-refund transaction
        $latest = $this->transactionService->getLatestTransactionForOrder($order);
        if ($latest && $latest->getType() !== TransactionInterface::TYPE_REFUND) {
            return $latest->getTransactionId();
        }
        return null;
    }

    public function cancelRefund(string $externalRefundId): bool
    {
        $result = $this->apiClient->cancelRefund($externalRefundId);
        $canceled = (bool)($result['canceled'] ?? false);

        try {
            $transaction = $this->transactionRepository->getByTransactionId($externalRefundId);
            $this->transactionService->updateTransactionStatus($transaction, $canceled ? TransactionInterface::STATUS_CANCELED : TransactionInterface::STATUS_FAILED, [
                'updated_by' => 'admin',
                'response' => $result['response'] ?? [],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update refund transaction after cancel', ['e' => $e]);
        }

        return $canceled;
    }

    public function handleWebhook(array $payload): void
    {
        // Expect either an event wrapper or a direct refund object
        $object = $payload['data']['object'] ?? $payload;
        if (!is_array($object)) {
            throw new LocalizedException(__('Invalid refund webhook payload'));
        }
        $externalId = (string)($object['id'] ?? $object['refund_id'] ?? '');
        if ($externalId === '') {
            throw new LocalizedException(__('Refund id missing in webhook'));
        }

        $status = strtolower((string)($object['status'] ?? ''));
        $mappedStatus = $this->mapRefundStatusToTransactionStatus($status);

        // Load transaction and update
        $transaction = $this->transactionRepository->getByTransactionId($externalId);
        $transaction->setWebhookData($this->json->serialize($object));
        $this->transactionService->updateTransactionStatus($transaction, $mappedStatus, ['updated_by' => 'webhook']);

        // Order status updates and auto credit memo can be added via observers if required
    }

    private function mapRefundStatusToTransactionStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'succeeded' || $status === 'success' || $status === 'completed') {
            return TransactionInterface::STATUS_SUCCESS;
        }
        if ($status === 'failed' || $status === 'error') {
            return TransactionInterface::STATUS_FAILED;
        }
        if ($status === 'canceled' || $status === 'cancelled') {
            return TransactionInterface::STATUS_CANCELED;
        }
        return TransactionInterface::STATUS_PENDING;
    }
}
