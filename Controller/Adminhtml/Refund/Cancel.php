<?php
declare(strict_types=1);

namespace Fintoc\Payment\Controller\Adminhtml\Refund;

use Fintoc\Payment\Api\RefundServiceInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class Cancel extends Action
{
    public const ADMIN_RESOURCE = 'Fintoc_Refunds::cancel_refund';

    private RefundServiceInterface $refundService;
    private FormKeyValidator $formKeyValidator;

    public function __construct(
        Context $context,
        RefundServiceInterface $refundService,
        FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->refundService = $refundService;
        $this->formKeyValidator = $formKeyValidator;
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $request = $this->getRequest();
        $orderId = (int)$request->getParam('order_id');
        $refundId = (string)$request->getParam('refund_id');

        if (!$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        if ($refundId === '') {
            $this->messageManager->addErrorMessage(__('Missing refund identifier.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        try {
            $canceled = $this->refundService->cancelRefund($refundId);
            if ($canceled) {
                $this->messageManager->addSuccessMessage(__('Refund %1 has been canceled.', $refundId));
            } else {
                $this->messageManager->addErrorMessage(__('Refund %1 could not be canceled.', $refundId));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Unexpected error: %1', $e->getMessage()));
        }

        if ($orderId) {
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }
        return $redirect->setPath('fintoc_transactions/index');
    }
}
