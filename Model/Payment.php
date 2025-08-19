<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Block\Info\Fintoc;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Fintoc Payment Method Model
 */
class Payment extends AbstractMethod
{
    /**
     * Payment method code
     */
    public const CODE = 'fintoc_payment';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_isInitializeNeeded = true;
    protected $_infoBlockType = Fintoc::class;
    protected $supportedCurrencyCodes = ['CLP'];

    /**
     * @var ConfigurationServiceInterface
     */
    private ConfigurationServiceInterface $configService;

    private StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param ConfigurationServiceInterface $configService
     * @param StoreManagerInterface $storeManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context                       $context,
        Registry                      $registry,
        ExtensionAttributesFactory    $extensionFactory,
        AttributeValueFactory         $customAttributeFactory,
        Data                          $paymentData,
        ScopeConfigInterface          $scopeConfig,
        Logger                        $logger,
        ConfigurationServiceInterface $configService,
        StoreManagerInterface         $storeManager,
        AbstractResource              $resource = null,
        AbstractDb                    $resourceCollection = null,
        array                         $data = []
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->storeManager = $storeManager;
        $this->configService = $configService;
    }

    /**
     * Availability for currency.
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    /**
     * Initialize payment
     *
     * @param string $paymentAction
     * @param object $stateObject
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $stateObject->setState(Order::STATE_NEW);
        $stateObject->setStatus($this->configService->getOrderStatus($storeId));
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * Check if payment method is active
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return $this->configService->isActive($storeId);
    }

    /**
     * Check whether payment method is available
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        // Check if method is active
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }

        // If no quote, we can't check the amount
        if ($quote === null) {
            return true;
        }

        // Get the maximum order amount
        $maxAmount = $this->configService->getMaxOrderAmount();

        // If no maximum amount is set, the payment method is available
        if ($maxAmount === null) {
            return true;
        }

        // Check if the order amount exceeds the maximum allowed amount
        $grandTotal = $quote->getGrandTotal();
        if ($grandTotal > $maxAmount) {
            return false;
        }

        return true;
    }
}
