<?php
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Transactions\View\Tab;

use Fintoc\Payment\Block\Adminhtml\Transactions\View\Content;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class WebhookHistory extends Content implements TabInterface
{
    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getTabLabel()
    {
        return __('Webhook History');
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getTabTitle()
    {
        return __('Webhook History');
    }

    /**
     * @return true
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return false
     */
    public function isHidden()
    {
        return false;
    }
}
