<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration\Console\Command;

use Magento\Tax\Api\TaxClassManagementInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\TaxCommands\Console\Command\TaxClassListCommand;
use Yireo\TaxCommands\Console\Command\TaxRateListCommand;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class TaxListCommandsTest extends TaxCommandTestCase
{
    private const LINK_TEXT = '<href=http://x>evil</>';

    public function testRateCodeIsDisplayedLiterally(): void
    {
        $prefix = $this->uniqueCode('listed-rate');
        $this->createRate($prefix . "\x1B" . self::LINK_TEXT);

        $this->assertSafeListing($this->tester(TaxRateListCommand::class), 'Code', $prefix);
    }

    public function testClassNameIsDisplayedLiterally(): void
    {
        $prefix = $this->uniqueCode('listed-class');
        $this->createTaxClass($prefix . "\x1B" . self::LINK_TEXT, TaxClassManagementInterface::TYPE_CUSTOMER);

        $this->assertSafeListing($this->tester(TaxClassListCommand::class), 'Type', $prefix);
    }

    private function assertSafeListing(CommandTester $tester, string $header, string $prefix): void
    {
        self::assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => false]));
        self::assertStringContainsString($header, $tester->getDisplay());
        self::assertStringContainsString($prefix . self::LINK_TEXT, $tester->getDisplay());
        self::assertStringNotContainsString("\x1B", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => false, 'decorated' => true]));
        self::assertStringContainsString($prefix . self::LINK_TEXT, $tester->getDisplay());
        self::assertStringNotContainsString("\x1B]", $tester->getDisplay());
    }
}
