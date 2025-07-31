<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model\ResourceModel\Transaction;

use Fintoc\Payment\Model\Transaction;
use Fintoc\Payment\Model\ResourceModel\Transaction as TransactionResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Fintoc payment transaction collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Transaction::class, TransactionResource::class);
    }

    /**
     * Add order filter
     *
     * @param int|string $orderId
     * @return $this
     */
    public function addOrderFilter($orderId)
    {
        $this->addFieldToFilter('order_id', $orderId);
        return $this;
    }

    /**
     * Add order increment ID filter
     *
     * @param string $orderIncrementId
     * @return $this
     */
    public function addOrderIncrementIdFilter($orderIncrementId)
    {
        $this->addFieldToFilter('order_increment_id', $orderIncrementId);
        return $this;
    }

    /**
     * Add transaction ID filter
     *
     * @param string $transactionId
     * @return $this
     */
    public function addTransactionIdFilter($transactionId)
    {
        $this->addFieldToFilter('transaction_id', $transactionId);
        return $this;
    }

    /**
     * Add type filter
     *
     * @param string|array $type
     * @return $this
     */
    public function addTypeFilter($type)
    {
        $this->addFieldToFilter('type', $type);
        return $this;
    }

    /**
     * Add status filter
     *
     * @param string|array $status
     * @return $this
     */
    public function addStatusFilter($status)
    {
        $this->addFieldToFilter('status', $status);
        return $this;
    }

    /**
     * Add date range filter
     *
     * @param string $fromDate
     * @param string $toDate
     * @return $this
     */
    public function addDateRangeFilter($fromDate, $toDate)
    {
        $this->addFieldToFilter('created_at', ['from' => $fromDate, 'to' => $toDate]);
        return $this;
    }
}
