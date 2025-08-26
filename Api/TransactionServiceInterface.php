<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface for Fintoc payment transaction service
 * @api
 */
interface TransactionServiceInterface
{
    /**
     * Create a pre-authorization transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface $order Magento order
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $requestData API request data
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
     */
    public function createPreAuthorizationTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $requestData = [],
        array          $additionalData = []
    ): TransactionInterface;

    /**
     * Create a post-authorization transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface $order Magento order
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $responseData API response data
     * @param string $status Transaction status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
     */
    public function createPostAuthorizationTransaction(
        string         $transactionId,
        OrderInterface $order,
        float          $amount,
        string         $currency,
        array          $responseData = [],
        string         $status = TransactionInterface::STATUS_SUCCESS,
        array          $additionalData = []
    ): TransactionInterface;

    /**
     * Create a capture transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface $order Magento order
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $requestData API request data
     * @param array $responseData API response data
     * @param string $status Transaction status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
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
    ): TransactionInterface;

    /**
     * Create a refund transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface $order Magento order
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $requestData API request data
     * @param array $responseData API response data
     * @param string $status Transaction status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
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
    ): TransactionInterface;

    /**
     * Create a void transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface $order Magento order
     * @param float $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $requestData API request data
     * @param array $responseData API response data
     * @param string $status Transaction status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
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
    ): TransactionInterface;

    /**
     * Create a webhook transaction
     *
     * @param string $transactionId Fintoc transaction ID
     * @param OrderInterface|null $order Magento order
     * @param float|null $amount Transaction amount
     * @param string $currency Transaction currency
     * @param array $webhookData Webhook data
     * @param string $status Transaction status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
     */
    public function createWebhookTransaction(
        string          $transactionId,
        ?OrderInterface $order = null,
        ?float          $amount = null,
        string          $currency = 'USD',
        array           $webhookData = [],
        string          $status = TransactionInterface::STATUS_SUCCESS,
        array           $additionalData = []
    ): TransactionInterface;

    /**
     * Update transaction status
     *
     * @param TransactionInterface $transaction Transaction to update
     * @param string $status New status
     * @param array $additionalData Additional data
     * @return TransactionInterface
     * @throws LocalizedException
     */
    public function updateTransactionStatus(
        TransactionInterface $transaction,
        string               $status,
        array                $additionalData = []
    ): TransactionInterface;

    /**
     * Get transaction history for an order
     *
     * @param OrderInterface $order
     * @return TransactionInterface[]
     */
    public function getTransactionHistoryForOrder(OrderInterface $order): array;

    /**
     * Get latest transaction for an order
     *
     * @param OrderInterface $order
     * @return TransactionInterface|null
     */
    public function getLatestTransactionForOrder(OrderInterface $order): ?TransactionInterface;
}
