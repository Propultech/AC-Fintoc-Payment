<?php
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Refund;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Create extends Template
{
    /** @var OrderRepositoryInterface */
    private OrderRepositoryInterface $orderRepository;

    /** @var TransactionServiceInterface */
    private TransactionServiceInterface $transactionService;

    /** @var ConfigurationServiceInterface */
    private ConfigurationServiceInterface $configService;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionServiceInterface $transactionService
     * @param ConfigurationServiceInterface $configService
     * @param array $data
     */
    public function __construct(
        Context                       $context,
        OrderRepositoryInterface      $orderRepository,
        TransactionServiceInterface   $transactionService,
        ConfigurationServiceInterface $configService,
        array                         $data = []
    )
    {
        parent::__construct($context, $data);
        $this->orderRepository = $orderRepository;
        $this->transactionService = $transactionService;
        $this->configService = $configService;
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return null;
        }
        try {
            $order = $this->orderRepository->get($orderId);
            return $order && $order->getEntityId() ? $order : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param Order $order
     * @return float
     */
    public function getRefundableAmount(Order $order): float
    {
        $totalPaid = (float)$order->getTotalPaid();
        if ($totalPaid <= 0) {
            $totalPaid = (float)$order->getGrandTotal();
        }
        $history = $this->transactionService->getTransactionHistoryForOrder($order);
        $refunded = 0.0;
        foreach ($history as $t) {
            if ($t->getType() === TransactionInterface::TYPE_REFUND) {
                $status = $t->getStatus();
                if (in_array($status, [TransactionInterface::STATUS_SUCCESS, TransactionInterface::STATUS_PENDING], true)) {
                    $refunded += (float)$t->getAmount();
                }
            }
        }
        $refundable = max(0.0, $totalPaid - $refunded);
        return round($refundable, 2);
    }

    /**
     * Get per-item refundable quantities and unit price info
     * @return array<int,array{item_id:int,name:string,sku:string,max_qty:float,price:float,price_incl_tax:float}>
     */
    public function getItemData(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $maxQty = (float)$item->getQtyInvoiced() - (float)$item->getQtyRefunded();
            if ($maxQty <= 0) {
                continue;
            }
            $price = (float)$item->getPrice();
            $priceIncl = (float)($item->getPriceInclTax() ?: ($price + (float)$item->getTaxAmount() / max(1.0, (float)$item->getQtyOrdered())));
            $items[] = [
                'item_id' => (int)$item->getItemId(),
                'name' => (string)$item->getName(),
                'sku' => (string)$item->getSku(),
                'max_qty' => round($maxQty, 2),
                'price' => round($price, 2),
                'price_incl_tax' => round($priceIncl, 2),
            ];
        }
        return $items;
    }

    /**
     * @param Order $order
     * @param float $amount
     * @return string
     */
    public function formatPrice(Order $order, float $amount): string
    {
        return $order->formatPrice($amount);
    }

    /**
     * @return bool
     */
    public function isPartialAllowed(): bool
    {
        return (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_allow_partial');
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getCurrencyCode(Order $order): string
    {
        return (string)$order->getOrderCurrencyCode();
    }
}
