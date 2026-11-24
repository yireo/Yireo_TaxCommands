<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yireo\TaxCommands\Logger\ChangeLogger;
use Yireo\TaxCommands\Util\SafeCell;

class RuleUpdater
{
    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly ChangeLogger $changeLogger,
        private readonly SafeCell $safeCell,
        private readonly QuestionHelper $questionHelper
    ) {
    }

    /**
     * @param string $commandName
     * @param TaxRuleInterface $rule
     * @param int[] $customerClassIds
     * @param int[] $productClassIds
     * @param int[] $rateIds
     * @param bool $dryRun
     * @param bool $force
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function update(
        string $commandName,
        TaxRuleInterface $rule,
        array $customerClassIds,
        array $productClassIds,
        array $rateIds,
        bool $dryRun,
        bool $force,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $ruleId = (int)$rule->getId();
        $before = $this->getSnapshot($rule);
        $after = [
            ChangeLogger::KEY_CUSTOMER_CLASS_IDS => $this->merge(
                $before[ChangeLogger::KEY_CUSTOMER_CLASS_IDS],
                $customerClassIds
            ),
            ChangeLogger::KEY_PRODUCT_CLASS_IDS => $this->merge(
                $before[ChangeLogger::KEY_PRODUCT_CLASS_IDS],
                $productClassIds
            ),
            ChangeLogger::KEY_RATE_IDS => $this->merge($before[ChangeLogger::KEY_RATE_IDS], $rateIds),
        ];

        $summary = $this->changeLogger->formatRuleChange($commandName, $ruleId, $before, $after);
        $output->writeln($this->safeCell->escape($summary));

        if ($before === $after) {
            $output->writeln('<info>' . $this->safeCell->escape(__('No changes needed.')) . '</info>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(
                '<comment>' . $this->safeCell->escape(__('Dry run: no changes were saved.')) . '</comment>'
            );
            return Command::SUCCESS;
        }

        if (!$force) {
            if (!$input->isInteractive()) {
                $output->writeln('<error>' . $this->safeCell->escape(
                    __('Refusing to save changes in non-interactive mode without --force.')
                ) . '</error>');
                return Command::FAILURE;
            }

            $question = new ConfirmationQuestion(
                (string)__('Save these changes to tax rule %1? [y/N] ', $ruleId),
                false
            );
            if (!$this->questionHelper->ask($input, $output, $question)) {
                $output->writeln('<comment>' . $this->safeCell->escape(__('Aborted.')) . '</comment>');
                return Command::FAILURE;
            }
        }

        $rule->setCustomerTaxClassIds($after[ChangeLogger::KEY_CUSTOMER_CLASS_IDS]);
        $rule->setProductTaxClassIds($after[ChangeLogger::KEY_PRODUCT_CLASS_IDS]);
        $rule->setTaxRateIds($after[ChangeLogger::KEY_RATE_IDS]);

        try {
            $this->taxRuleRepository->save($rule);
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . $this->safeCell->escape(
                __('Could not save tax rule %1: %2', $ruleId, $exception->getMessage())
            ) . '</error>');
            return Command::FAILURE;
        }

        $this->changeLogger->logRuleChange($commandName, $ruleId, $before, $after);
        $output->writeln('<info>' . $this->safeCell->escape(__('Tax rule %1 has been saved.', $ruleId)) . '</info>');

        return Command::SUCCESS;
    }

    /**
     * @param TaxRuleInterface $rule
     * @return array<string, int[]>
     */
    private function getSnapshot(TaxRuleInterface $rule): array
    {
        return [
            ChangeLogger::KEY_CUSTOMER_CLASS_IDS => $this->merge((array)$rule->getCustomerTaxClassIds(), []),
            ChangeLogger::KEY_PRODUCT_CLASS_IDS => $this->merge((array)$rule->getProductTaxClassIds(), []),
            ChangeLogger::KEY_RATE_IDS => $this->merge((array)$rule->getTaxRateIds(), []),
        ];
    }

    /**
     * @param array<int|string> $existing
     * @param array<int|string> $additional
     * @return int[]
     */
    private function merge(array $existing, array $additional): array
    {
        return array_values(array_unique(array_map('intval', array_merge($existing, $additional))));
    }
}
