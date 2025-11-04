<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Webhook;

use Exception;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Fintoc\Payment\Api\LoggerServiceInterface as LoggerInterface;
use Fintoc\Payment\Api\Webhook\WebhookIdempotencyServiceInterface;
use Fintoc\Payment\Api\Webhook\WebhookRequestParserInterface;
use Fintoc\Payment\Api\Webhook\WebhookRequestValidatorInterface;
use Fintoc\Payment\Api\Webhook\WebhookRouterInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\OrderFactory;

/**
 * Webhook controller for Fintoc payment events
 */
class Index extends Action implements CsrfAwareActionInterface
{
    /** @var string|null */
    private $currentEventType = null;
    /** @var array */
    private $currentPayload = [];
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var LoggerServiceInterface
     */
    protected $logger;

    /** @var ConfigurationServiceInterface */
    protected $configService;
    /** @var WebhookRequestValidatorInterface */
    private $webhookValidator;
    /** @var WebhookRequestParserInterface */
    private $webhookParser;
    /** @var WebhookIdempotencyServiceInterface */
    private $webhookIdempotency;
    /** @var WebhookRouterInterface */
    private $webhookRouter;

    /**
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param Transaction $transaction
     * @param LoggerInterface $logger
     * @param ConfigurationServiceInterface $configService
     * @param WebhookRequestValidatorInterface $webhookValidator
     * @param WebhookRequestParserInterface $webhookParser
     * @param WebhookIdempotencyServiceInterface $webhookIdempotency
     * @param WebhookRouterInterface $webhookRouter
     */
    public function __construct(
        Context                            $context,
        JsonFactory                        $resultJsonFactory,
        OrderFactory                       $orderFactory,
        Transaction                        $transaction,
        LoggerInterface                    $logger,
        ConfigurationServiceInterface      $configService,
        WebhookRequestValidatorInterface   $webhookValidator,
        WebhookRequestParserInterface      $webhookParser,
        WebhookIdempotencyServiceInterface $webhookIdempotency,
        WebhookRouterInterface             $webhookRouter
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
        $this->transaction = $transaction;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->webhookValidator = $webhookValidator;
        $this->webhookParser = $webhookParser;
        $this->webhookIdempotency = $webhookIdempotency;
        $this->webhookRouter = $webhookRouter;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Get the request body using Magento request API
            $payload = $this->getRequest()->getContent();

            // Validate signature via service (throws on invalid)
            try {
                $headers = ['Fintoc-Signature' => (string)$this->getRequest()->getHeader('Fintoc-Signature')];
                $this->webhookValidator->validate($payload, $headers);
            } catch (\Throwable $sigErr) {
                $this->logger->critical('Fintoc webhook signature invalid or missing', [
                    'error' => $sigErr->getMessage(),
                ]);
                return $result->setStatusHeader(400)->setData(['error' => 'invalid signature']);
            }

            // Parse payload into DTO
            try {
                $event = $this->webhookParser->parse($payload);
            } catch (\Throwable $decodeErr) {
                $this->logger->critical('Fintoc webhook payload decode failed', [
                    'error' => $decodeErr->getMessage(),
                ]);
                return $result->setStatusHeader(400)->setData(['error' => 'invalid payload']);
            }

            // Conditional logging of payload depending on sensitive logging setting
            $logSensitive = (bool)$this->configService->isLogSensitiveDataEnabled();
            if ($logSensitive) {
                $this->logger->debug('Received Fintoc webhook', ['payload' => $event->getFullPayload()]);
            } else {
                $this->logger->debug('Received Fintoc webhook', ['payload_present' => $payload !== '']);
            }

            // Duplicate webhook resilience
            $eventId = $event->getEventId();
            if ($eventId) {
                if ($this->webhookIdempotency->seen($eventId)) {
                    $this->logger->info('Duplicate Fintoc webhook ignored', ['event_id' => $eventId]);
                    return $result->setData(['success' => true, 'duplicate' => true]);
                }
                $this->webhookIdempotency->markSeen($eventId);
            }

            // Dispatch all events via router; it has fallbacks when type is absent
            $this->webhookRouter->dispatch($event);

            return $result->setData(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setStatusHeader(500)->setData(['error' => $e->getMessage()]);
        }
    }
}
