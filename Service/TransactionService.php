<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\Data\TransactionInterfaceFactory;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Transaction service implementation
 */
class TransactionService implements TransactionServiceInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var TransactionInterfaceFactory
     */
    private $transactionFactory;

    /**
     * @var LoggerServiceInterface
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param TransactionRepositoryInterface $transactionRepository
     * @param TransactionInterfaceFactory $transactionFactory
     * @param LoggerServiceInterface $logger
     * @param Json $json
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        TransactionInterfaceFactory    $transactionFactory,
        LoggerServiceInterface         $logger,
        Json                           $json
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * @inheritDoc
     */
    public function createPreAuthorizationTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $requestData = [],
        array          $additionalData = []
    ): TransactionInterface {
        $transaction = $this->transactionFactory->create();
        $transaction->setTransactionId($transactionId);
        $transaction->setOrderId((string)$order->getEntityId());
        $transaction->setOrderIncrementId($order->getIncrementId());
        $transaction->setType(TransactionInterface::TYPE_AUTHORIZATION);
        $transaction->setStatus(TransactionInterface::STATUS_PENDING);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);

        if (!empty($requestData)) {
            $transaction->setRequestData($this->json->serialize($requestData));
        }

        if (!empty($additionalData['reference'])) {
            $transaction->setReference($additionalData['reference']);
        }

        if (!empty($additionalData['created_by'])) {
            $transaction->setCreatedBy($additionalData['created_by']);
        }

        if (!empty($additionalData['ip_address'])) {
            $transaction->setIpAddress($additionalData['ip_address']);
        }

        if (!empty($additionalData['user_agent'])) {
            $transaction->setUserAgent($additionalData['user_agent']);
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_AUTHORIZATION,
            ['status' => TransactionInterface::STATUS_PENDING, 'amount' => $amount, 'currency' => $currency]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function createPostAuthorizationTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $responseData = [],
        string         $status = TransactionInterface::STATUS_SUCCESS,
        array          $additionalData = []
    ): TransactionInterface {
        try {
            // Try to find existing transaction
            $transaction = $this->transactionRepository->getByTransactionId($transactionId);

            // Update existing transaction
            $transaction->setPreviousStatus($transaction->getStatus());
            $transaction->setStatus($status);

            if (!empty($responseData)) {
                $transaction->setResponseData($this->json->serialize($responseData));
            }

            if (!empty($additionalData['error_code'])) {
                $transaction->setErrorCode($additionalData['error_code']);
            }

            if (!empty($additionalData['error_message'])) {
                $transaction->setErrorMessage($additionalData['error_message']);
            }

            if (!empty($additionalData['updated_by'])) {
                $transaction->setUpdatedBy($additionalData['updated_by']);
            }

            // Update status history
            $this->updateStatusHistory($transaction);

        } catch (Exception $e) {
            // Create new transaction if not found
            $transaction = $this->transactionFactory->create();
            $transaction->setTransactionId($transactionId);
            $transaction->setOrderId((string)$order->getEntityId());
            $transaction->setOrderIncrementId($order->getIncrementId());
            $transaction->setType(TransactionInterface::TYPE_AUTHORIZATION);
            $transaction->setStatus($status);
            $transaction->setAmount($amount);
            $transaction->setCurrency($currency);

            if (!empty($responseData)) {
                $transaction->setResponseData($this->json->serialize($responseData));
            }

            if (!empty($additionalData['reference'])) {
                $transaction->setReference($additionalData['reference']);
            }

            if (!empty($additionalData['error_code'])) {
                $transaction->setErrorCode($additionalData['error_code']);
            }

            if (!empty($additionalData['error_message'])) {
                $transaction->setErrorMessage($additionalData['error_message']);
            }

            if (!empty($additionalData['created_by'])) {
                $transaction->setCreatedBy($additionalData['created_by']);
            }

            if (!empty($additionalData['ip_address'])) {
                $transaction->setIpAddress($additionalData['ip_address']);
            }

            if (!empty($additionalData['user_agent'])) {
                $transaction->setUserAgent($additionalData['user_agent']);
            }
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_AUTHORIZATION,
            ['status' => $status, 'amount' => $amount, 'currency' => $currency]
        );

        return $transaction;
    }

    /**
     * Update the status history of a transaction
     *
     * @param TransactionInterface $transaction
     * @return void
     */
    private function updateStatusHistory(TransactionInterface $transaction): void
    {
        $history = [];

        // Get existing history if available
        if ($transaction->getStatusHistory()) {
            try {
                $history = $this->json->unserialize($transaction->getStatusHistory());
            } catch (Exception $e) {
                $this->logger->error('Error unserializing status history: ' . $e->getMessage());
                $history = [];
            }
        }

        // Add new status change to history
        $history[] = [
            'from' => $transaction->getPreviousStatus(),
            'to' => $transaction->getStatus(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $transaction->getUpdatedBy()
        ];

        // Update transaction with new history
        $transaction->setStatusHistory($this->json->serialize($history));
    }

    /**
     * @inheritDoc
     */
    public function createCaptureTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $requestData = [],
        array          $responseData = [],
        string         $status = TransactionInterface::STATUS_SUCCESS,
        array          $additionalData = []
    ): TransactionInterface {
        $transaction = $this->transactionFactory->create();
        $transaction->setTransactionId($transactionId);
        $transaction->setOrderId((string)$order->getEntityId());
        $transaction->setOrderIncrementId($order->getIncrementId());
        $transaction->setType(TransactionInterface::TYPE_CAPTURE);
        $transaction->setStatus($status);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);

        if (!empty($requestData)) {
            $transaction->setRequestData($this->json->serialize($requestData));
        }

        if (!empty($responseData)) {
            $transaction->setResponseData($this->json->serialize($responseData));
        }

        if (!empty($additionalData['reference'])) {
            $transaction->setReference($additionalData['reference']);
        }

        if (!empty($additionalData['error_code'])) {
            $transaction->setErrorCode($additionalData['error_code']);
        }

        if (!empty($additionalData['error_message'])) {
            $transaction->setErrorMessage($additionalData['error_message']);
        }

        if (!empty($additionalData['created_by'])) {
            $transaction->setCreatedBy($additionalData['created_by']);
        }

        if (!empty($additionalData['ip_address'])) {
            $transaction->setIpAddress($additionalData['ip_address']);
        }

        if (!empty($additionalData['user_agent'])) {
            $transaction->setUserAgent($additionalData['user_agent']);
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_CAPTURE,
            ['status' => $status, 'amount' => $amount, 'currency' => $currency]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function createRefundTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $requestData = [],
        array          $responseData = [],
        string         $status = TransactionInterface::STATUS_SUCCESS,
        array          $additionalData = []
    ): TransactionInterface {
        $transaction = $this->transactionFactory->create();
        $transaction->setTransactionId($transactionId);
        $transaction->setOrderId((string)$order->getEntityId());
        $transaction->setOrderIncrementId($order->getIncrementId());
        $transaction->setType(TransactionInterface::TYPE_REFUND);
        $transaction->setStatus($status);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);

        if (!empty($requestData)) {
            $transaction->setRequestData($this->json->serialize($requestData));
        }

        if (!empty($responseData)) {
            $transaction->setResponseData($this->json->serialize($responseData));
        }

        if (!empty($additionalData['reference'])) {
            $transaction->setReference($additionalData['reference']);
        }

        if (!empty($additionalData['error_code'])) {
            $transaction->setErrorCode($additionalData['error_code']);
        }

        if (!empty($additionalData['error_message'])) {
            $transaction->setErrorMessage($additionalData['error_message']);
        }

        if (!empty($additionalData['created_by'])) {
            $transaction->setCreatedBy($additionalData['created_by']);
        }

        if (!empty($additionalData['ip_address'])) {
            $transaction->setIpAddress($additionalData['ip_address']);
        }

        if (!empty($additionalData['user_agent'])) {
            $transaction->setUserAgent($additionalData['user_agent']);
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_REFUND,
            ['status' => $status, 'amount' => $amount, 'currency' => $currency]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function createVoidTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $requestData = [],
        array          $responseData = [],
        string         $status = TransactionInterface::STATUS_SUCCESS,
        array          $additionalData = []
    ): TransactionInterface {
        $transaction = $this->transactionFactory->create();
        $transaction->setTransactionId($transactionId);
        $transaction->setOrderId((string)$order->getEntityId());
        $transaction->setOrderIncrementId($order->getIncrementId());
        $transaction->setType(TransactionInterface::TYPE_VOID);
        $transaction->setStatus($status);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);

        if (!empty($requestData)) {
            $transaction->setRequestData($this->json->serialize($requestData));
        }

        if (!empty($responseData)) {
            $transaction->setResponseData($this->json->serialize($responseData));
        }

        if (!empty($additionalData['reference'])) {
            $transaction->setReference($additionalData['reference']);
        }

        if (!empty($additionalData['error_code'])) {
            $transaction->setErrorCode($additionalData['error_code']);
        }

        if (!empty($additionalData['error_message'])) {
            $transaction->setErrorMessage($additionalData['error_message']);
        }

        if (!empty($additionalData['created_by'])) {
            $transaction->setCreatedBy($additionalData['created_by']);
        }

        if (!empty($additionalData['ip_address'])) {
            $transaction->setIpAddress($additionalData['ip_address']);
        }

        if (!empty($additionalData['user_agent'])) {
            $transaction->setUserAgent($additionalData['user_agent']);
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_VOID,
            ['status' => $status, 'amount' => $amount, 'currency' => $currency]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function createWebhookTransaction(
        string          $transactionId,
        ?OrderInterface $order = null,
        ?float          $amount = null,
        string          $currency = 'USD',
        array           $webhookData = [],
        string          $status = TransactionInterface::STATUS_SUCCESS,
        array           $additionalData = []
    ): TransactionInterface {
        $transaction = $this->transactionFactory->create();
        $transaction->setTransactionId($transactionId);
        $transaction->setType(TransactionInterface::TYPE_WEBHOOK);
        $transaction->setStatus($status);

        if ($order) {
            $transaction->setOrderId((string)$order->getEntityId());
            $transaction->setOrderIncrementId($order->getIncrementId());
        }

        if ($amount !== null) {
            $transaction->setAmount($amount);
        }

        $transaction->setCurrency($currency);

        if (!empty($webhookData)) {
            $transaction->setWebhookData($this->json->serialize($webhookData));
        }

        if (!empty($additionalData['reference'])) {
            $transaction->setReference($additionalData['reference']);
        }

        if (!empty($additionalData['error_code'])) {
            $transaction->setErrorCode($additionalData['error_code']);
        }

        if (!empty($additionalData['error_message'])) {
            $transaction->setErrorMessage($additionalData['error_message']);
        }

        if (!empty($additionalData['created_by'])) {
            $transaction->setCreatedBy($additionalData['created_by']);
        }

        if (!empty($additionalData['ip_address'])) {
            $transaction->setIpAddress($additionalData['ip_address']);
        }

        if (!empty($additionalData['user_agent'])) {
            $transaction->setUserAgent($additionalData['user_agent']);
        }

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transactionId,
            TransactionInterface::TYPE_WEBHOOK,
            ['status' => $status, 'webhook_data' => $webhookData]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function updateTransactionStatus(
        TransactionInterface $transaction,
        string               $status,
        array                $additionalData = []
    ): TransactionInterface {
        $transaction->setPreviousStatus($transaction->getStatus());
        $transaction->setStatus($status);

        if (!empty($additionalData['error_code'])) {
            $transaction->setErrorCode($additionalData['error_code']);
        }

        if (!empty($additionalData['error_message'])) {
            $transaction->setErrorMessage($additionalData['error_message']);
        }

        if (!empty($additionalData['updated_by'])) {
            $transaction->setUpdatedBy($additionalData['updated_by']);
        }

        // Update status history
        $this->updateStatusHistory($transaction);

        $this->transactionRepository->save($transaction);

        $this->logger->logTransaction(
            $transaction->getTransactionId(),
            $transaction->getType(),
            ['status' => $status, 'previous_status' => $transaction->getPreviousStatus()]
        );

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function getLatestTransactionForOrder(OrderInterface $order): ?TransactionInterface
    {
        $transactions = $this->getTransactionHistoryForOrder($order);

        if (empty($transactions)) {
            return null;
        }

        // Sort transactions by created_at in descending order
        usort($transactions, function ($a, $b) {
            return strtotime($b->getCreatedAt()) - strtotime($a->getCreatedAt());
        });

        return reset($transactions);
    }

    /**
     * @inheritDoc
     */
    public function getTransactionHistoryForOrder(OrderInterface $order): array
    {
        $searchResults = $this->transactionRepository->getByOrderId((string)$order->getEntityId());
        return $searchResults->getItems();
    }

    /**
     * Append webhook payload into webhook_data grouped by event type.
     */
    public function appendWebhookData(
        TransactionInterface $transaction,
        string $eventType,
        array $payload
    ): TransactionInterface {
        $existing = $transaction->getWebhookData();
        $data = [];
        if ($existing) {
            try {
                $decoded = $this->json->unserialize($existing);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (Exception $e) {
                // If existing is invalid JSON, start fresh but keep a trace under a special key
                $data = ['__previous_invalid__' => $existing];
            }
        }

        $key = $eventType ?: 'unknown';
        if (!isset($data[$key])) {
            $data[$key] = [];
        }
        if (!is_array($data[$key])) {
            $data[$key] = [$data[$key]];
        }
        $data[$key][] = $payload;

        $transaction->setWebhookData($this->json->serialize($data));
        $this->transactionRepository->save($transaction);
        return $transaction;
    }
}
