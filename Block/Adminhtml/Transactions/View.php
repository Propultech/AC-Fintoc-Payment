<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Transactions;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Block\Traits\DateTimeFormatterTrait;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Throwable;

class View extends \Magento\Backend\Block\Widget\Form\Container
{
    use DateTimeFormatterTrait;

    /** @var string */
    protected $_objectId = 'id';

    /** @var string */
    protected $_blockGroup = 'Fintoc_Payment';

    /** @var string */
    protected $_controller = 'adminhtml_transactions';

    /** @var TransactionRepositoryInterface */
    private $transactionRepository;

    /** @var Json */
    private $json;

    /**
     * @var
     */
    private $orderRepository;

    /**
     * @param Context $context
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param OrderRepositoryInterface $orderRepository
     * @param array $data
     */
    public function __construct(
        Context                        $context,
        TransactionRepositoryInterface $transactionRepository,
        Json                           $json,
        OrderRepositoryInterface       $orderRepository,
        array                          $data = []
    )
    {
        $this->transactionRepository = $transactionRepository;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->buttonList->remove('reset');
        $this->buttonList->remove('save');
        $this->buttonList->remove('delete');

        // Back to grid
        $this->buttonList->add(
            'back',
            [
                'label' => __("Back"),
                'onclick' => sprintf('setLocation("%s")', $this->getUrl('fintoc/transactions/index')),
                'class' => 'back'
            ],
            -1
        );

        // Refund button
        if ($this->canRefund()) {
            $this->buttonList->add(
                'fintoc_refund',
                [
                    'label' => __("Refund"),
                    'class' => 'save primary',
                    'onclick' => sprintf('setLocation("%s")', $this->getRefundCreateUrl())
                ]
            );
        }

        // Request Cancellation (requires POST) — will submit hidden form rendered by content template
        if ($this->canCancelRefund()) {
            $this->buttonList->add(
                'fintoc_refund_cancel',
                [
                    'label' => __("Request Cancellation"),
                    'class' => 'action-secondary',
                    'onclick' => 'if (confirm("Are you sure you want to request cancellation of this refund?")) { var f = document.getElementById("fintoc-refund-cancel-form"); if (f) { f.submit(); } }'
                ]
            );
        }
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param string $type
     * @return string
     */
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

    /**
     * @param string $status
     * @return string
     */
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

    /**
     * @param float|null $amount
     * @param string|null $currency
     * @return string
     */
    public function formatAmount(?float $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '';
        }
        $formatted = number_format((float)$amount, 2, '.', ',');
        return $currency ? sprintf('%s %s', $currency, $formatted) : $formatted;
    }

    /**
     * @param string|null $orderIncrementId
     * @return string|null
     */
    public function getOrderViewUrl(?string $orderIncrementId): ?string
    {
        if (!$orderIncrementId) {
            return null;
        }
        return $this->getUrl('sales/order/view', ['order_id' => null, 'increment_id' => $orderIncrementId]);
    }

    /**
     * @return string
     */
    public function getHeaderText(): string
    {
        $tr = $this->getTransaction();
        if ($tr) {
            $label = $tr->getTransactionId() ?: ('ID ' . $tr->getEntityId());
            return (string)__("Fintoc Transaction #%1", $label);
        }
        return (string)__("Fintoc Transaction");
    }

    /**
     * @return bool
     */
    public function canRefund(): bool
    {
        $tr = $this->getTransaction();
        if (!$tr) {
            return false;
        }

        $order = $this->orderRepository->get($tr->getOrderId());
        $additionalInformation = $order->getPayment()->getAdditionalInformation();
        $paymentIntentId = $additionalInformation['fintoc_payment_id'] ?? null;

        if (!$paymentIntentId) {
            return false;
        }

        return (string)$tr->getType() === TransactionInterface::TYPE_AUTHORIZATION && (bool)$tr->getOrderId();
    }

    /**
     * @return bool
     */
    public function canCancelRefund(): bool
    {
        $tr = $this->getTransaction();
        if (!$tr) {
            return false;
        }
        return (string)$tr->getType() === TransactionInterface::TYPE_REFUND
            && (string)$tr->getStatus() === TransactionInterface::STATUS_PENDING;
    }

    /**
     * @return string
     */
    public function getRefundCreateUrl(): string
    {
        return $this->getUrl('fintoc_refunds/refund/create', ['order_id' => $this->getOrderId()]);
    }

    /**
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->getUrl('fintoc_refunds/refund/cancel');
    }

    /**
     * @return int
     */
    public function getOrderId(): int
    {
        $tr = $this->getTransaction();
        return $tr && $tr->getOrderId() ? (int)$tr->getOrderId() : 0;
    }

    /**
     * @return string
     */
    public function getRefundId(): string
    {
        $tr = $this->getTransaction();
        return $tr ? (string)$tr->getTransactionId() : '';
    }
}
