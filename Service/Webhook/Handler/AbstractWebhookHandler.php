<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

abstract class AbstractWebhookHandler
{
    /** @var OrderFactory */
    protected $orderFactory;
    /** @var TransactionServiceInterface */
    protected $transactionService;
    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;
    /** @var Json */
    protected $json;
    /** @var LoggerInterface */
    protected $logger;
    /** @var CartRepositoryInterface|null */
    protected $cartRepository;

    /**
     * @param OrderFactory $orderFactory
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface|null $cartRepository
     */
    public function __construct(
        OrderFactory                   $orderFactory,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        Json                           $json,
        LoggerInterface                $logger,
        ?CartRepositoryInterface       $cartRepository = null
    )
    {
        $this->orderFactory = $orderFactory;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->json = $json;
        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
    }


    /**
     * @param array $object
     * @return string|null
     */
    protected function extractOrderIncrementId(array $object): ?string
    {
        $metadata = $object['metadata'] ?? null;
        if (is_array($metadata)) {
            foreach (WebhookConstants::META_ORDER_KEYS as $k) {
                if (!empty($metadata[$k]) && is_string($metadata[$k])) {
                    return $metadata[$k];
                }
            }
        }
        return null;
    }

    /**
     * @param string $incrementId
     * @return Order
     * @throws LocalizedException
     */
    protected function loadOrderOrFail(string $incrementId): Order
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $incrementId));
        }
        return $order;
    }

    /**
     * @param string $orderIncrementId
     * @return TransactionInterface|null
     */
    protected function getFirstTransactionByOrder(string $orderIncrementId): ?TransactionInterface
    {
        $searchResults = $this->transactionRepository->getByOrderIncrementId($orderIncrementId);
        $items = $searchResults->getItems();
        return count($items) > 0 ? reset($items) : null;
    }

    /**
     * @param TransactionInterface $transaction
     * @param WebhookEvent $event
     * @param string $defaultType
     * @return void
     */
    protected function appendWebhookPayload(TransactionInterface $transaction, WebhookEvent $event, string $defaultType): void
    {
        $this->transactionService->appendWebhookData(
            $transaction,
            $event->getEventType() ?? $defaultType,
            $event->getFullPayload()
        );
    }

    /**
     * @param Order $order
     * @return void
     */
    protected function restoreQuoteForOrder(Order $order): void
    {
        if (!$this->cartRepository) {
            return;
        }
        try {
            $quoteId = (int)$order->getQuoteId();
            if ($quoteId) {
                $quote = $this->cartRepository->get($quoteId);
                if ($quote && $quote->getId()) {
                    $quote->setIsActive(true);
                    $quote->setReservedOrderId(null);
                    $this->cartRepository->save($quote);
                    $this->logger->info('Quote restored for order', [
                        'order' => $order->getIncrementId(),
                        'quote_id' => $quoteId
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Restore quote failed in webhook: ' . $e->getMessage());
        }
    }

    /**
     * Generic upsert + append for non-PI objects (e.g., checkout session)
     *
     * @param Order $order
     * @param string $externalId
     * @param float|null $amount
     * @param string|null $currency
     * @param string $status
     * @param WebhookEvent $event
     * @param string $defaultType
     * @param array $extraMeta
     * @param array|null $payloadToStore
     * @return TransactionInterface
     * @throws LocalizedException
     */
    protected function upsertAndAppendTransactionRaw(
        Order        $order,
        string       $externalId,
        ?float       $amount,
        ?string      $currency,
        string       $status,
        WebhookEvent $event,
        string       $defaultType,
        array        $extraMeta = [],
        ?array       $payloadToStore = null
    ): TransactionInterface
    {
        $existing = $this->getFirstTransactionByOrder($order->getIncrementId());
        if ($existing) {
            $this->transactionService->updateTransactionStatus($existing, $status, array_merge(['updated_by' => 'webhook'], $extraMeta));
            $this->appendWebhookPayload($existing, $event, $defaultType);
            return $existing;
        }
        $created = $this->transactionService->createWebhookTransaction(
            $externalId,
            $order,
            $amount,
            $currency ?? $order->getOrderCurrencyCode(),
            $payloadToStore ?? [],
            $status,
            array_merge(['created_by' => 'webhook'], $extraMeta)
        );
        $this->appendWebhookPayload($created, $event, $defaultType);
        return $created;
    }

    /**
     * @param Order $order
     * @param array $data
     * @return void
     * @throws \Exception
     */
    protected function setPaymentAdditionalInformation(Order $order, array $data): void
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $payment->setAdditionalInformation($key, $value);
            }
        }
        $payment->save();
    }
}
