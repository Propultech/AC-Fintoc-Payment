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
        // GET-only: render the refund request page
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Fintoc_Refunds::orders');
        $resultPage->getConfig()->getTitle()->prepend(__('Request Fintoc Refund'));
        return $resultPage;
    }
}
