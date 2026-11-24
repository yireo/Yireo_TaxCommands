<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Unit\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Yireo\TaxCommands\Util\SafeCell;

class SafeCellTest extends TestCase
{
    /**
     * @return void
     */
    public function testEscapePreservesLiteralLinkMarkupAfterFormatting(): void
    {
        $value = '<href=http://x>evil</>';
        $formatter = new OutputFormatter(false);

        self::assertSame($value, $formatter->format((new SafeCell())->escape($value)));
    }

    /**
     * @return void
     */
    public function testEscapeAlsoStripsControlCharacters(): void
    {
        self::assertSame('ab', (new SafeCell())->escape("a\tb\n"));
    }

    /**
     * @dataProvider cellValues
     * @param mixed $value
     * @param string $expected
     * @return void
     */
    #[DataProvider('cellValues')]
    public function testStripControlChars(mixed $value, string $expected): void
    {
        self::assertSame($expected, (new SafeCell())->stripControlChars($value));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function cellValues(): array
    {
        return [
            'escape byte' => ["\x1b[31mRed", '[31mRed'],
            'nul and del' => ["a\x00b\x7fc", 'abc'],
            'newline and tab' => ["a\nb\tc", 'abc'],
            'valid UTF-8' => ["Ümlaut\t €", 'Ümlaut €'],
            'invalid UTF-8' => ["\xC3(\x00x\n", "\xC3(x"],
            'null' => [null, ''],
            'true' => [true, '1'],
            'false' => [false, '0'],
            'integer' => [12, '12'],
            'array' => [['value'], ''],
            'non-stringable object' => [new stdClass(), ''],
        ];
    }

    /**
     * @return void
     */
    public function testStripControlCharsAcceptsStringable(): void
    {
        $value = $this->createStub(Stringable::class);
        $value->method('__toString')->willReturn("cell\nvalue");

        self::assertSame('cellvalue', (new SafeCell())->stripControlChars($value));
    }
}
