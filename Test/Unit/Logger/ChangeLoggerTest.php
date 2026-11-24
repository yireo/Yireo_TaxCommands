<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yireo\TaxCommands\Logger\ChangeLogger;
use Yireo\TaxCommands\Util\SafeCell;

class ChangeLoggerTest extends TestCase
{
    /**
     * @return void
     */
    public function testFormatRuleChangeFormatsIdListsAndDefaultsMissingListsToEmpty(): void
    {
        $changeLogger = new ChangeLogger(new NullLogger(), new SafeCell());
        $before = [
            ChangeLogger::KEY_CUSTOMER_CLASS_IDS => [1, 2],
            ChangeLogger::KEY_PRODUCT_CLASS_IDS => [3],
        ];
        $after = [
            ChangeLogger::KEY_CUSTOMER_CLASS_IDS => [2],
            ChangeLogger::KEY_RATE_IDS => [4, 5],
        ];

        self::assertSame(
            '[tax:rule:update] Tax rule 7: customer class IDs [1,2] -> [2]; '
            . 'product class IDs [3] -> []; rate IDs [] -> [4,5]',
            $changeLogger->formatRuleChange("tax:rule:update\n", 7, $before, $after)
        );
    }

    /**
     * @return void
     */
    public function testFormatRateCreatedStripsControlsFromTextFields(): void
    {
        $changeLogger = new ChangeLogger(new NullLogger(), new SafeCell());

        self::assertSame(
            '[tax:rate:create] Tax rate 8 created: code "VAT", rate 7.5, country NL, postcode "1234", region ID 2',
            $changeLogger->formatRateCreated("tax:rate:create\n", 8, "VA\tT", 7.5, "N\x00L", "12\r34", 2)
        );
    }

    /**
     * @return void
     */
    public function testLogRuleChangeLogsTheFormattedLineOnce(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            '[tax:rule:update] Tax rule 7: customer class IDs [] -> []; '
            . 'product class IDs [] -> []; rate IDs [4] -> [5]'
        );

        (new ChangeLogger($logger, new SafeCell()))->logRuleChange(
            'tax:rule:update',
            7,
            [ChangeLogger::KEY_RATE_IDS => [4]],
            [ChangeLogger::KEY_RATE_IDS => [5]]
        );
    }

    /**
     * @return void
     */
    public function testLogRateCreatedLogsTheFormattedLineOnce(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            '[tax:rate:create] Tax rate 8 created: code "VAT", rate 7.5, country NL, postcode "*", region ID 0'
        );

        (new ChangeLogger($logger, new SafeCell()))->logRateCreated('tax:rate:create', 8, 'VAT', 7.5, 'NL', '*', 0);
    }
}
