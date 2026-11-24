<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Util;

class LikeEscaper
{
    public function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
