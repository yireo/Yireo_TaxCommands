<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation\RuleFactory;
use Magento\Tax\Model\ResourceModel\Calculation as TaxCalculationResourceModel;
use Magento\Tax\Model\ResourceModel\Calculation\Rule as RuleResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TaxCalculationAutoapplyCommand extends Command
{
    public function __construct(
        private TaxClassRepositoryInterface $taxClassRepository,
        private RuleFactory $ruleFactory,
        private RuleResource $ruleResource,
        private TaxRateRepositoryInterface $taxRateRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        ?string $name = null)  {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('tax:calculation:autoapply');
        $this->setDescription('Autoapply tax calculation');
        $this->addOption('rule_id', null, InputOption::VALUE_OPTIONAL, 'Rule ID', 1);
        $this->addOption('keyword', null, InputOption::VALUE_OPTIONAL, 'Rule match', 'standard');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productClassId = $this->getProductClassId();
        $customerClassId = $this->getCustomerClassId();

        $ruleId = (int)$input->getOption('rule_id');
        if (empty($ruleId)) {
            $ruleId = 1;
        }

        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, $ruleId);

        $rule->setCustomerTaxClassIds([$customerClassId]);
        $rule->setProductTaxClassIds([$productClassId]);

        $keyword = (string)$input->getOption('keyword');
        $newRateIds = $this->getRateIds($keyword);
        if (empty($newRateIds)) {
            throw new \Exception('No rules found for keyword: '.$keyword);
        }

        $rateIds = (array)$rule->getTaxRateIds();
        $rateIds = array_merge($rateIds,$newRateIds);
        $rateIds = array_unique($rateIds);
        $rule->setTaxRateIds($rateIds);

        $this->ruleResource->save($rule);

        return Command::SUCCESS;
    }

    private function getProductClassId(): int
    {
        $this->searchCriteriaBuilder->addFilter('class_type', 'PRODUCT');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->taxClassRepository->getList($searchCriteria);
        if ($searchResults->getTotalCount() === 0) {
            throw new \Exception('No tax class available');
        }

        $taxClasses = $searchResults->getItems();
        $taxClass = array_shift($taxClasses);
        return (int)$taxClass->getClassId();
    }

    private function getCustomerClassId(): int
    {
        $this->searchCriteriaBuilder->addFilter('class_type', 'CUSTOMER');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->taxClassRepository->getList($searchCriteria);
        if ($searchResults->getTotalCount() === 0) {
            throw new \Exception('No customer class available');
        }

        $taxClasses = $searchResults->getItems();
        $taxClass = array_shift($taxClasses);
        return (int)$taxClass->getClassId();
    }

    private function getRateIds(string $keyword): array
    {
        $this->searchCriteriaBuilder->addFilter('code', '%'.$keyword.'%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->taxRateRepository->getList($searchCriteria);
        $rates = $searchResults->getItems();

        $rateIds = [];
        foreach ($rates as $rate) {
            $rateIds[] = $rate->getId();
        }

        return $rateIds;
    }
}
