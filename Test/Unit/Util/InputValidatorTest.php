<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Unit\Util;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yireo\TaxCommands\Util\InputValidator;

class InputValidatorTest extends TestCase
{
    /**
     * @dataProvider positiveIntegers
     * @param mixed $value
     * @param int $expected
     * @return void
     */
    #[DataProvider('positiveIntegers')]
    public function testPositiveIntAcceptsValidValues(mixed $value, int $expected): void
    {
        self::assertSame($expected, (new InputValidator())->positiveInt($value, 'id'));
    }

    /**
     * @dataProvider invalidPositiveIntegers
     * @param mixed $value
     * @return void
     */
    #[DataProvider('invalidPositiveIntegers')]
    public function testPositiveIntRejectsInvalidValues(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InputValidator())->positiveInt($value, 'id');
    }

    /**
     * @dataProvider nonNegativeIntegers
     * @param mixed $value
     * @param int $expected
     * @return void
     */
    #[DataProvider('nonNegativeIntegers')]
    public function testNonNegativeIntAcceptsValidValues(mixed $value, int $expected): void
    {
        self::assertSame($expected, (new InputValidator())->nonNegativeInt($value, 'id'));
    }

    /**
     * @dataProvider invalidNonNegativeIntegers
     * @param mixed $value
     * @return void
     */
    #[DataProvider('invalidNonNegativeIntegers')]
    public function testNonNegativeIntRejectsInvalidValues(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InputValidator())->nonNegativeInt($value, 'id');
    }

    /**
     * @dataProvider requiredStrings
     * @param mixed $value
     * @param string $expected
     * @return void
     */
    #[DataProvider('requiredStrings')]
    public function testRequiredStringAcceptsNonEmptyScalarValues(mixed $value, string $expected): void
    {
        self::assertSame($expected, (new InputValidator())->requiredString($value, 'code'));
    }

    /**
     * @dataProvider invalidRequiredStrings
     * @param mixed $value
     * @return void
     */
    #[DataProvider('invalidRequiredStrings')]
    public function testRequiredStringRejectsEmptyAndNonScalarValues(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InputValidator())->requiredString($value, 'code');
    }

    /**
     * @return array<string, array{int|string, int}>
     */
    public static function positiveIntegers(): array
    {
        return [
            'trimmed string' => [' 5 ', 5],
            'integer' => [5, 5],
            'leading zeroes' => ['005', 5],
            'largest integer string' => [(string)PHP_INT_MAX, PHP_INT_MAX],
        ];
    }

    /**
     * @return array<string, array{int|string, int}>
     */
    public static function nonNegativeIntegers(): array
    {
        return self::positiveIntegers() + [
            'zero string' => ['0', 0],
            'zero integer' => [0, 0],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPositiveIntegers(): array
    {
        return self::invalidNonNegativeIntegers() + [
            'zero string' => ['0'],
            'zero integer' => [0],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNonNegativeIntegers(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'blank string' => [' '],
            'alphanumeric' => ['1a'],
            'negative string' => ['-1'],
            'negative integer' => [-1],
            'decimal string' => ['1.5'],
            'float' => [1.5],
            'overflow' => ['99999999999999999999'],
        ];
    }

    /**
     * @return array<string, array{int|string, string}>
     */
    public static function requiredStrings(): array
    {
        return [
            'trimmed string' => [' 5 ', '5'],
            'integer' => [5, '5'],
            'zero string' => ['0', '0'],
            'zero integer' => [0, '0'],
            'alphanumeric' => ['1a', '1a'],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidRequiredStrings(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'blank string' => [' '],
            'array' => [['code']],
            'false' => [false],
        ];
    }
}
