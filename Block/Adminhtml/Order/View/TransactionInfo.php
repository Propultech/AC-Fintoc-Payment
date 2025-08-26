<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Order\View;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Fintoc\Payment\Block\Traits\DateTimeFormatterTrait;

/**
 * Block for displaying Fintoc transaction information in admin order view
 */
class TransactionInfo extends Template
{
    use DateTimeFormatterTrait;
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var TransactionServiceInterface
     */
    private $transactionService;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param TransactionServiceInterface $transactionService
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context                     $context,
        Registry                    $registry,
        TransactionServiceInterface $transactionService,
        Json                        $json,
        array                       $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->transactionService = $transactionService;
        $this->json = $json;
    }

    /**
     * Get transaction history for current order
     *
     * @return array
     */
    public function getTransactionHistory(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        return $this->transactionService->getTransactionHistoryForOrder($order);
    }

    /**
     * Get current order
     *
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Get latest transaction for current order
     *
     * @return TransactionInterface|null
     */
    public function getLatestTransaction(): ?TransactionInterface
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        return $this->transactionService->getLatestTransactionForOrder($order);
    }

    /**
     * Get transaction type label
     *
     * @param string $type
     * @return string
     */
    public function getTransactionTypeLabel(string $type): string
    {
        $types = [
            TransactionInterface::TYPE_AUTHORIZATION => __('Authorization'),
            TransactionInterface::TYPE_CAPTURE => __('Capture'),
            TransactionInterface::TYPE_REFUND => __('Refund'),
            TransactionInterface::TYPE_VOID => __('Void'),
            TransactionInterface::TYPE_WEBHOOK => __('Webhook')
        ];

        return $types[$type] ?? $type;
    }

    /**
     * Get transaction status label
     *
     * @param string $status
     * @return string
     */
    public function getTransactionStatusLabel(string $status): string
    {
        $statuses = [
            TransactionInterface::STATUS_PENDING => __('Pending'),
            TransactionInterface::STATUS_SUCCESS => __('Success'),
            TransactionInterface::STATUS_FAILED => __('Failed'),
            TransactionInterface::STATUS_PROCESSING => __('Processing'),
            TransactionInterface::STATUS_CANCELED => __('Canceled')
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Get transaction status class
     *
     * @param string $status
     * @return string
     */
    public function getTransactionStatusClass(string $status): string
    {
        $classes = [
            TransactionInterface::STATUS_PENDING => 'grid-severity-minor',
            TransactionInterface::STATUS_SUCCESS => 'grid-severity-notice',
            TransactionInterface::STATUS_FAILED => 'grid-severity-critical',
            TransactionInterface::STATUS_PROCESSING => 'grid-severity-minor',
            TransactionInterface::STATUS_CANCELED => 'grid-severity-major'
        ];

        return $classes[$status] ?? '';
    }

    /**
     * Format amount with currency
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public function formatAmount(float $amount, string $currency): string
    {
        return $this->getOrder()->getBaseCurrency()->formatPrecision(
            $amount,
            2,
            [],
            false,
            false
        );
    }

    /**
     * Get transaction status history
     *
     * @param TransactionInterface $transaction
     * @return array
     */
    public function getStatusHistory(TransactionInterface $transaction): array
    {
        return $this->decodeData($transaction->getStatusHistory());
    }

    /**
     * Decode JSON data
     *
     * @param string|null $data
     * @return array
     */
    public function decodeData(?string $data): array
    {
        if (empty($data)) {
            return [];
        }

        try {
            return $this->json->unserialize($data);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if payment method is Fintoc
     *
     * @return bool
     */
    public function isFintocPayment(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        return $order->getPayment()->getMethod() === 'fintoc_payment';
    }
}
