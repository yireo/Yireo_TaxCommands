<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Util;

use InvalidArgumentException;

class InputValidator
{
    public function positiveInt(mixed $value, string $optionName): int
    {
        $message = (string)__('Option "--%1" must be a positive integer.', $optionName);
        $int = $this->toInt($value, $message);
        if ($int <= 0) {
            throw new InvalidArgumentException($message);
        }

        return $int;
    }

    public function nonNegativeInt(mixed $value, string $optionName): int
    {
        return $this->toInt($value, (string)__('Option "--%1" must be a non-negative integer.', $optionName));
    }

    public function requiredString(mixed $value, string $optionName): string
    {
        $string = is_scalar($value) ? trim((string)$value, " \f\n\r\t\v\x00") : '';
        if ($string === '') {
            throw new InvalidArgumentException((string)__('Option "--%1" is required.', $optionName));
        }

        return $string;
    }

    private function toInt(mixed $value, string $message): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException($message);
            }

            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException($message);
        }

        $value = trim($value, " \f\n\r\t\v\x00");
        if (!preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException($message);
        }

        $normalized = ltrim($value, '0');
        $int = filter_var($normalized === '' ? '0' : $normalized, FILTER_VALIDATE_INT);
        if ($int === false) {
            throw new InvalidArgumentException($message);
        }

        return $int;
    }
}
