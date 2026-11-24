<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration\Console\Command;

use Magento\Tax\Api\TaxClassManagementInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Yireo\TaxCommands\Console\Command\TaxCalculationAutoapplyCommand;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[DbIsolation(true)]
#[AppIsolation(true)]
class TaxCalculationAutoapplyCommandTest extends TaxCommandTestCase
{
    private int $customerId;
    private int $productId;
    private int $originalRateId;
    private int $ruleId;

    public function testRequiresRuleId(): void
    {
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);

        self::assertSame(Command::INVALID, $tester->execute(['--keyword' => 'sample'], ['interactive' => false]));
        self::assertStringContainsString('--rule_id', $tester->getDisplay());
    }

    public function testRejectsNonexistentRule(): void
    {
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $this->createBaselineRule();
        $input = ['--rule_id' => '999999'] + $this->input('sample');

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('Tax rule with ID 999999 does not exist.', $tester->getDisplay());
    }

    #[DataProvider('blankKeywords')]
    public function testRejectsBlankKeyword(string $keyword): void
    {
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $this->createBaselineRule();

        self::assertSame(Command::INVALID, $tester->execute($this->input($keyword), ['interactive' => false]));
        self::assertStringContainsString('--keyword', $tester->getDisplay());
        $this->assertBaselineRule();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankKeywords(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [" \t\n "],
        ];
    }

    public function testPercentKeywordDoesNotMatchEveryRate(): void
    {
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $this->createBaselineRule();
        $this->createRate($this->uniqueCode('not-a-percent'));
        $input = $this->input('%') + ['--force' => true];

        self::assertSame(Command::FAILURE, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('No tax rates found', $tester->getDisplay());
        $this->assertBaselineRule();
    }

    public function testForcePreservesExistingCustomerAndProductClasses(): void
    {
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $this->createBaselineRule();
        $secondCustomerId = $this->createTaxClass(
            $this->uniqueCode('customer-b'),
            TaxClassManagementInterface::TYPE_CUSTOMER
        );
        $newCustomerId = $this->createTaxClass(
            $this->uniqueCode('customer-c'),
            TaxClassManagementInterface::TYPE_CUSTOMER
        );
        $secondProductId = $this->createTaxClass(
            $this->uniqueCode('product-b'),
            TaxClassManagementInterface::TYPE_PRODUCT
        );
        $this->ruleId = $this->createRule(
            [$this->customerId, $secondCustomerId],
            [$this->productId, $secondProductId],
            [$this->originalRateId]
        );
        $code = $this->uniqueCode('merge');
        $rateId = $this->createRate($code);
        $input = ['--customer_class_id' => (string)$newCustomerId, '--force' => true] + $this->input($code);

        self::assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        $this->assertPersistedRule(
            $this->ruleId,
            [$this->customerId, $secondCustomerId, $newCustomerId],
            [$this->productId, $secondProductId],
            [$this->originalRateId, $rateId]
        );
    }

    #[DataProvider('saveDecisions')]
    public function testSaveDecision(
        bool $interactive,
        ?string $answer,
        bool $dryRun,
        bool $force,
        int $status,
        string $message,
        bool $saved
    ): void {
        $this->createBaselineRule();
        $code = $this->uniqueCode('new-rate');
        $rateId = $this->createRate($code);
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        if ($answer !== null) {
            $tester->setInputs([$answer]);
        }

        $input = $this->input($code);
        if ($dryRun) {
            $input['--dry-run'] = true;
        }
        if ($force) {
            $input['--force'] = true;
        }
        self::assertSame($status, $tester->execute($input, ['interactive' => $interactive]));
        $display = $tester->getDisplay();
        self::assertStringContainsString(
            "rate IDs [{$this->originalRateId}] -> [{$this->originalRateId},{$rateId}]",
            $display
        );
        self::assertStringContainsString($message, $display);
        if ($interactive) {
            self::assertStringContainsString('Save these changes', $display);
        }
        $this->assertPersistedRule(
            $this->ruleId,
            [$this->customerId],
            [$this->productId],
            $saved ? [$this->originalRateId, $rateId] : [$this->originalRateId]
        );
    }

    /**
     * @return array<string, array{bool, ?string, bool, bool, int, string, bool}>
     */
    public static function saveDecisions(): array
    {
        return [
            'dry run' => [false, null, true, true, Command::SUCCESS, 'Dry run: no changes were saved.', false],
            'non-interactive' => [false, null, false, false, Command::FAILURE, 'without --force', false],
            'interactive yes' => [true, 'y', false, false, Command::SUCCESS, 'has been saved.', true],
            'interactive no' => [true, 'no', false, false, Command::FAILURE, 'Aborted.', false],
            'interactive default' => [true, '', false, false, Command::FAILURE, 'Aborted.', false],
        ];
    }

    public function testNoChangesSucceedsWithoutForce(): void
    {
        $code = $this->uniqueCode('already-assigned');
        $this->createBaselineRule($code);
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);

        self::assertSame(Command::SUCCESS, $tester->execute($this->input($code), ['interactive' => false]));
        self::assertStringContainsString('No changes needed.', $tester->getDisplay());
        $this->assertBaselineRule();
    }

    public function testRejectsProductClassAsCustomerClass(): void
    {
        $this->createBaselineRule();
        $code = $this->uniqueCode('wrong-class');
        $this->createRate($code);
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $input = ['--customer_class_id' => (string)$this->productId, '--force' => true] + $this->input($code);

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString('is not of type CUSTOMER', $tester->getDisplay());
        $this->assertBaselineRule();
    }

    public function testExactMatchesOnlyIdenticalRateCode(): void
    {
        $this->createBaselineRule();
        $keyword = $this->uniqueCode('exact');
        $rateId = $this->createRate($keyword);
        $otherCode = $keyword . '-suffix';
        $this->createRate($otherCode);
        $tester = $this->tester(TaxCalculationAutoapplyCommand::class);
        $input = $this->input($keyword) + ['--exact' => true, '--force' => true];

        self::assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString(' - ' . $keyword, $tester->getDisplay());
        self::assertStringNotContainsString($otherCode, $tester->getDisplay());
        $this->assertPersistedRule(
            $this->ruleId,
            [$this->customerId],
            [$this->productId],
            [$this->originalRateId, $rateId]
        );
    }

    private function createBaselineRule(?string $rateCode = null): void
    {
        $this->customerId = $this->createTaxClass(
            $this->uniqueCode('customer'),
            TaxClassManagementInterface::TYPE_CUSTOMER
        );
        $this->productId = $this->createTaxClass($this->uniqueCode('product'), TaxClassManagementInterface::TYPE_PRODUCT);
        $this->originalRateId = $this->createRate($rateCode ?? $this->uniqueCode('initial-rate'));
        $this->ruleId = $this->createRule([$this->customerId], [$this->productId], [$this->originalRateId]);
    }

    /**
     * @return array<string, string>
     */
    private function input(string $keyword): array
    {
        return [
            '--rule_id' => (string)$this->ruleId,
            '--keyword' => $keyword,
            '--customer_class_id' => (string)$this->customerId,
            '--product_class_id' => (string)$this->productId,
        ];
    }

    private function assertBaselineRule(): void
    {
        $this->assertPersistedRule($this->ruleId, [$this->customerId], [$this->productId], [$this->originalRateId]);
    }
}
