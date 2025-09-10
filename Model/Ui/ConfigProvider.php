<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model\Ui;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Model\Payment;
use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Configuration provider for Fintoc payment method
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ConfigurationServiceInterface
     */
    protected $configService;

    /**
     * @param ConfigurationServiceInterface $configService
     */
    public function __construct(
        ConfigurationServiceInterface $configService
    ) {
        $this->configService = $configService;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                Payment::CODE => [
                    'title' => $this->configService->getTitle(),
                    'apiKey' => $this->configService->getApiSecret(),
                    'isDebugMode' => $this->configService->isDebugMode()
                ]
            ]
        ];
    }
}
