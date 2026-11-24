<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Unit\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yireo\TaxCommands\Util\LikeEscaper;

class LikeEscaperTest extends TestCase
{
    /**
     * @dataProvider patterns
     * @param string $pattern
     * @param string $expected
     * @return void
     */
    #[DataProvider('patterns')]
    public function testEscape(string $pattern, string $expected): void
    {
        self::assertSame($expected, (new LikeEscaper())->escape($pattern));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function patterns(): array
    {
        return [
            'plain string' => ['Tax rate', 'Tax rate'],
            'percent' => ['20%', "20\\%"],
            'underscore' => ['tax_rate', "tax\\_rate"],
            'backslash' => ["tax\\rate", "tax\\\\rate"],
            'backslash before percent' => ["\\%", "\\\\\\%"],
        ];
    }
}
