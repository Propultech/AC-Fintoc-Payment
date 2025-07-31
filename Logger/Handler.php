<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Custom handler for Fintoc logs
 */
class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/fintoc.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
