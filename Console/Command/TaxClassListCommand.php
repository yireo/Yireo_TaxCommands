<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\TaxCommands\Util\SafeCell;

class TaxClassListCommand extends Command
{
    /**
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SafeCell $safeCell
     * @param string|null $name
     */
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly SafeCell $safeCell,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tax:class:list');
        $this->setDescription('List all tax classes');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()->create();
        $searchResults = $this->taxClassRepository->getList($searchCriteria);

        $table = new Table($output);
        $table->setHeaders(['ID', 'Class', 'Type']);

        foreach ($searchResults->getItems() as $taxClass) {
            $table->addRow(array_map([$this->safeCell, 'escape'], [
                $taxClass->getClassId(),
                $taxClass->getClassName(),
                $taxClass->getClassType(),
            ]));
        }

        $table->render();

        return Command::SUCCESS;
    }
}
