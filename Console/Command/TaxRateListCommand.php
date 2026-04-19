<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaxRateListCommand extends Command
{
    public function __construct(
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
        $this->setName('tax:rates:list');
        $this->setDescription('List all existing tax rates');
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
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->taxRateRepository->getList($searchCriteria);

        $table = new Table($output);
        foreach ($searchResults->getItems() as $taxRate) {
            $table->addRow([
                $taxRate->getId(),
                $taxRate->getCode(),
                $taxRate->getRate(),
                $taxRate->getRegionName(),
                $taxRate->getTaxCountryId(),
                $taxRate->getTaxPostcode(),
                $taxRate->getTaxRegionId(),
                $taxRate->getZipFrom(),
                $taxRate->getZipIsRange(),
                $taxRate->getZipTo(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
