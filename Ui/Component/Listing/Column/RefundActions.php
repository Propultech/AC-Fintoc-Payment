<?php
declare(strict_types=1);

namespace Fintoc\Payment\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RefundActions extends Column
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlBuilder,
        array              $components = [],
        array              $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!isset($item['parent_id'])) {
                    continue;
                }
                $orderId = (int)$item['parent_id'];
                $viewOrderUrl = $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);
                $requestRefundUrl = $this->urlBuilder->getUrl('fintoc_refunds/refund/create', ['order_id' => $orderId]);

                // Build transactions grid URL pre-filtered to this order's refunds
                $incrementId = isset($item['order_increment_id']) ? (string)$item['order_increment_id'] : null;
                $filters = ['type' => 'refund'];
                if ($incrementId !== null && $incrementId !== '') {
                    $filters['order_increment_id'] = $incrementId;
                }
                $transactionsUrl = $this->urlBuilder->getUrl('fintoc/transactions/index', ['filters' => $filters]);

                $item[$this->getData('name')] = [
                    'view' => [
                        'href' => $viewOrderUrl,
                        'label' => __('View Order'),
                        'hidden' => false,
                    ],
                    'refund' => [
                        'href' => $requestRefundUrl,
                        'label' => __('Request Refund'),
                        'hidden' => false,
                    ],
                    'transactions' => [
                        'href' => $transactionsUrl,
                        'label' => __('View Refund Transactions'),
                        'hidden' => false,
                    ],
                ];
            }
        }
        return $dataSource;
    }
}
