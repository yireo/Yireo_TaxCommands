<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Util;

use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatter;

class SafeCell
{
    public function escape(mixed $value): string
    {
        return OutputFormatter::escape($this->stripControlChars($value));
    }

    public function stripControlChars(mixed $value): string
    {
        $string = $this->toString($value);
        $stripped = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);
        if ($stripped === null) {
            $stripped = (string)preg_replace('/[\x00-\x1F\x7F]/', '', $string);
        }

        return $stripped;
    }

    private function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string)$value;
        }

        return '';
    }
}
