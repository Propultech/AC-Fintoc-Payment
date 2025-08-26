<?php
declare(strict_types=1);

namespace Fintoc\Payment\Controller\Adminhtml\Orders;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Fintoc_Refunds::orders_grid';

    /** @var PageFactory */
    private $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Fintoc_Refunds::orders');
        $resultPage->getConfig()->getTitle()->prepend(__('Fintoc Refundable Orders'));
        return $resultPage;
    }
}
