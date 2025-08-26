<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Controller\Adminhtml\Transactions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class View extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'Fintoc_Payment::transactions';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * View transaction details page
     *
     * @return Page|Redirect
     */
    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('Missing transaction identifier.'));
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('fintoc/transactions/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Fintoc_Payment::transactions');
        $resultPage->getConfig()->getTitle()->prepend(__('Fintoc Transaction #%1', $id));

        return $resultPage;
    }
}
