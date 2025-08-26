<?php
declare(strict_types=1);

namespace Fintoc\Payment\Controller\Adminhtml\Refund;

use Fintoc\Payment\Api\RefundServiceInterface;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\View\Result\PageFactory;

class Create extends Action
{
    public const ADMIN_RESOURCE = 'Fintoc_Refunds::create_refund';

    private PageFactory $resultPageFactory;
    private OrderRepositoryInterface $orderRepository;
    private RefundServiceInterface $refundService;
    private ConfigurationServiceInterface $configService;
    private CreditmemoFactory $creditmemoFactory;
    private CreditmemoManagementInterface $creditmemoManagement;
    private Json $json;
    private FormKeyValidator $formKeyValidator;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param RefundServiceInterface $refundService
     * @param ConfigurationServiceInterface $configService
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param Json $json
     * @param FormKeyValidator $formKeyValidator
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        OrderRepositoryInterface $orderRepository,
        RefundServiceInterface $refundService,
        ConfigurationServiceInterface $configService,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        Json $json,
        FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->orderRepository = $orderRepository;
        $this->refundService = $refundService;
        $this->configService = $configService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->json = $json;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            return $this->processPost($request);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Fintoc_Refunds::orders');
        $resultPage->getConfig()->getTitle()->prepend(__('Request Fintoc Refund'));
        return $resultPage;
    }

    /**
     * Processes a refund request for an order, validating input parameters, verifying order and payment details,
     * and interacting with the Fintoc refund service. Optionally, creates a credit memo based on configuration.
     *
     * @param RequestInterface $request The HTTP request containing refund parameters such as order ID, mode, amount, comment, and quantities.
     * @return ResultInterface A redirect result leading to the appropriate page based on the success or failure of the operation.
     */
    private function processPost(RequestInterface $request): ResultInterface
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page.'));
            return $redirect->setPath('*/*/create', ['order_id' => (int)$request->getParam('order_id')]);
        }

        $orderId = (int)$request->getParam('order_id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Missing order identifier.'));
            return $redirect->setPath('fintoc_refunds/orders/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getEntityId()) {
                throw new LocalizedException(__('Order not found.'));
            }

            // Validate payment method
            $payment = $order->getPayment();
            if (!$payment || (string)$payment->getMethod() !== 'fintoc_payment') {
                throw new LocalizedException(__('Only Fintoc orders can be refunded here.'));
            }

            $allowPartial = (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_allow_partial');
            $autoCm = (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_auto_creditmemo');

            $mode = (string)$request->getParam('mode', 'full'); // 'full' | 'amount' | 'items'
            $comment = (string)$request->getParam('comment', '');
            $qtys = (array)$request->getParam('qty', []);

            $amount = 0.0;
            $createdCreditmemo = null;

            if (!empty(array_filter($qtys, function ($q) { return (float)$q > 0; }))) {
                // Create credit memo draft by items to compute amount
                $data = ['qtys' => []];
                foreach ($qtys as $itemId => $qty) {
                    $q = (float)$qty;
                    if ($q > 0) {
                        $data['qtys'][(int)$itemId] = $q;
                    }
                }
                $creditmemo = $this->creditmemoFactory->createByOrder($order, $data);
                $amount = (float)$creditmemo->getGrandTotal();
                $createdCreditmemo = $creditmemo; // we will persist if configured
                $mode = 'items';
            } elseif ($mode === 'amount' && $allowPartial) {
                $amount = (float)$request->getParam('amount', 0);
            } else {
                // full refund or partial disabled
                $amount = $this->computeRefundableAmount($order);
                $mode = 'full';
            }

            if ($amount <= 0.0) {
                throw new LocalizedException(__('Refund amount must be greater than zero.'));
            }

            $metadata = [
                'source' => 'admin',
                'comment' => $comment,
                'mode' => $mode,
            ];
            if ($mode === 'items') {
                $metadata['qtys'] = $qtys;
            }

            // Request refund via Fintoc
            $this->refundService->requestRefund($order, (float)round($amount, 2), null, $metadata);

            // Optionally create offline credit memo now
            if ($autoCm && $createdCreditmemo) {
                $this->creditmemoManagement->refund($createdCreditmemo, true);
                $this->messageManager->addSuccessMessage(__('Refund requested at Fintoc and Credit Memo #%1 created.', $createdCreditmemo->getIncrementId())) ;
            } elseif ($autoCm && !$createdCreditmemo && $mode !== 'items') {
                // Can't reliably create credit memo without item breakdown; skip but inform user
                $this->messageManager->addSuccessMessage(__('Refund requested at Fintoc. Credit Memo will be created automatically by project-specific observer on success.'));
            } else {
                $this->messageManager->addSuccessMessage(__('Refund requested at Fintoc.'));
            }

            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Unexpected error: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/create', ['order_id' => $orderId]);
    }

    /**
     * Computes the refundable amount for a given order by evaluating the total paid and subtracting the total refunded.
     *
     * @param Order $order The order for which the refundable amount is calculated.
     * @return float The calculated refundable amount, rounded to two decimal places.
     */
    private function computeRefundableAmount(Order $order): float
    {
        $totalPaid = (float)$order->getTotalPaid();
        if ($totalPaid <= 0) {
            $totalPaid = (float)$order->getGrandTotal();
        }
        // If TransactionService is available in DI, we could compute from history; but keep simple: subtract existing refunds
        $refunded = (float)$order->getTotalRefunded();
        $refundable = max(0.0, $totalPaid - $refunded);
        return round($refundable, 2);
    }
}
