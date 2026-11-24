<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration\Console\Command;

use Magento\Tax\Api\TaxClassManagementInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Yireo\TaxCommands\Console\Command\TaxCalculationCreateCommand;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class TaxCalculationCreateCommandTest extends TaxCommandTestCase
{
    private int $customerId;
    private int $productId;
    private int $rateId;
    private int $ruleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerId = $this->createTaxClass(
            $this->uniqueCode('customer'),
            TaxClassManagementInterface::TYPE_CUSTOMER
        );
        $this->productId = $this->createTaxClass(
            $this->uniqueCode('product'),
            TaxClassManagementInterface::TYPE_PRODUCT
        );
        $this->rateId = $this->createRate($this->uniqueCode('original'));
        $this->ruleId = $this->createRule([$this->customerId], [$this->productId], [$this->rateId]);
    }

    /**
     * @dataProvider invalidIds
     */
    #[DataProvider('invalidIds')]
    public function testRejectsInvalidId(string $option, string $value): void
    {
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = [$option => $value] + $this->input();

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString($option . '" must be a positive integer', $tester->getDisplay());
        $this->assertOriginalRule();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidIds(): array
    {
        return [
            'zero rate' => ['--rate_id', '0'],
            'non-digit customer class' => ['--customer_class_id', 'abc'],
            'negative rule' => ['--rule_id', '-1'],
        ];
    }

    public function testRequiresProductClassId(): void
    {
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = $this->input();
        unset($input['--product_class_id']);

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('--product_class_id', $tester->getDisplay());
        $this->assertOriginalRule();
    }

    public function testRejectsCustomerClassAsProductClass(): void
    {
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = ['--product_class_id' => (string)$this->customerId] + $this->input();

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('is not of type PRODUCT', $tester->getDisplay());
        $this->assertOriginalRule();
    }

    public function testRejectsNonexistentClass(): void
    {
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = ['--customer_class_id' => '999999'] + $this->input();

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('Tax class with ID 999999 does not exist.', $tester->getDisplay());
        $this->assertOriginalRule();
    }

    public function testRejectsNonexistentRate(): void
    {
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = ['--rate_id' => '999999'] + $this->input();

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('Tax rate with ID 999999 does not exist.', $tester->getDisplay());
        $this->assertOriginalRule();
    }

    public function testForceMergesRateAndClassesIntoExistingRule(): void
    {
        $newCustomerId = $this->createTaxClass(
            $this->uniqueCode('added-customer'),
            TaxClassManagementInterface::TYPE_CUSTOMER
        );
        $newProductId = $this->createTaxClass(
            $this->uniqueCode('added-product'),
            TaxClassManagementInterface::TYPE_PRODUCT
        );
        $newRateId = $this->createRate($this->uniqueCode('added-rate'));
        $tester = $this->tester(TaxCalculationCreateCommand::class);
        $input = [
            '--rate_id' => (string)$newRateId,
            '--customer_class_id' => (string)$newCustomerId,
            '--product_class_id' => (string)$newProductId,
        ] + $this->input();

        self::assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString("Tax rule {$this->ruleId} has been saved.", $tester->getDisplay());
        $this->assertPersistedRule(
            $this->ruleId,
            [$this->customerId, $newCustomerId],
            [$this->productId, $newProductId],
            [$this->rateId, $newRateId]
        );
    }

    /**
     * @return array<string, string|true>
     */
    private function input(): array
    {
        return [
            '--rule_id' => (string)$this->ruleId,
            '--rate_id' => (string)$this->rateId,
            '--customer_class_id' => (string)$this->customerId,
            '--product_class_id' => (string)$this->productId,
            '--force' => true,
        ];
    }

    private function assertOriginalRule(): void
    {
        $this->assertPersistedRule($this->ruleId, [$this->customerId], [$this->productId], [$this->rateId]);
    }
}
