<?php
declare(strict_types=1);

namespace Fintoc\Payment\Test\Unit\Block\Info;

use Fintoc\Payment\Block\Info\Fintoc;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FintocTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManagerHelper;

    /** @var JsonSerializer */
    private $json;

    public function testWithOrder_AmountFromFintocAmountAndIds(): void
    {
        /** @var Order|MockObject $order */
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods(['formatPrice'])->getMock();
        $order->expects($this->once())->method('formatPrice')->with(123.45)->willReturn('$123.45');

        $ai = [
            'fintoc_transaction_id' => 'tx123',
            'fintoc_payment_id' => 'pay456',
            'fintoc_transaction_status' => 'COMPLETED',
            'fintoc_amount' => 123.45,
            'fintoc_reference_id' => 'ref-1',
            'fintoc_transaction_date' => '2025-01-01T00:00:00Z',
            'fintoc_transaction_completed_at' => '2025-01-01T01:00:00Z',
            'fintoc_transaction_canceled_at' => '',
            'fintoc_cancel_reason' => '',
            'fintoc_sender_account' => json_encode([
                'institutionId' => 'BANK-001',
                'holderId' => '12345678-9',
                'number' => '1234567890',
                'type' => 'checking'
            ])
        ];

        $info = $this->createInfo($ai, $order);
        $block = $this->createBlock($info);

        $data = $block->getSpecificInformation();

        $this->assertSame('tx123', $data['Transaction ID'] ?? null);
        $this->assertSame('pay456', $data['Payment ID'] ?? null);
        $this->assertSame('COMPLETED', $data['Status'] ?? null);
        $this->assertSame('$123.45', $data['Amount'] ?? null);
        $this->assertSame('ref-1', $data['Reference ID'] ?? null);
        $this->assertSame('2025-01-01T00:00:00Z', $data['Transaction Date'] ?? null);
        $this->assertSame('2025-01-01T01:00:00Z', $data['Completed At'] ?? null);
        $this->assertArrayNotHasKey('Canceled At', $data);
        $this->assertArrayNotHasKey('Cancel Reason', $data);

        $sender = $data['Sender Account'] ?? '';
        $this->assertStringContainsString('BANK-001', $sender);
        $this->assertStringContainsString('Holder ID: 12345678-9', $sender);
        // Masked account should retain only last 4 digits
        $this->assertStringContainsString('Account: ' . str_repeat('•', 6) . '7890', $sender);
        $this->assertStringContainsString('checking', $sender);
    }

    /**
     * Helper to build an Info mock.
     * @param array $ai
     * @param Order|null $order
     * @param string|null $lastTransId
     * @return InfoInterface|MockObject
     */
    private function createInfo(array $ai, ?Order $order = null, ?string $lastTransId = null): InfoInterface
    {
        /** @var InfoInterface|MockObject $info */
        $info = $this->getMockBuilder(InfoInterface::class)->getMock();
        $info->method('getAdditionalInformation')
            ->willReturnCallback(function ($key = null) use ($ai) {
                if ($key === null) {
                    return $ai;
                }
                return $ai[$key] ?? null;
            });
        $info->method('getOrder')->willReturn($order);
        $info->method('getLastTransId')->willReturn($lastTransId);
        return $info;
    }

    private function createBlock(InfoInterface $info): Fintoc
    {
        /** @var Context|MockObject $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var Fintoc $block */
        $block = $this->objectManagerHelper->getObject(
            Fintoc::class,
            [
                'context' => $context,
                'json' => $this->json,
                'data' => []
            ]
        );
        $block->setData('info', $info);
        return $block;
    }

    public function testWithOrder_AmountFromPaymentCents(): void
    {
        /** @var Order|MockObject $order */
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods(['formatPrice'])->getMock();
        $order->expects($this->once())->method('formatPrice')->with(19.99)->willReturn('CLP $19.99');

        $ai = [
            'fintoc_payment_amount' => 1999,
            'fintoc_payment_currency' => 'CLP'
        ];

        $info = $this->createInfo($ai, $order);
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();

        $this->assertSame('CLP $19.99', $data['Amount'] ?? null);
    }

    public function testWithoutOrder_FallbackFormatting(): void
    {
        $ai = [
            'fintoc_amount' => 123.4,
            'fintoc_currency' => 'CLP'
        ];

        $info = $this->createInfo($ai, null);
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();

        $this->assertSame('123.40 CLP', $data['Amount'] ?? null);
    }

    public function testMalformedSenderJson_IsIgnored(): void
    {
        $ai = [
            'fintoc_sender_account' => '{invalid json]'
        ];
        $info = $this->createInfo($ai, null);
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();

        $this->assertArrayNotHasKey('Sender Account', $data);
    }

    public function testDuplicateIds_AvoidsPaymentIdWhenSame(): void
    {
        $ai = [
            'fintoc_transaction_id' => 'same-id',
            'fintoc_payment_id' => 'same-id'
        ];
        $info = $this->createInfo($ai, null);
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();

        $this->assertSame('same-id', $data['Transaction ID'] ?? null);
        $this->assertArrayNotHasKey('Payment ID', $data);
    }

    public function testStatusPrecedence(): void
    {
        // Case 1: only payment status
        $ai1 = [
            'fintoc_payment_status' => 'PENDING'
        ];
        $data1 = $this->createBlock($this->createInfo($ai1))->getSpecificInformation();
        $this->assertSame('PENDING', $data1['Status'] ?? null);

        // Case 2: transaction status overrides payment status
        $ai2 = [
            'fintoc_payment_status' => 'PENDING',
            'fintoc_transaction_status' => 'SUCCEEDED'
        ];
        $data2 = $this->createBlock($this->createInfo($ai2))->getSpecificInformation();
        $this->assertSame('SUCCEEDED', $data2['Status'] ?? null);
    }

    public function testTransactionIdFallsBackToLastTransId(): void
    {
        $ai = [];
        /** @var InfoInterface|MockObject $info */
        $info = $this->createInfo($ai, null, 'last-123');
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();
        $this->assertSame('last-123', $data['Transaction ID'] ?? null);
    }

    public function testMergeOrder_PutsCustomDataBeforeParent(): void
    {
        $ai = [
            'fintoc_transaction_id' => 'tx-1'
        ];
        $info = $this->createInfo($ai, null);
        $block = $this->createBlock($info);

        // Use reflection to call protected method with pre-filled transport
        $ref = new ReflectionClass(Fintoc::class);
        $method = $ref->getMethod('_prepareSpecificInformation');
        $method->setAccessible(true);
        $transport = new DataObject(['Parent Key' => 'Parent Val']);
        /** @var DataObject $result */
        $result = $method->invoke($block, $transport);
        $data = $result->getData();

        $keys = array_keys($data);
        $this->assertSame('Transaction ID', $keys[0] ?? null, 'Custom data should be first');
        $this->assertContains('Parent Key', $keys, 'Parent data should still be present');
    }

    public function testWithoutOrder_FallbackFormatting_FromPaymentCents(): void
    {
        $ai = [
            'fintoc_payment_amount' => 2500,
            'fintoc_payment_currency' => 'USD'
        ];
        $info = $this->createInfo($ai, null);
        $block = $this->createBlock($info);
        $data = $block->getSpecificInformation();
        $this->assertSame('25.00 USD', $data['Amount'] ?? null);
    }

    protected function setUp(): void
    {
        $this->objectManagerHelper = new ObjectManager($this);
        $this->json = new JsonSerializer();
    }
}
