<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api\Data;

/**
 * Interface for Fintoc payment transaction data
 * @api
 */
interface TransactionInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const ENTITY_ID = 'entity_id';
    const TRANSACTION_ID = 'transaction_id';
    const ORDER_ID = 'order_id';
    const ORDER_INCREMENT_ID = 'order_increment_id';
    const REFERENCE = 'reference';
    const TYPE = 'type';
    const STATUS = 'status';
    const PREVIOUS_STATUS = 'previous_status';
    const AMOUNT = 'amount';
    const CURRENCY = 'currency';
    const REQUEST_DATA = 'request_data';
    const RESPONSE_DATA = 'response_data';
    const WEBHOOK_DATA = 'webhook_data';
    const STATUS_HISTORY = 'status_history';
    const ERROR_CODE = 'error_code';
    const ERROR_MESSAGE = 'error_message';
    const RETRY_ATTEMPTS = 'retry_attempts';
    const CREATED_BY = 'created_by';
    const UPDATED_BY = 'updated_by';
    const IP_ADDRESS = 'ip_address';
    const USER_AGENT = 'user_agent';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Transaction types
     */
    const TYPE_AUTHORIZATION = 'authorization';
    const TYPE_CAPTURE = 'capture';
    const TYPE_REFUND = 'refund';
    const TYPE_VOID = 'void';
    const TYPE_WEBHOOK = 'webhook';

    /**
     * Transaction statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_CANCELED = 'canceled';

    /**
     * Get entity ID
     *
     * @return int|null
     */
    public function getEntityId();

    /**
     * Set entity ID
     *
     * @param int $entityId
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setEntityId($entityId);

    /**
     * Get transaction ID
     *
     * @return string
     */
    public function getTransactionId();

    /**
     * Set transaction ID
     *
     * @param string $transactionId
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setTransactionId($transactionId);

    /**
     * Get order ID
     *
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order ID
     *
     * @param string|null $orderId
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setOrderId($orderId);

    /**
     * Get order increment ID
     *
     * @return string|null
     */
    public function getOrderIncrementId();

    /**
     * Set order increment ID
     *
     * @param string|null $orderIncrementId
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setOrderIncrementId($orderIncrementId);

    /**
     * Get reference
     *
     * @return string|null
     */
    public function getReference();

    /**
     * Set reference
     *
     * @param string|null $reference
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setReference($reference);

    /**
     * Get type
     *
     * @return string
     */
    public function getType();

    /**
     * Set type
     *
     * @param string $type
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setType($type);

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus();

    /**
     * Set status
     *
     * @param string $status
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setStatus($status);

    /**
     * Get previous status
     *
     * @return string|null
     */
    public function getPreviousStatus();

    /**
     * Set previous status
     *
     * @param string|null $previousStatus
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setPreviousStatus($previousStatus);

    /**
     * Get amount
     *
     * @return float
     */
    public function getAmount();

    /**
     * Set amount
     *
     * @param float $amount
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setAmount($amount);

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency();

    /**
     * Set currency
     *
     * @param string $currency
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setCurrency($currency);

    /**
     * Get request data
     *
     * @return string|null
     */
    public function getRequestData();

    /**
     * Set request data
     *
     * @param string|null $requestData
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setRequestData($requestData);

    /**
     * Get response data
     *
     * @return string|null
     */
    public function getResponseData();

    /**
     * Set response data
     *
     * @param string|null $responseData
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setResponseData($responseData);

    /**
     * Get webhook data
     *
     * @return string|null
     */
    public function getWebhookData();

    /**
     * Set webhook data
     *
     * @param string|null $webhookData
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setWebhookData($webhookData);

    /**
     * Get status history
     *
     * @return string|null
     */
    public function getStatusHistory();

    /**
     * Set status history
     *
     * @param string|null $statusHistory
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setStatusHistory($statusHistory);

    /**
     * Get error code
     *
     * @return string|null
     */
    public function getErrorCode();

    /**
     * Set error code
     *
     * @param string|null $errorCode
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setErrorCode($errorCode);

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage();

    /**
     * Set error message
     *
     * @param string|null $errorMessage
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setErrorMessage($errorMessage);

    /**
     * Get retry attempts
     *
     * @return int
     */
    public function getRetryAttempts();

    /**
     * Set retry attempts
     *
     * @param int $retryAttempts
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setRetryAttempts($retryAttempts);

    /**
     * Get created by
     *
     * @return string|null
     */
    public function getCreatedBy();

    /**
     * Set created by
     *
     * @param string|null $createdBy
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setCreatedBy($createdBy);

    /**
     * Get updated by
     *
     * @return string|null
     */
    public function getUpdatedBy();

    /**
     * Set updated by
     *
     * @param string|null $updatedBy
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setUpdatedBy($updatedBy);

    /**
     * Get IP address
     *
     * @return string|null
     */
    public function getIpAddress();

    /**
     * Set IP address
     *
     * @param string|null $ipAddress
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setIpAddress($ipAddress);

    /**
     * Get user agent
     *
     * @return string|null
     */
    public function getUserAgent();

    /**
     * Set user agent
     *
     * @param string|null $userAgent
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setUserAgent($userAgent);

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setCreatedAt($createdAt);

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return \Fintoc\Payment\Api\Data\TransactionInterface
     */
    public function setUpdatedAt($updatedAt);
}
