<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api\Webhook;

use Fintoc\Payment\Service\Webhook\WebhookEvent;

interface WebhookRequestParserInterface
{
    /**
     * Decode JSON and extract event type/object; throw \InvalidArgumentException on error.
     * @param string $rawBody
     */
    public function parse(string $rawBody): WebhookEvent;
}
