<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Logger;

use Psr\Log\LoggerInterface;
use Yireo\TaxCommands\Util\SafeCell;

class ChangeLogger
{
    public const KEY_CUSTOMER_CLASS_IDS = 'customer_class_ids';
    public const KEY_PRODUCT_CLASS_IDS = 'product_class_ids';
    public const KEY_RATE_IDS = 'rate_ids';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SafeCell $safeCell
    ) {
    }

    /**
     * @param string $command
     * @param int $ruleId
     * @param array<string, int[]> $before
     * @param array<string, int[]> $after
     * @return void
     */
    public function logRuleChange(string $command, int $ruleId, array $before, array $after): void
    {
        $this->logger->info($this->formatRuleChange($command, $ruleId, $before, $after));
    }

    /**
     * @param string $command
     * @param int $ruleId
     * @param array<string, int[]> $before
     * @param array<string, int[]> $after
     * @return string
     */
    public function formatRuleChange(string $command, int $ruleId, array $before, array $after): string
    {
        $parts = [];
        $labels = [
            self::KEY_CUSTOMER_CLASS_IDS => 'customer class IDs',
            self::KEY_PRODUCT_CLASS_IDS => 'product class IDs',
            self::KEY_RATE_IDS => 'rate IDs',
        ];

        foreach ($labels as $key => $label) {
            $parts[] = sprintf(
                '%s [%s] -> [%s]',
                $label,
                $this->formatIds($before[$key] ?? []),
                $this->formatIds($after[$key] ?? [])
            );
        }

        return sprintf(
            '[%s] Tax rule %d: %s',
            $this->safeCell->stripControlChars($command),
            $ruleId,
            implode('; ', $parts)
        );
    }

    public function logRateCreated(
        string $command,
        int $rateId,
        string $code,
        float $rate,
        string $countryId,
        string $postcode,
        int $regionId
    ): void {
        $this->logger->info(
            $this->formatRateCreated($command, $rateId, $code, $rate, $countryId, $postcode, $regionId)
        );
    }

    public function formatRateCreated(
        string $command,
        int $rateId,
        string $code,
        float $rate,
        string $countryId,
        string $postcode,
        int $regionId
    ): string {
        return sprintf(
            '[%s] Tax rate %d created: code "%s", rate %s, country %s, postcode "%s", region ID %d',
            $this->safeCell->stripControlChars($command),
            $rateId,
            $this->safeCell->stripControlChars($code),
            (string)$rate,
            $this->safeCell->stripControlChars($countryId),
            $this->safeCell->stripControlChars($postcode),
            $regionId
        );
    }

    /**
     * @param array<int|string> $ids
     * @return string
     */
    private function formatIds(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }
}
