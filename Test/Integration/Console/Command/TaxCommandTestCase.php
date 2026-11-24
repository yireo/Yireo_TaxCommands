<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration\Console\Command;

use Magento\Tax\Api\Data\TaxClassInterfaceFactory;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Magento\Tax\Model\Calculation\TaxRuleRegistry;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

abstract class TaxCommandTestCase extends TestCase
{
    protected function uniqueCode(string $label): string
    {
        return 'TaxCommands-' . $label . '-' . bin2hex(random_bytes(6));
    }

    /**
     * @param class-string<Command> $commandClass
     */
    protected function tester(string $commandClass): CommandTester
    {
        return new CommandTester(Bootstrap::getObjectManager()->create($commandClass));
    }

    protected function createTaxClass(string $name, string $type): int
    {
        $objectManager = Bootstrap::getObjectManager();
        $taxClass = $objectManager->get(TaxClassInterfaceFactory::class)->create();
        $taxClass->setClassName($name);
        $taxClass->setClassType($type);

        return (int)$objectManager->get(TaxClassRepositoryInterface::class)->save($taxClass);
    }

    protected function createRate(string $code): int
    {
        $objectManager = Bootstrap::getObjectManager();
        $rate = $objectManager->get(TaxRateInterfaceFactory::class)->create();
        $rate->setCode($code);
        $rate->setRate(9.5);
        $rate->setTaxCountryId('NL');
        $rate->setTaxRegionId(0);
        $rate->setTaxPostcode('*');

        return (int)$objectManager->get(TaxRateRepositoryInterface::class)->save($rate)->getId();
    }

    /**
     * @param list<int> $customerIds
     * @param list<int> $productIds
     * @param list<int> $rateIds
     */
    protected function createRule(array $customerIds, array $productIds, array $rateIds): int
    {
        $objectManager = Bootstrap::getObjectManager();
        $rule = $objectManager->get(TaxRuleInterfaceFactory::class)->create();
        $rule->setCode($this->uniqueCode('rule'));
        $rule->setPriority(0);
        $rule->setPosition(0);
        $rule->setCalculateSubtotal(false);
        $rule->setCustomerTaxClassIds($customerIds);
        $rule->setProductTaxClassIds($productIds);
        $rule->setTaxRateIds($rateIds);

        return (int)$objectManager->get(TaxRuleRepositoryInterface::class)->save($rule)->getId();
    }

    /**
     * @param list<int> $customerIds
     * @param list<int> $productIds
     * @param list<int> $rateIds
     */
    protected function assertPersistedRule(int $ruleId, array $customerIds, array $productIds, array $rateIds): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(TaxRuleRegistry::class)->removeTaxRule($ruleId);
        $rule = $objectManager->get(TaxRuleRepositoryInterface::class)->get($ruleId);

        self::assertEqualsCanonicalizing($customerIds, array_map('intval', $rule->getCustomerTaxClassIds()));
        self::assertEqualsCanonicalizing($productIds, array_map('intval', $rule->getProductTaxClassIds()));
        self::assertEqualsCanonicalizing($rateIds, array_map('intval', $rule->getTaxRateIds()));
    }
}
