<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use InvalidArgumentException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxClassInterface;
use Magento\Tax\Api\Data\TaxRateInterface;
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
use Yireo\TaxCommands\Util\LikeEscaper;
use Yireo\TaxCommands\Util\SafeCell;

class TaxCalculationAutoapplyCommand extends Command
{
    /**
     * @param TaxRuleRepositoryInterface $taxRuleRepository
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param TaxClassResolver $taxClassResolver
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param RuleUpdater $ruleUpdater
     * @param InputValidator $inputValidator
     * @param LikeEscaper $likeEscaper
     * @param SafeCell $safeCell
     * @param string|null $name
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepository,
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        private readonly TaxClassResolver $taxClassResolver,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly RuleUpdater $ruleUpdater,
        private readonly InputValidator $inputValidator,
        private readonly LikeEscaper $likeEscaper,
        private readonly SafeCell $safeCell,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tax:calculation:autoapply');
        $this->setDescription('Add all tax rates matching a keyword to an existing tax rule');
        $this->addOption('rule_id', null, InputOption::VALUE_REQUIRED, 'Tax rule ID');
        $this->addOption('keyword', null, InputOption::VALUE_REQUIRED, 'Keyword to match tax rate codes with');
        $this->addOption('exact', null, InputOption::VALUE_NONE, 'Match the tax rate code exactly');
        $this->addOption(
            'customer_class_id',
            null,
            InputOption::VALUE_REQUIRED,
            'Customer tax class ID (defaults to the first customer tax class)'
        );
        $this->addOption(
            'product_class_id',
            null,
            InputOption::VALUE_REQUIRED,
            'Product tax class ID (defaults to the first product tax class)'
        );
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the changes without saving them');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Save the changes without confirmation');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $ruleId = $this->inputValidator->positiveInt($input->getOption('rule_id'), 'rule_id');
            $keyword = $this->inputValidator->requiredString($input->getOption('keyword'), 'keyword');
            $customerClass = $this->resolveTaxClass(
                $input->getOption('customer_class_id'),
                'customer_class_id',
                TaxClassManagementInterface::TYPE_CUSTOMER
            );
            $productClass = $this->resolveTaxClass(
                $input->getOption('product_class_id'),
                'product_class_id',
                TaxClassManagementInterface::TYPE_PRODUCT
            );
        } catch (InvalidArgumentException $exception) {
            $this->writeError($output, $exception->getMessage());
            return Command::INVALID;
        }

        try {
            $rule = $this->taxRuleRepository->get($ruleId);
        } catch (NoSuchEntityException) {
            $this->writeError($output, (string)__('Tax rule with ID %1 does not exist.', $ruleId));
            return Command::INVALID;
        }

        if ($customerClass === null || $productClass === null) {
            $this->writeError($output, (string)__('No suitable customer or product tax class available.'));
            return Command::FAILURE;
        }

        $this->writeTaxClass($output, (string)__('Customer tax class'), $customerClass);
        $this->writeTaxClass($output, (string)__('Product tax class'), $productClass);

        $rates = $this->getRates($keyword, (bool)$input->getOption('exact'));
        if (empty($rates)) {
            $this->writeError($output, (string)__('No tax rates found for keyword "%1".', $keyword));
            return Command::FAILURE;
        }

        $rateIds = [];
        $output->writeln($this->safeCell->escape(__('Matched tax rates:')));
        foreach ($rates as $rate) {
            $rateIds[] = (int)$rate->getId();
            $output->writeln(' - ' . $this->safeCell->escape($rate->getCode()));
        }

        return $this->ruleUpdater->update(
            (string)$this->getName(),
            $rule,
            [(int)$customerClass->getClassId()],
            [(int)$productClass->getClassId()],
            $rateIds,
            (bool)$input->getOption('dry-run'),
            (bool)$input->getOption('force'),
            $input,
            $output
        );
    }

    private function resolveTaxClass(mixed $value, string $optionName, string $classType): ?TaxClassInterface
    {
        if ($value === null) {
            return $this->taxClassResolver->getFirstByType($classType);
        }

        $classId = $this->inputValidator->positiveInt($value, $optionName);
        return $this->taxClassResolver->getByIdAndType($classId, $classType);
    }

    /**
     * @param string $keyword
     * @param bool $exact
     * @return TaxRateInterface[]
     */
    private function getRates(string $keyword, bool $exact): array
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        if ($exact) {
            $searchCriteriaBuilder->addFilter('code', $keyword, 'eq');
        } else {
            $searchCriteriaBuilder->addFilter('code', '%' . $this->likeEscaper->escape($keyword) . '%', 'like');
        }

        return $this->taxRateRepository->getList($searchCriteriaBuilder->create())->getItems();
    }

    private function writeTaxClass(OutputInterface $output, string $label, TaxClassInterface $taxClass): void
    {
        $output->writeln(sprintf(
            '%s: %d (%s)',
            $this->safeCell->escape($label),
            (int)$taxClass->getClassId(),
            $this->safeCell->escape($taxClass->getClassName())
        ));
    }

    private function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln('<error>' . $this->safeCell->escape($message) . '</error>');
    }
}
