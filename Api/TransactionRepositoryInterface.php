<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\Data\TransactionSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Interface for Fintoc payment transaction repository
 * @api
 */
interface TransactionRepositoryInterface
{
    /**
     * Save transaction
     *
     * @param TransactionInterface $transaction
     * @return TransactionInterface
     * @throws LocalizedException
     */
    public function save(TransactionInterface $transaction): TransactionInterface;

    /**
     * Get transaction by ID
     *
     * @param int $entityId
     * @return TransactionInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): TransactionInterface;

    /**
     * Get transaction by transaction ID
     *
     * @param string $transactionId
     * @return TransactionInterface
     * @throws NoSuchEntityException
     */
    public function getByTransactionId(string $transactionId): TransactionInterface;

    /**
     * Get transactions by order ID
     *
     * @param string $orderId
     * @return TransactionSearchResultsInterface
     */
    public function getByOrderId(string $orderId): TransactionSearchResultsInterface;

    /**
     * Get transactions by order increment ID
     *
     * @param string $orderIncrementId
     * @return TransactionSearchResultsInterface
     */
    public function getByOrderIncrementId(string $orderIncrementId): TransactionSearchResultsInterface;

    /**
     * Get list of transactions
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return TransactionSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TransactionSearchResultsInterface;

    /**
     * Delete transaction
     *
     * @param TransactionInterface $transaction
     * @return bool
     * @throws LocalizedException
     */
    public function delete(TransactionInterface $transaction): bool;

    /**
     * Delete transaction by ID
     *
     * @param int $entityId
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $entityId): bool;
}
