<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration\Console\Command;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Yireo\TaxCommands\Console\Command\TaxRateCreateCommand;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class TaxRateCreateCommandTest extends TaxCommandTestCase
{
    /**
     * @dataProvider invalidOptions
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOption(string $option, string $value, string $message): void
    {
        $tester = $this->tester(TaxRateCreateCommand::class);
        $input = [$option => $value] + $this->input($this->uniqueCode('invalid-rate'));

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertStringContainsString($option . '" ' . $message, $tester->getDisplay());
        self::assertCount(0, $this->findRates($input['--code']));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidOptions(): array
    {
        return [
            'rate above 100' => ['--rate', '100.01', 'must be a number between 0 and 100'],
            'negative rate' => ['--rate', '-0.01', 'must be a number between 0 and 100'],
            'non-numeric rate' => ['--rate', 'abc', 'must be a number between 0 and 100'],
            'three-letter country' => ['--country_id', 'USA', 'must be a two-letter country code'],
            'digit in country' => ['--country_id', '1A', 'must be a two-letter country code'],
            'empty country' => ['--country_id', '', 'is required'],
            'code too long' => ['--code', str_repeat('x', 256), 'must not be longer than 255 characters'],
        ];
    }

    public function testCreatesRateWithUppercaseCountryAndReportsItsId(): void
    {
        $code = $this->uniqueCode('valid-rate');
        $tester = $this->tester(TaxRateCreateCommand::class);
        $input = ['--country_id' => 'nl'] + $this->input($code);

        self::assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        $rates = $this->findRates($code);
        self::assertCount(1, $rates);
        $rate = $rates[0];
        $rateId = (int)$rate->getId();
        self::assertGreaterThan(0, $rateId);
        self::assertSame('NL', $rate->getTaxCountryId());
        self::assertSame(21.5, (float)$rate->getRate());
        self::assertStringContainsString("Tax rate created with ID $rateId.", $tester->getDisplay());
    }

    public function testRejectsPostcodeLongerThanTenCharacters(): void
    {
        $code = $this->uniqueCode('long-postcode');
        $tester = $this->tester(TaxRateCreateCommand::class);
        $input = $this->input($code) + ['--postcode' => '12345678901'];

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertCount(0, $this->findRates($code));
    }

    public function testRejectsRegionFromAnotherCountry(): void
    {
        $code = $this->uniqueCode('foreign-region');
        $tester = $this->tester(TaxRateCreateCommand::class);
        $input = $this->input($code) + ['--region_id' => (string)$this->getCaliforniaRegionId()];

        self::assertSame(Command::INVALID, $tester->execute($input, ['interactive' => false]));
        self::assertCount(0, $this->findRates($code));
    }

    public function testAcceptsRegionFromSelectedCountry(): void
    {
        $code = $this->uniqueCode('valid-region');
        $regionId = $this->getCaliforniaRegionId();
        $tester = $this->tester(TaxRateCreateCommand::class);
        $input = ['--country_id' => 'US', '--region_id' => (string)$regionId] + $this->input($code);

        self::assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        $rates = $this->findRates($code);
        self::assertCount(1, $rates);
        self::assertSame('US', $rates[0]->getTaxCountryId());
        self::assertSame($regionId, (int)$rates[0]->getTaxRegionId());
    }

    public function testDuplicateCodeReturnsFailureWithoutReplacingRate(): void
    {
        $code = $this->uniqueCode('duplicate-rate');
        $originalRateId = $this->createRate($code);
        $tester = $this->tester(TaxRateCreateCommand::class);

        self::assertSame(Command::FAILURE, $tester->execute($this->input($code), ['interactive' => false]));
        self::assertStringContainsString('Tax rate already exists', $tester->getDisplay());
        $rates = $this->findRates($code);
        self::assertCount(1, $rates);
        self::assertSame($originalRateId, (int)$rates[0]->getId());
        self::assertSame(9.5, (float)$rates[0]->getRate());
    }

    private function getCaliforniaRegionId(): int
    {
        $regionFactory = Bootstrap::getObjectManager()->get(RegionFactory::class);
        $regionId = (int)$regionFactory->create()->loadByCode('CA', 'US')->getId();
        self::assertGreaterThan(0, $regionId);

        return $regionId;
    }

    /**
     * @return array<string, string>
     */
    private function input(string $code): array
    {
        return [
            '--code' => $code,
            '--rate' => '21.5',
            '--country_id' => 'NL',
        ];
    }

    /**
     * @return list<TaxRateInterface>
     */
    private function findRates(string $code): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $builder = $objectManager->get(SearchCriteriaBuilderFactory::class)->create();
        $criteria = $builder->addFilter('code', $code)->create();
        $results = $objectManager->get(TaxRateRepositoryInterface::class)->getList($criteria);

        return array_values($results->getItems());
    }
}
