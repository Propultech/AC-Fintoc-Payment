<?php
// phpcs:ignoreFile - CLI test helper script, not part of production code
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Test;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Test script for Fintoc transaction storage system
 *
 * Usage: php -f app/code/Fintoc/Payment/Test/TransactionStorageTest.php
 */

// Initialize Magento application
require __DIR__ . '/../../../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Get required services
$transactionService = $objectManager->get(TransactionServiceInterface::class);
$transactionRepository = $objectManager->get(TransactionRepositoryInterface::class);
$orderRepository = $objectManager->get(OrderRepositoryInterface::class);

// Test configuration
$testOrderId = 1; // Replace with a valid order ID from your system
$testTransactionId = 'test_' . uniqid();

try {
    echo "Starting Fintoc Transaction Storage Test\n";
    echo "----------------------------------------\n";

    // Get test order
    try {
        $order = $orderRepository->get($testOrderId);
        echo "Found test order #{$order->getIncrementId()}\n";
    } catch (Exception $e) {
        die("Error: Could not find test order with ID {$testOrderId}. Please update the test script with a valid order ID.\n");
    }

    // Test 1: Create pre-authorization transaction
    echo "\nTest 1: Creating pre-authorization transaction\n";
    $requestData = [
        'payment_method' => 'card',
        'amount' => 100.00,
        'currency' => 'USD',
        'description' => 'Test transaction',
    ];

    $transaction = $transactionService->createPreAuthorizationTransaction(
        $testTransactionId,
        $order,
        100.00,
        'USD',
        $requestData,
        [
            'reference' => 'TEST-REF-' . uniqid(),
            'created_by' => 'test_script',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHP Test Script'
        ]
    );

    echo "Created transaction with ID: {$transaction->getTransactionId()}\n";
    echo "Status: {$transaction->getStatus()}\n";
    echo "Type: {$transaction->getType()}\n";
    echo "Amount: {$transaction->getAmount()} {$transaction->getCurrency()}\n";

    // Test 2: Get transaction by ID
    echo "\nTest 2: Getting transaction by ID\n";
    $retrievedTransaction = $transactionRepository->getByTransactionId($testTransactionId);
    echo "Retrieved transaction with ID: {$retrievedTransaction->getTransactionId()}\n";
    echo "Status: {$retrievedTransaction->getStatus()}\n";

    // Test 3: Update transaction status
    echo "\nTest 3: Updating transaction status\n";
    $updatedTransaction = $transactionService->updateTransactionStatus(
        $retrievedTransaction,
        TransactionInterface::STATUS_SUCCESS,
        [
            'updated_by' => 'test_script'
        ]
    );
    echo "Updated transaction status to: {$updatedTransaction->getStatus()}\n";
    echo "Previous status: {$updatedTransaction->getPreviousStatus()}\n";

    // Test 4: Get transaction history for order
    echo "\nTest 4: Getting transaction history for order\n";
    $transactions = $transactionService->getTransactionHistoryForOrder($order);
    echo "Found " . count($transactions) . " transactions for order #{$order->getIncrementId()}\n";

    // Test 5: Get latest transaction for order
    echo "\nTest 5: Getting latest transaction for order\n";
    $latestTransaction = $transactionService->getLatestTransactionForOrder($order);
    if ($latestTransaction) {
        echo "Latest transaction ID: {$latestTransaction->getTransactionId()}\n";
        echo "Status: {$latestTransaction->getStatus()}\n";
        echo "Created at: {$latestTransaction->getCreatedAt()}\n";
    } else {
        echo "No transactions found for order\n";
    }

    // Test 6: Create post-authorization transaction
    echo "\nTest 6: Creating post-authorization transaction\n";
    $responseData = [
        'id' => $testTransactionId,
        'status' => 'succeeded',
        'amount' => 100.00,
        'currency' => 'USD',
    ];

    $postAuthTransaction = $transactionService->createPostAuthorizationTransaction(
        $testTransactionId,
        $order,
        100.00,
        'USD',
        $responseData,
        TransactionInterface::STATUS_SUCCESS,
        [
            'updated_by' => 'test_script'
        ]
    );

    echo "Updated transaction with ID: {$postAuthTransaction->getTransactionId()}\n";
    echo "Status: {$postAuthTransaction->getStatus()}\n";
    echo "Previous status: {$postAuthTransaction->getPreviousStatus()}\n";

    // Test 7: Create webhook transaction
    echo "\nTest 7: Creating webhook transaction\n";
    $webhookData = [
        'event' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'webhook_' . uniqid(),
                'status' => 'succeeded',
                'amount' => 100.00,
                'currency' => 'USD',
            ]
        ]
    ];

    $webhookTransaction = $transactionService->createWebhookTransaction(
        'webhook_' . uniqid(),
        $order,
        100.00,
        'USD',
        $webhookData,
        TransactionInterface::STATUS_SUCCESS,
        [
            'created_by' => 'test_script',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHP Test Script'
        ]
    );

    echo "Created webhook transaction with ID: {$webhookTransaction->getTransactionId()}\n";
    echo "Status: {$webhookTransaction->getStatus()}\n";

    echo "\nAll tests completed successfully!\n";

} catch (LocalizedException $e) {
    echo "Magento Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "General Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
