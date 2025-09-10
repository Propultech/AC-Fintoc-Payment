<?php
declare(strict_types=1);

namespace Fintoc\Payment\Block\Adminhtml\Transactions\View;

use Magento\Backend\Block\Widget\Tabs as WidgetTabs;

class Tabs extends WidgetTabs
{
    /**
     * Initialize the construct method to set up the component with a specific ID, destination element, and title.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('fintoc_transactions_view_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Fintoc Transaction'));
    }
}
