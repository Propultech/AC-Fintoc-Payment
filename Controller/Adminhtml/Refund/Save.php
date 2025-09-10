<?php
declare(strict_types=1);

namespace Fintoc\Payment\Controller\Adminhtml\Refund;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\RefundServiceInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Fintoc_Refunds::create_refund';
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var RefundServiceInterface
     */
    private RefundServiceInterface $refundService;
    /**
     * @var ConfigurationServiceInterface
     */
    private ConfigurationServiceInterface $configService;
    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;
    /**
     * @var CreditmemoManagementInterface
     */
    private CreditmemoManagementInterface $creditmemoManagement;
    /**
     * @var FormKeyValidator
     */
    private FormKeyValidator $formKeyValidator;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param RefundServiceInterface $refundService
     * @param ConfigurationServiceInterface $configService
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param FormKeyValidator $formKeyValidator
     */
    public function __construct(
        Context                       $context,
        OrderRepositoryInterface      $orderRepository,
        RefundServiceInterface        $refundService,
        ConfigurationServiceInterface $configService,
        CreditmemoFactory             $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        FormKeyValidator              $formKeyValidator
    )
    {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->refundService = $refundService;
        $this->configService = $configService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $request = $this->getRequest();
        if (!$request->isPost()) {
            // Only POST is allowed here — redirect back to the form
            $this->messageManager->addErrorMessage(__('Invalid request method.'));
            return $redirect->setPath('fintoc_refunds/refund/create', ['order_id' => (int)$request->getParam('order_id')]);
        }

        if (!$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page.'));
            return $redirect->setPath('fintoc_refunds/refund/create', ['order_id' => (int)$request->getParam('order_id')]);
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

            // Enforce refundable only when 'payment_intent.succeeded' exists in webhook data and extract its data.id
            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            $paymentIntentId = $additionalInformation['fintoc_payment_id'] ?? null;
            if (!$paymentIntentId) {
                $this->messageManager->addErrorMessage(__('Transaction is not refundable: missing payment_intent.succeeded event in webhook data.'));
                return $redirect->setPath('fintoc_refunds/refund/create', ['order_id' => $orderId]);
            }
            $autoCm = (bool)$this->configService->getConfig('payment/fintoc_payment/refunds_auto_creditmemo');
            $comment = (string)$request->getParam('comment', '');

            if (trim($comment) === '') {
                $this->messageManager->addErrorMessage(__('Please enter a reason/comment for this refund.'));
                return $redirect->setPath('fintoc_refunds/refund/create', ['order_id' => $orderId]);
            }

            $qtys = (array)$request->getParam('qty', []);

            $amount = null; // null => full refund; numeric => partial/items
            $createdCreditMemo = null;

            if (!empty(array_filter($qtys, function ($q) {
                return (float)$q > 0;
            }))) {
                // Create a credit memo draft by items to compute the amount
                $data = ['qtys' => []];
                foreach ($qtys as $itemId => $qty) {
                    $q = (float)$qty;
                    if ($q > 0) {
                        $data['qtys'][(int)$itemId] = $q;
                    }
                }
                $creditMemo = $this->creditmemoFactory->createByOrder($order, $data);
                $amount = (float)$creditMemo->getGrandTotal();
                $createdCreditMemo = $creditMemo; // we will persist if configured
                $mode = 'items';
            } else {
                // Attempt full refund; but if remaining refundable is less than order grand total,
                // we must switch to items mode and include shipping/adjustments breakdown.
                $mode = 'full';
                try {
                    $cmRemain = $this->creditmemoFactory->createByOrder($order, []);
                    $remainingTotal = (float)$cmRemain->getGrandTotal();
                    if ($remainingTotal > 0 && $remainingTotal < (float)$order->getGrandTotal() - 0.0001) {
                        // Switch to items mode using the draft CM breakdown
                        $mode = 'items';
                        $amount = $remainingTotal;
                        $createdCreditMemo = $cmRemain;
                        // Build qtys from CM items
                        $qtysFromCm = [];
                        foreach ($cmRemain->getAllItems() as $cmItem) {
                            $orderItemId = (int)$cmItem->getOrderItemId();
                            $qty = (float)$cmItem->getQty();
                            if ($orderItemId && $qty > 0) {
                                $qtysFromCm[$orderItemId] = $qty;
                            }
                        }
                        // Override qtys to match the draft CM
                        $qtys = $qtysFromCm;
                    }
                } catch (\Throwable $e) {
                    // If CM draft fails, keep mode as full and proceed.
                }
            }

            $metadata = [
                'source' => 'admin',
                'mode' => $mode,
            ];
            if (trim($comment) !== '') {
                $metadata['comment'] = $comment;
            }
            if ($mode === 'items') {
                $metadata['qtys'] = $qtys;
                // If we created a draft CM, include shipping and adjustments to instruct the webhook handler
                if ($createdCreditMemo) {
                    $shippingAmount = (float)$createdCreditMemo->getShippingAmount();
                    if ($shippingAmount > 0) {
                        $metadata['shipping_amount'] = $shippingAmount;
                    }
                    $adjPos = (float)$createdCreditMemo->getAdjustmentPositive();
                    $adjNeg = (float)$createdCreditMemo->getAdjustmentNegative();
                    if ($adjPos !== 0.0) {
                        $metadata['adjustment_positive'] = $adjPos;
                    }
                    if ($adjNeg !== 0.0) {
                        $metadata['adjustment_negative'] = $adjNeg;
                    }
                }
            }

            // Request refund via Fintoc
            $this->refundService->requestRefund($order, $amount !== null ? (float)round($amount, 2) : null, null, $metadata);

            // Do not create credit memo here. It will be created only after Fintoc confirms refund via webhook.
            $this->messageManager->addSuccessMessage(__('Refund requested at Fintoc. A credit memo will be created automatically when Fintoc confirms the refund via webhook.'));

            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Unexpected error: %1', $e->getMessage()));
        }

        return $redirect->setPath('fintoc_refunds/refund/create', ['order_id' => $orderId]);
    }

}
