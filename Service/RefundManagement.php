<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\RefundManagementInterface;
use Fintoc\Payment\Api\RefundServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;

class RefundManagement implements RefundManagementInterface
{
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private RefundServiceInterface $refundService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RefundServiceInterface $refundService
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->refundService = $refundService;
    }

    /**
     * @inheritDoc
     */
    public function createRefund(string $orderIncrementId, ?float $amount = null, ?string $currency = null, array $metadata = []): TransactionInterface
    {
        // Load order by increment ID
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderIncrementId, 'eq')
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($criteria)->getItems();
        $order = array_shift($orders);
        if (!$order) {
            throw new NoSuchEntityException(__('Order with increment ID "%1" not found', $orderIncrementId));
        }

        // Delegate to core refund service
        return $this->refundService->requestRefund($order, $amount, $currency, $metadata);
    }
}
