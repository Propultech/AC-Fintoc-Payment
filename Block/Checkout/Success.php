<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Checkout;

use Exception;
use Fintoc\Payment\Model\Payment as FintocPaymentMethod;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Fintoc\Payment\Block\Traits\DateTimeFormatterTrait;

class Success extends Template
{
    use DateTimeFormatterTrait;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Json
     */
    private $json;

    public function __construct(
        Template\Context $context,
        CheckoutSession  $checkoutSession,
        Json             $json,
        array            $data = []
    )
    {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->json = $json;
    }

    /**
     * Build the rows to display as [label => value]
     */
    public function getRows(): array
    {
        $order = $this->getOrder();
        $payment = $order->getPayment();
        $ai = (array)$payment->getAdditionalInformation();

        $rows = [];

        // Transaction/payment IDs
        $transactionId = $ai['fintoc_transaction_id'] ?? $payment->getLastTransId() ?? null;
        $paymentId = $ai['fintoc_payment_id'] ?? null;
        if ($transactionId) {
            $rows[(string)__('Transaction ID')] = $transactionId;
        }
        if ($paymentId && $paymentId !== $transactionId) {
            $rows[(string)__('Payment ID')] = $paymentId;
        }

        // Status
        $status = $ai['fintoc_transaction_status'] ?? $ai['fintoc_payment_status'] ?? null;
        if ($status) {
            $rows[(string)__('Status')] = (string)$status;
        }

        // Amount and currency
        $amountStr = $this->formatAmountRow($order, $ai);
        if ($amountStr) {
            $rows[(string)__('Amount')] = $amountStr;
        }

        // Reference and dates
        if (!empty($ai['fintoc_reference_id'])) {
            $rows[(string)__('Reference ID')] = (string)$ai['fintoc_reference_id'];
        }
        if (!empty($ai['fintoc_transaction_date'])) {
            $rows[(string)__('Transaction Date')] = $this->formatDateTimeExact($ai['fintoc_transaction_date']);
        }
        if (!empty($ai['fintoc_transaction_completed_at'])) {
            $rows[(string)__('Completed At')] = $this->formatDateTimeExact($ai['fintoc_transaction_completed_at']);
        }
        if (!empty($ai['fintoc_transaction_canceled_at'])) {
            $rows[(string)__('Canceled At')] = $this->formatDateTimeExact($ai['fintoc_transaction_canceled_at']);
        }
        if (!empty($ai['fintoc_cancel_reason'])) {
            $rows[(string)__('Cancel Reason')] = (string)$ai['fintoc_cancel_reason'];
        }

        // Sender account details from webhook
        if (!empty($ai['fintoc_sender_account'])) {
            try {
                $sender = $this->json->unserialize((string)$ai['fintoc_sender_account']);
                if (is_array($sender)) {
                    $parts = [];
                    if (!empty($sender['institutionId'])) {
                        $parts[] = (string)$sender['institutionId'];
                    }
                    if (!empty($sender['holderId'])) {
                        $parts[] = (string)__('Holder ID: %1', (string)$sender['holderId']);
                    }
                    if (!empty($sender['number'])) {
                        // Mask account number for safety
                        $num = (string)$sender['number'];
                        $masked = (strlen($num) > 4) ? str_repeat('•', max(0, strlen($num) - 4)) . substr($num, -4) : $num;
                        $parts[] = (string)__('Account: %1', $masked);
                    }
                    if (!empty($sender['type'])) {
                        $parts[] = (string)$sender['type'];
                    }
                    if ($parts) {
                        $rows[(string)__('Sender Account')] = implode(' | ', $parts);
                    }
                }
            } catch (Exception $e) {
                // ignore malformed json
                unset($e);
            }
        }

        return $rows;
    }

    /**
     * Whether the block should be displayed
     */
    public function canShow(): bool
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return false;
        }
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }
        return $payment->getMethod() === FintocPaymentMethod::CODE;
    }

    /**
     * Get the last real order from session
     */
    public function getOrder(): ?Order
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            return $order && $order->getId() ? $order : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param Order $order
     * @param array $ai
     * @return string|null
     */
    private function formatAmountRow(Order $order, array $ai): ?string
    {
        if (isset($ai['fintoc_amount'])) {
            $amount = (float)$ai['fintoc_amount'];
            return $order->formatPrice($amount);
        }
        if (isset($ai['fintoc_payment_amount'])) {
            $amount = ((float)$ai['fintoc_payment_amount']) / 100; // cents to major units
            return $order->formatPrice($amount);
        }
        return null;
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        if ($this->canShow()) {
            return parent::toHtml();
        } else {
            return '';
        }
    }
}
