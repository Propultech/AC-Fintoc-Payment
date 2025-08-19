<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Transactions;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Backend\Model\UrlInterface as BackendUrl;

class View extends Template
{
    /** @var TransactionRepositoryInterface */
    private $transactionRepository;

    /** @var Json */
    private $json;

    /** @var BackendUrl */
    private $backendUrl;

    public function __construct(
        Context $context,
        TransactionRepositoryInterface $transactionRepository,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->transactionRepository = $transactionRepository;
        $this->json = $json;
        $this->backendUrl = $context->getUrlBuilder();
    }

    /**
     * Get the transaction loaded by request param 'id'
     */
    public function getTransaction(): ?TransactionInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            return null;
        }
        try {
            return $this->transactionRepository->getById($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether a value looks like JSON and can be pretty printed
     */
    private function tryDecode($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $decoded = $this->json->unserialize((string)$value);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Pretty print a JSON field or return raw safe string
     */
    public function prettyJson($value): string
    {
        $decoded = $this->tryDecode($value);
        if ($decoded !== null) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return (string)$value;
    }

    public function getTypeLabel(string $type): string
    {
        $map = [
            TransactionInterface::TYPE_AUTHORIZATION => (string)__('Authorization'),
            TransactionInterface::TYPE_CAPTURE => (string)__('Capture'),
            TransactionInterface::TYPE_REFUND => (string)__('Refund'),
            TransactionInterface::TYPE_VOID => (string)__('Void'),
            TransactionInterface::TYPE_WEBHOOK => (string)__('Webhook'),
        ];
        return $map[$type] ?? $type;
    }

    public function getStatusLabel(string $status): string
    {
        $map = [
            TransactionInterface::STATUS_PENDING => (string)__('Pending'),
            TransactionInterface::STATUS_SUCCESS => (string)__('Success'),
            TransactionInterface::STATUS_FAILED => (string)__('Failed'),
            TransactionInterface::STATUS_PROCESSING => (string)__('Processing'),
            TransactionInterface::STATUS_CANCELED => (string)__('Canceled'),
        ];
        return $map[$status] ?? $status;
    }

    public function formatAmount(?float $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '';
        }
        $formatted = number_format((float)$amount, 2, '.', ',');
        return $currency ? sprintf('%s %s', $currency, $formatted) : $formatted;
    }

    public function getOrderViewUrl(?string $orderIncrementId): ?string
    {
        if (!$orderIncrementId) {
            return null;
        }
        return $this->backendUrl->getUrl('sales/order/view', ['order_id' => null, 'increment_id' => $orderIncrementId]);
    }
}
