<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Service;

use InvalidArgumentException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxClassInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class TaxClassResolver
{
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
    ) {
    }

    public function getByIdAndType(int $classId, string $classType): TaxClassInterface
    {
        try {
            $taxClass = $this->taxClassRepository->get($classId);
        } catch (NoSuchEntityException) {
            throw new InvalidArgumentException((string)__('Tax class with ID %1 does not exist.', $classId));
        }

        if ((string)$taxClass->getClassType() !== $classType) {
            throw new InvalidArgumentException(
                (string)__('Tax class with ID %1 is not of type %2.', $classId, $classType)
            );
        }

        return $taxClass;
    }

    public function getFirstByType(string $classType): ?TaxClassInterface
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter('class_type', $classType);
        $searchCriteriaBuilder->setPageSize(1);
        $searchCriteriaBuilder->setCurrentPage(1);

        $taxClasses = $this->taxClassRepository->getList($searchCriteriaBuilder->create())->getItems();
        foreach ($taxClasses as $taxClass) {
            return $taxClass;
        }

        return null;
    }
}
