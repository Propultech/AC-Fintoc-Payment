<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for Fintoc payment transaction search results
 * @api
 */
interface TransactionSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get transactions list
     *
     * @return TransactionInterface[]
     */
    public function getItems();

    /**
     * Set transactions list
     *
     * @param TransactionInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
