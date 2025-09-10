<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

use Fintoc\Payment\Api\Webhook\WebhookRequestParserInterface;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Serialize\Serializer\Json;
use InvalidArgumentException;

class WebhookRequestParser implements WebhookRequestParserInterface
{
    /**
     * @var Json
     */
    private $json;

    /**
     * @param Json $json
     */
    public function __construct(Json $json)
    {
        $this->json = $json;
    }

    /**
     * @param string $rawBody
     * @return \Fintoc\Payment\Service\Webhook\WebhookEvent
     */
    public function parse(string $rawBody): WebhookEvent
    {
        if ($rawBody === '') {
            throw new InvalidArgumentException('Empty webhook body');
        }
        $data = $this->json->unserialize($rawBody);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid webhook JSON');
        }
        $eventId = null;
        if (!empty($data['id']) && is_string($data['id'])) {
            $eventId = $data['id'];
        } elseif (!empty($data['data']['id']) && is_string($data['data']['id'])) {
            $eventId = $data['data']['id'];
        }
        $eventType = isset($data['type']) && is_string($data['type']) ? $data['type'] : null;
        $object = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $object = $data['data'];
        } elseif (isset($data['object']) && is_array($data['object'])) {
            $object = $data['object'];
        } else {
            $object = $data; // fallback
        }
        // Basic normalization: convert metadata keys to camelCase duplicates
        if (isset($object['metadata']) && is_array($object['metadata'])) {
            $normalized = [];
            foreach ($object['metadata'] as $k => $v) {
                $normalized[$k] = $v;
                $camel = preg_replace_callback('/_([a-z])/', function($m){ return strtoupper($m[1]); }, (string)$k);
                if ($camel !== $k) {
                    $normalized[$camel] = $v;
                }
            }
            $object['metadata'] = $normalized;
        }
        return new WebhookEvent($eventId, $eventType, $object, $data);
    }
}
