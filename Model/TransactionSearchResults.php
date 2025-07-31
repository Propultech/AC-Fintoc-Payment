<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\Data\TransactionSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Transaction search results implementation
 */
class TransactionSearchResults extends SearchResults implements TransactionSearchResultsInterface
{
    /**
     * Get transactions list
     *
     * @return TransactionInterface[]
     */
    public function getItems()
    {
        return parent::getItems();
    }

    /**
     * Set transactions list
     *
     * @param TransactionInterface[] $items
     * @return $this
     */
    public function setItems(array $items)
    {
        return parent::setItems($items);
    }
}
