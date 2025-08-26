<?php
declare(strict_types=1);

namespace Fintoc\Payment\Model\ResourceModel\Payment\RefundableGrid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult implements SearchResultInterface
{
    protected function _initSelect()
    {
        parent::_initSelect();
        // Join sales order table to fetch increment id and created at
        $this->getSelect()->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'so.entity_id = main_table.parent_id',
            [
                'order_increment_id' => 'so.increment_id',
                'order_created_at' => 'so.created_at'
            ]
        );

        // Join a derived table of non-canceled refund transactions to determine flag
        $connection = $this->getConnection();
        $transactionsTable = $this->getTable('fintoc_payment_transactions');
        $subSelect = $connection->select()
            ->from(['t' => $transactionsTable], ['order_id'])
            ->where('t.type = ?', 'refund')
            ->where('t.status != ?', 'canceled')
            ->group('t.order_id');

        $this->getSelect()->joinLeft(
            ['r' => $subSelect],
            'r.order_id = so.entity_id',
            ['has_non_canceled_refund' => new \Zend_Db_Expr('IF(r.order_id IS NULL, 0, 1)')]
        );

        // Ensure we only list payments made with Fintoc payment method
        $this->getSelect()->where('main_table.method = ?', 'fintoc_payment');

        return $this;
    }
}
