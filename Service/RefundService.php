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
    /**
     * @var RefundsApiClientInterface
     */
    private RefundsApiClientInterface $apiClient;
    /**
     * @var TransactionServiceInterface
     */
    private TransactionServiceInterface $transactionService;
    /**
     * @var TransactionRepositoryInterface
     */
    private TransactionRepositoryInterface $transactionRepository;
    /**
     * @var PaymentConfigServiceInterface
     */
    private PaymentConfigServiceInterface $configService;
    /**
     * @var Json
     */
    private Json $json;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RefundsApiClientInterface $apiClient
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param PaymentConfigServiceInterface $configService
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        RefundsApiClientInterface      $apiClient,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        PaymentConfigServiceInterface  $configService,
        Json                           $json,
        LoggerInterface                $logger
    )
    {
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
    public function requestRefund(OrderInterface $order, ?float $amount, ?string $currency = null, array $metadata = []): TransactionInterface
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

        $refundable = $this->getRefundableAmount($order);
        $allowPartial = (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_allow_partial');

        // Determine mode and validate when partial
        $sendAmount = $amount; // null for full, value for partial
        $recordAmount = $refundable; // default to full refundable for recording
        if ($amount !== null) {
            // Amount validations for partial
            if ($amount <= 0) {
                throw new LocalizedException(__('Refund amount must be greater than zero'));
            }
            if (!$allowPartial && $amount < $refundable) {
                throw new LocalizedException(__('Partial refunds are disabled; you must refund the full refundable amount (%1)', $refundable));
            }
            if ($amount - $refundable > 0.0001) {
                throw new LocalizedException(__('Refund amount exceeds refundable amount (%1)', $refundable));
            }
            $recordAmount = $amount;
        } else {
            // Full refund path: ensure there is something to refund
            if ($refundable <= 0) {
                throw new LocalizedException(__('Nothing to refund'));
            }
            // Mark mode as full if not already provided
            if (!isset($metadata['mode'])) {
                $metadata['mode'] = 'full';
            }
        }

        // Obtain Fintoc payment identifier to refund
        $paymentIntentId = $this->getPaymentIntentIdForOrder($order);
        if (!$paymentIntentId) {
            throw new LocalizedException(__('Missing Fintoc payment identifier for order'));
        }

        // Ensure order increment id is always present in metadata
        $metadata['ecommerce_order_id'] = (string)$order->getIncrementId();

        // Call API client
        $apiResult = $this->apiClient->createRefund($paymentIntentId, $sendAmount, $currency, $metadata);
        $externalId = (string)($apiResult['external_id'] ?? '');
        if ($externalId === '') {
            throw new LocalizedException(__('Refund API did not return a valid external ID'));
        }

        // Create local refund transaction as pending
        return $this->transactionService->createRefundTransaction(
            $externalId,
            $order,
            $recordAmount,
            $currency,
            ['payment_intent_id' => $paymentIntentId, 'metadata' => $metadata],
            (array)($apiResult['response'] ?? []),
            TransactionInterface::STATUS_PENDING,
            ['created_by' => 'admin']
        );
    }

    /**
     * Calculates and returns the maximum refundable amount for a given order.
     *
     * The refundable amount is determined by subtracting the total refunded amount
     * from the total paid amount. If a valid paid amount is not available, the order's
     * grand total is used as a fallback.
     *
     * @param OrderInterface $order The order for which the refundable amount is computed.
     * @return float The calculated refundable amount, rounded to two decimal places.
     */
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

    /**
     * Retrieves the payment intent ID associated with a given order. It first checks for the payment intent ID
     * in the order's payment additional information. If not found or invalid, it falls back to the latest non-refund transaction
     * associated with the order.
     *
     * @param OrderInterface $order The order for which the payment intent ID is being retrieved.
     * @return string|null The payment intent ID if found; otherwise, null.
     */
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

    /**
     * Attempts to cancel a refund request identified by the external refund ID. The method communicates with the API client
     * to cancel the refund and updates the transaction status in the system accordingly. If the cancellation fails or the
     * transaction cannot be updated, it logs the error and returns the cancellation status.
     *
     * @param string $externalRefundId The unique identifier of the refund to be canceled.
     * @return bool True if the
     */
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
}
