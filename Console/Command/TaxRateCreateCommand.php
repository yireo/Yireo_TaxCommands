<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TaxRateCreateCommand extends Command
{
    public function __construct(
        private TaxRateRepositoryInterface $taxRateRepository,
        private TaxRateInterfaceFactory $taxRateFactory,
        ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('tax:rates:create');
        $this->setDescription('Create a new tax rate');

        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code');
        $this->addOption('rate', null, InputOption::VALUE_REQUIRED, 'Rate');
        $this->addOption('country_id', null, InputOption::VALUE_REQUIRED, 'Country ID');
        $this->addOption('postcode', null, InputOption::VALUE_OPTIONAL, 'Postcode', '*');
        $this->addOption('region_id', null, InputOption::VALUE_OPTIONAL, 'Region ID');

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
        $code = $input->getOption('code');
        $rate = $input->getOption('rate');
        $countryId = $input->getOption('country_id');
        $postcode = $input->getOption('postcode');
        $regionId = $input->getOption('region_id');

        $taxRate = $this->taxRateFactory->create();
        $taxRate->setCode($code);
        $taxRate->setRate($rate);
        $taxRate->setTaxCountryId($countryId);
        $taxRate->setTaxPostcode($postcode);
        $taxRate->setTaxRegionId($regionId);

        $this->taxRateRepository->save($taxRate);

        return Command::SUCCESS;
    }
}
