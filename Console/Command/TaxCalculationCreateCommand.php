<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Tax\Model\ResourceModel\Calculation as TaxCalculationResourceModel;
use Magento\Tax\Model\CalculationFactory as TaxCalculationFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Tax\Model\Calculation\RuleFactory;
use Magento\Tax\Model\ResourceModel\Calculation\Rule as RuleResource;

class TaxCalculationCreateCommand extends Command
{
    public function __construct(
        private RuleFactory $ruleFactory,
        private RuleResource $ruleResource,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('tax:calculation:create');
        $this->setDescription('Create a new tax calculation');
        $this->addOption('rate_id', null, InputOption::VALUE_REQUIRED, 'Rate id');
        $this->addOption('rule_id', null, InputOption::VALUE_REQUIRED, 'Rule id');
        $this->addOption('customer_class_id', null, InputOption::VALUE_REQUIRED, 'Customer class id');
        $this->addOption('product_class_id', null, InputOption::VALUE_REQUIRED, 'Product class id');
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
        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, (int)$input->getOption('rule_id'));

        $rule->setCustomerTaxClassIds([(int)$input->getOption('customer_class_id')]);
        $rule->setProductTaxClassIds([(int)$input->getOption('product_class_id')]);

        $rateIds = (array)$rule->getTaxRateIds();
        $rateIds[] = (int)$input->getOption('rate_id');
        $rateIds = array_unique($rateIds);
        $rule->setTaxRateIds($rateIds);

        $this->ruleResource->save($rule);

        return Command::SUCCESS;
    }
}
