<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\Data\TransactionSearchResultsInterface;
use Fintoc\Payment\Api\Data\TransactionSearchResultsInterfaceFactory;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Model\ResourceModel\Transaction as TransactionResource;
use Fintoc\Payment\Model\ResourceModel\Transaction\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Transaction repository implementation
 */
class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * @var TransactionResource
     */
    private $resource;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var TransactionSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param TransactionResource $resource
     * @param TransactionFactory $transactionFactory
     * @param CollectionFactory $collectionFactory
     * @param TransactionSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        TransactionResource                      $resource,
        TransactionFactory                       $transactionFactory,
        CollectionFactory                        $collectionFactory,
        TransactionSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface             $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->transactionFactory = $transactionFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(TransactionInterface $transaction): TransactionInterface
    {
        try {
            $this->resource->save($transaction);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the transaction: %1',
                $exception->getMessage()
            ));
        }
        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function getByTransactionId(string $transactionId): TransactionInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addTransactionIdFilter($transactionId);
        $transaction = $collection->getFirstItem();

        if (!$transaction->getId()) {
            throw new NoSuchEntityException(__('Transaction with transaction_id "%1" does not exist.', $transactionId));
        }

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId): TransactionSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addOrderFilter($orderId);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderIncrementId(string $orderIncrementId): TransactionSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addOrderIncrementIdFilter($orderIncrementId);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TransactionSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * @inheritDoc
     */
    public function delete(TransactionInterface $transaction): bool
    {
        try {
            $this->resource->delete($transaction);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the transaction: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): TransactionInterface
    {
        $transaction = $this->transactionFactory->create();
        $this->resource->load($transaction, $entityId);
        if (!$transaction->getId()) {
            throw new NoSuchEntityException(__('Transaction with id "%1" does not exist.', $entityId));
        }
        return $transaction;
    }
}
