<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\TaxClassManagementInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\TaxCommands\Service\RuleUpdater;
use Yireo\TaxCommands\Service\TaxClassResolver;
use Yireo\TaxCommands\Util\InputValidator;
use Yireo\TaxCommands\Util\SafeCell;

class TaxCalculationCreateCommand extends Command
{
    /**
     * @param TaxRuleRepositoryInterface $taxRuleRepository
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param TaxClassResolver $taxClassResolver
     * @param RuleUpdater $ruleUpdater
     * @param InputValidator $inputValidator
     * @param SafeCell $safeCell
     * @param string|null $name
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        private readonly TaxClassResolver $taxClassResolver,
        private readonly RuleUpdater $ruleUpdater,
        private readonly InputValidator $inputValidator,
        private readonly SafeCell $safeCell,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tax:calculation:create');
        $this->setDescription('Add a tax rate and tax classes to an existing tax rule');
        $this->addOption('rate_id', null, InputOption::VALUE_REQUIRED, 'Tax rate ID');
        $this->addOption('rule_id', null, InputOption::VALUE_REQUIRED, 'Tax rule ID');
        $this->addOption('customer_class_id', null, InputOption::VALUE_REQUIRED, 'Customer tax class ID');
        $this->addOption('product_class_id', null, InputOption::VALUE_REQUIRED, 'Product tax class ID');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the changes without saving them');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Save the changes without confirmation');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rateId = $this->inputValidator->positiveInt($input->getOption('rate_id'), 'rate_id');
            $ruleId = $this->inputValidator->positiveInt($input->getOption('rule_id'), 'rule_id');
            $customerClassId = $this->inputValidator->positiveInt(
                $input->getOption('customer_class_id'),
                'customer_class_id'
            );
            $productClassId = $this->inputValidator->positiveInt(
                $input->getOption('product_class_id'),
                'product_class_id'
            );
            $this->taxClassResolver->getByIdAndType($customerClassId, TaxClassManagementInterface::TYPE_CUSTOMER);
            $this->taxClassResolver->getByIdAndType($productClassId, TaxClassManagementInterface::TYPE_PRODUCT);
        } catch (InvalidArgumentException $exception) {
            $this->writeError($output, $exception->getMessage());
            return Command::INVALID;
        }

        try {
            $this->taxRateRepository->get($rateId);
        } catch (NoSuchEntityException) {
            $this->writeError($output, (string)__('Tax rate with ID %1 does not exist.', $rateId));
            return Command::INVALID;
        }

        try {
            $rule = $this->taxRuleRepository->get($ruleId);
        } catch (NoSuchEntityException) {
            $this->writeError($output, (string)__('Tax rule with ID %1 does not exist.', $ruleId));
            return Command::INVALID;
        }

        return $this->ruleUpdater->update(
            (string)$this->getName(),
            $rule,
            [$customerClassId],
            [$productClassId],
            [$rateId],
            (bool)$input->getOption('dry-run'),
            (bool)$input->getOption('force'),
            $input,
            $output
        );
    }

    private function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln('<error>' . $this->safeCell->escape($message) . '</error>');
    }
}
