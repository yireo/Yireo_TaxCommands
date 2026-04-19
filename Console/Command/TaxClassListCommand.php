<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaxClassListCommand extends Command
{
    public function __construct(
        private TaxClassRepositoryInterface $taxClassRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        ?string $name = null)  {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('tax:class:list');
        $this->setDescription('List all tax classes');
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
        $table = new Table($output);
        $table->setHeaders(['ID', 'Class', 'Rate']);

        $searchResults = $this->taxClassRepository->getList($this->searchCriteriaBuilder->create());
        $taxClasses = $searchResults->getItems();

        foreach ($taxClasses as $taxClass) {
            $table->addRow([
                $taxClass->getClassId(),
                $taxClass->getClassName(),
                $taxClass->getClassType(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
