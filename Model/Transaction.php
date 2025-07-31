<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Fintoc payment transaction model
 */
class Transaction extends AbstractModel implements TransactionInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Transaction::class);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID) === null ? null : (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getTransactionId()
    {
        return (string)$this->getData(self::TRANSACTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setTransactionId($transactionId)
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderIncrementId()
    {
        return $this->getData(self::ORDER_INCREMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderIncrementId($orderIncrementId)
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $orderIncrementId);
    }

    /**
     * @inheritDoc
     */
    public function getReference()
    {
        return $this->getData(self::REFERENCE);
    }

    /**
     * @inheritDoc
     */
    public function setReference($reference)
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return (string)$this->getData(self::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return (string)$this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getPreviousStatus()
    {
        return $this->getData(self::PREVIOUS_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setPreviousStatus($previousStatus)
    {
        return $this->setData(self::PREVIOUS_STATUS, $previousStatus);
    }

    /**
     * @inheritDoc
     */
    public function getAmount()
    {
        return (float)$this->getData(self::AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAmount($amount)
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @inheritDoc
     */
    public function getCurrency()
    {
        return (string)$this->getData(self::CURRENCY);
    }

    /**
     * @inheritDoc
     */
    public function setCurrency($currency)
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    /**
     * @inheritDoc
     */
    public function getRequestData()
    {
        return $this->getData(self::REQUEST_DATA);
    }

    /**
     * @inheritDoc
     */
    public function setRequestData($requestData)
    {
        return $this->setData(self::REQUEST_DATA, $requestData);
    }

    /**
     * @inheritDoc
     */
    public function getResponseData()
    {
        return $this->getData(self::RESPONSE_DATA);
    }

    /**
     * @inheritDoc
     */
    public function setResponseData($responseData)
    {
        return $this->setData(self::RESPONSE_DATA, $responseData);
    }

    /**
     * @inheritDoc
     */
    public function getWebhookData()
    {
        return $this->getData(self::WEBHOOK_DATA);
    }

    /**
     * @inheritDoc
     */
    public function setWebhookData($webhookData)
    {
        return $this->setData(self::WEBHOOK_DATA, $webhookData);
    }

    /**
     * @inheritDoc
     */
    public function getStatusHistory()
    {
        return $this->getData(self::STATUS_HISTORY);
    }

    /**
     * @inheritDoc
     */
    public function setStatusHistory($statusHistory)
    {
        return $this->setData(self::STATUS_HISTORY, $statusHistory);
    }

    /**
     * @inheritDoc
     */
    public function getErrorCode()
    {
        return $this->getData(self::ERROR_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setErrorCode($errorCode)
    {
        return $this->setData(self::ERROR_CODE, $errorCode);
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage()
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setErrorMessage($errorMessage)
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function getRetryAttempts()
    {
        return (int)$this->getData(self::RETRY_ATTEMPTS);
    }

    /**
     * @inheritDoc
     */
    public function setRetryAttempts($retryAttempts)
    {
        return $this->setData(self::RETRY_ATTEMPTS, $retryAttempts);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedBy()
    {
        return $this->getData(self::CREATED_BY);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedBy($createdBy)
    {
        return $this->setData(self::CREATED_BY, $createdBy);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedBy()
    {
        return $this->getData(self::UPDATED_BY);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedBy($updatedBy)
    {
        return $this->setData(self::UPDATED_BY, $updatedBy);
    }

    /**
     * @inheritDoc
     */
    public function getIpAddress()
    {
        return $this->getData(self::IP_ADDRESS);
    }

    /**
     * @inheritDoc
     */
    public function setIpAddress($ipAddress)
    {
        return $this->setData(self::IP_ADDRESS, $ipAddress);
    }

    /**
     * @inheritDoc
     */
    public function getUserAgent()
    {
        return $this->getData(self::USER_AGENT);
    }

    /**
     * @inheritDoc
     */
    public function setUserAgent($userAgent)
    {
        return $this->setData(self::USER_AGENT, $userAgent);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
