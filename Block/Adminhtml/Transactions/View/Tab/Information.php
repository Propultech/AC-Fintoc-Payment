<?php
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Transactions\View\Tab;

use Fintoc\Payment\Block\Adminhtml\Transactions\View\Content;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class Information extends Content implements TabInterface
{
    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getTabLabel()
    {
        return __('Information');
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getTabTitle()
    {
        return __('Information');
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
