<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

use Fintoc\Payment\Api\Webhook\WebhookIdempotencyServiceInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;

class WebhookIdempotencyService implements WebhookIdempotencyServiceInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;
    /**
     * @var TransactionServiceInterface
     */
    private $transactionService;

    /**
     * @param TransactionRepositoryInterface $transactionRepository
     * @param TransactionServiceInterface $transactionService
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        TransactionServiceInterface $transactionService
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionService = $transactionService;
    }

    /**
     * @param string $eventId
     * @return bool
     */
    public function seen(string $eventId): bool
    {
        try {
            $existing = $this->transactionRepository->getByTransactionId($eventId);
            return (bool)($existing && $existing->getTransactionId());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $eventId
     * @return void
     */
    public function markSeen(string $eventId): void
    {
        try {
            $this->transactionService->createWebhookTransaction(
                $eventId,
                null,
                null,
                'USD',
                [],
                TransactionInterface::STATUS_SUCCESS,
                ['created_by' => 'webhook-idem']
            );
        } catch (\Exception $e) {
            // ignore; best-effort marker
        }
    }
}
