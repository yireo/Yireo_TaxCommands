<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\TaxCommands\Util\SafeCell;

class TaxRateListCommand extends Command
{
    /**
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SafeCell $safeCell
     * @param string|null $name
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly SafeCell $safeCell,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tax:rates:list');
        $this->setDescription('List all existing tax rates');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()->create();
        $searchResults = $this->taxRateRepository->getList($searchCriteria);

        $table = new Table($output);
        $table->setHeaders([
            'ID',
            'Code',
            'Rate',
            'Region',
            'Country',
            'Postcode',
            'Region ID',
            'Zip From',
            'Zip Is Range',
            'Zip To',
        ]);

        foreach ($searchResults->getItems() as $taxRate) {
            $table->addRow(array_map([$this->safeCell, 'escape'], [
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
            ]));
        }

        $table->render();

        return Command::SUCCESS;
    }
}
