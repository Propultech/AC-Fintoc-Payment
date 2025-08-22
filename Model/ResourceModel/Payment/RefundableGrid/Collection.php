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
        // Join sales order table to fetch increment id
        $this->getSelect()->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'so.entity_id = main_table.parent_id',
            ['order_increment_id' => 'so.increment_id']
        );
        // Ensure we only list payments made with Fintoc payment method
        $this->getSelect()->where('main_table.method = ?', 'fintoc_payment');
        return $this;
    }
}
