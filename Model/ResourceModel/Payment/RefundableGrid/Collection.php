<?php
declare(strict_types=1);

namespace Fintoc\Payment\Model\ResourceModel\Payment\RefundableGrid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\Framework\App\Config\ScopeConfigInterface;

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

        // Join aggregated refund statuses per order to compute display status
        $connection = $this->getConnection();
        $transactionsTable = $this->getTable('fintoc_payment_transactions');
        $refundAggSelect = $connection->select()
            ->from(['t' => $transactionsTable], [
                'order_id',
                'has_success' => new \Zend_Db_Expr("MAX(CASE WHEN t.status = 'success' THEN 1 ELSE 0 END)"),
                'has_pending' => new \Zend_Db_Expr("MAX(CASE WHEN t.status IN ('pending','processing') THEN 1 ELSE 0 END)")
            ])
            ->where('t.type = ?', 'refund')
            ->group('t.order_id');

        $this->getSelect()->joinLeft(
            ['ra' => $refundAggSelect],
            'ra.order_id = so.entity_id',
            [
                'order_payment_status' => new \Zend_Db_Expr("CASE WHEN ra.has_success = 1 THEN 'Refunded' WHEN ra.has_pending = 1 THEN 'Refund requested' ELSE 'Paid' END")
            ]
        );

        // Ensure we only list payments made with Fintoc payment method
        $this->getSelect()->where('main_table.method = ?', 'fintoc_payment');

        // Apply filter by refundable order statuses from system config (if configured)
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = ObjectManager::getInstance()->get(ScopeConfigInterface::class);
        $statuses = (string)$scopeConfig->getValue('payment/fintoc_payment/refunds_refundable_statuses');
        if ($statuses !== '') {
            $statusesArray = array_values(array_filter(array_map('trim', explode(',', $statuses))));
            if (!empty($statusesArray)) {
                $this->getSelect()->where('so.status IN (?)', $statusesArray);
            }
        }

        return $this;
    }
}
