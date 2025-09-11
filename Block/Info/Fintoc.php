<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Info;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Sales\Model\Order;
use Fintoc\Payment\Block\Traits\DateTimeFormatterTrait;

class Fintoc extends Info
{
    use DateTimeFormatterTrait;
    /**
     * @var Json
     */
    private $json;

    /**
     * @param Context $context
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        Json    $json,
        array   $data = []
    ) {
        parent::__construct($context, $data);
        $this->json = $json;
    }

    /**
     * Prepare specific information for payment info block (admin and frontend)
     *
     * @param DataObject|null $transport
     * @return DataObject
     * @throws LocalizedException
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if ($this->_paymentSpecificInformation !== null) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);

        $info = $this->getInfo();
        $ai = (array)$info->getAdditionalInformation();
        $order = $info->getOrder();

        $data = [];

        // Transaction/payment IDs
        $transactionId = $ai['fintoc_transaction_id'] ?? $info->getLastTransId() ?? null;
        $paymentId = $ai['fintoc_payment_id'] ?? null;
        if ($transactionId) {
            $data[(string)__('Transaction ID')] = (string)$transactionId;
        }
        if ($paymentId && $paymentId !== $transactionId) {
            $data[(string)__('Payment ID')] = (string)$paymentId;
        }

        // Status
        $status = $ai['fintoc_transaction_status'] ?? $ai['fintoc_payment_status'] ?? null;
        if ($status) {
            $data[(string)__('Status')] = (string)$status;
        }

        // Amount and currency
        $amountStr = $this->formatAmountRow($order, $ai);
        if ($amountStr) {
            $data[(string)__('Amount')] = strip_tags($amountStr);
        }

        // Reference and dates
        if (!empty($ai['fintoc_reference_id'])) {
            $data[(string)__('Reference ID')] = (string)$ai['fintoc_reference_id'];
        }
        if (!empty($ai['fintoc_transaction_date'])) {
            $data[(string)__('Transaction Date')] = $this->formatDateTimeExact($ai['fintoc_transaction_date']);
        }
        if (!empty($ai['fintoc_transaction_completed_at'])) {
            $data[(string)__('Completed At')] = $this->formatDateTimeExact($ai['fintoc_transaction_completed_at']);
        }
        if (!empty($ai['fintoc_transaction_canceled_at'])) {
            $data[(string)__('Canceled At')] = $this->formatDateTimeExact($ai['fintoc_transaction_canceled_at']);
        }
        if (!empty($ai['fintoc_cancel_reason'])) {
            $data[(string)__('Cancel Reason')] = (string)$ai['fintoc_cancel_reason'];
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
                        $num = (string)$sender['number'];
                        $masked = (strlen($num) > 4) ? str_repeat('•', max(0, strlen($num) - 4)) . substr($num, -4) : $num;
                        $parts[] = (string)__('Account: %1', $masked);
                    }
                    if (!empty($sender['type'])) {
                        $parts[] = (string)$sender['type'];
                    }
                    if ($parts) {
                        $data[(string)__('Sender Account')] = implode(' | ', $parts);
                    }
                }
            } catch (Exception $e) {
                // Ignore malformed json
                unset($e);
            }
        }

        // Merge our data on top so it appears first
        $transport->setData(array_merge($data, $transport->getData()));

        return $transport;
    }

    /**
     * Format amount and currency row for payment info.
     *
     * @param Order|null $order
     * @param array $ai
     * @return string|null
     */
    private function formatAmountRow(?Order $order, array $ai): ?string
    {
        if ($order) {
            if (isset($ai['fintoc_amount'])) {
                $amount = (float)$ai['fintoc_amount'];
                return $order->formatPrice($amount);
            }
            if (isset($ai['fintoc_payment_amount'])) {
                $amount = (float)$ai['fintoc_payment_amount'];
                return $order->formatPrice($amount);
            }
        } else {
            // Fallback formatting without order context
            if (isset($ai['fintoc_amount'])) {
                $amount = (float)$ai['fintoc_amount'];
                return number_format($amount, 2) . (isset($ai['fintoc_currency']) ? ' ' . $ai['fintoc_currency'] : '');
            }
            if (isset($ai['fintoc_payment_amount'])) {
                $amount = ((float)$ai['fintoc_payment_amount']) / 100;
                return number_format($amount, 2) . (isset($ai['fintoc_payment_currency']) ? ' ' . $ai['fintoc_payment_currency'] : '');
            }
        }
        return null;
    }

}
