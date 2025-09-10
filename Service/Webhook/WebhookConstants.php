<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

class WebhookConstants
{
    // Event type names
    public const EV_PI_SUCCEEDED = 'payment_intent.succeeded';
    public const EV_PI_FAILED    = 'payment_intent.failed';
    public const EV_PI_PENDING   = 'payment_intent.pending';
    public const EV_CS_FINISHED  = 'checkout_session.finished';
    public const EV_CS_EXPIRED   = 'checkout_session.expired';
    public const EV_REFUND_SUCCEEDED = 'refund.succeeded';
    public const EV_REFUND_FAILED = 'refund.failed';
    public const EV_REFUND_IN_PROGRESS = 'refund.in_progress';

    // Metadata keys that might hold the order increment id
    public const META_ORDER_KEYS = [
        'ecommerceOrderId',
        'ecommerce_order_id',
        'order',
        'order_id',
        'order_increment_id',
    ];
}
