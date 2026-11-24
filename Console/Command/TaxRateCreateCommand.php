<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Console\Command;

use InvalidArgumentException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\TaxCommands\Logger\ChangeLogger;
use Yireo\TaxCommands\Util\InputValidator;
use Yireo\TaxCommands\Util\SafeCell;

class TaxRateCreateCommand extends Command
{
    private const MAX_CODE_LENGTH = 255;
    private const MAX_POSTCODE_LENGTH = 10;

    /**
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param TaxRateInterfaceFactory $taxRateFactory
     * @param InputValidator $inputValidator
     * @param SafeCell $safeCell
     * @param ChangeLogger $changeLogger
     * @param string|null $name
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRateRepository,
        private readonly TaxRateInterfaceFactory $taxRateFactory,
        private readonly InputValidator $inputValidator,
        private readonly SafeCell $safeCell,
        private readonly ChangeLogger $changeLogger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tax:rates:create');
        $this->setDescription('Create a new tax rate');
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code');
        $this->addOption('rate', null, InputOption::VALUE_REQUIRED, 'Rate (0 to 100)');
        $this->addOption('country_id', null, InputOption::VALUE_REQUIRED, 'Country ID (ISO 3166-1 alpha-2)');
        $this->addOption('postcode', null, InputOption::VALUE_REQUIRED, 'Postcode', '*');
        $this->addOption('region_id', null, InputOption::VALUE_REQUIRED, 'Region ID (0 for all regions)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $code = $this->validateCode($input->getOption('code'));
            $rate = $this->validateRate($input->getOption('rate'));
            $countryId = $this->validateCountryId($input->getOption('country_id'));
            $postcode = $this->validatePostcode($input->getOption('postcode'));
            $regionId = $this->validateRegionId($input->getOption('region_id'));
        } catch (InvalidArgumentException $exception) {
            $this->writeError($output, $exception->getMessage());
            return Command::INVALID;
        }

        $taxRate = $this->taxRateFactory->create();
        $taxRate->setCode($code);
        $taxRate->setRate($rate);
        $taxRate->setTaxCountryId($countryId);
        $taxRate->setTaxPostcode($postcode);
        $taxRate->setTaxRegionId($regionId);

        try {
            $taxRate = $this->taxRateRepository->save($taxRate);
        } catch (AlreadyExistsException $exception) {
            $this->writeError($output, (string)__('Tax rate already exists: %1', $exception->getMessage()));
            return Command::FAILURE;
        } catch (InputException $exception) {
            $messages = [];
            foreach ($exception->getErrors() as $error) {
                $messages[] = $error->getMessage();
            }
            $this->writeError(
                $output,
                (string)__('Invalid tax rate: %1', $messages ? implode(' ', $messages) : $exception->getMessage())
            );
            return Command::FAILURE;
        } catch (LocalizedException $exception) {
            $this->writeError($output, (string)__('Could not save tax rate: %1', $exception->getMessage()));
            return Command::FAILURE;
        }

        $rateId = (int)$taxRate->getId();
        $this->changeLogger->logRateCreated(
            (string)$this->getName(),
            $rateId,
            $code,
            $rate,
            $countryId,
            $postcode,
            $regionId
        );
        $output->writeln(
            '<info>' . $this->safeCell->escape(__('Tax rate created with ID %1.', $rateId)) . '</info>'
        );

        return Command::SUCCESS;
    }

    private function validateCode(mixed $value): string
    {
        $code = $this->inputValidator->requiredString($value, 'code');
        if (mb_strlen($code) > self::MAX_CODE_LENGTH) {
            throw new InvalidArgumentException(
                (string)__('Option "--code" must not be longer than %1 characters.', self::MAX_CODE_LENGTH)
            );
        }

        return $code;
    }

    private function validateRate(mixed $value): float
    {
        $rate = $this->inputValidator->requiredString($value, 'rate');
        if (!is_numeric($rate) || (float)$rate < 0 || (float)$rate > 100) {
            throw new InvalidArgumentException((string)__('Option "--rate" must be a number between 0 and 100.'));
        }

        return (float)$rate;
    }

    private function validateCountryId(mixed $value): string
    {
        $countryId = strtoupper($this->inputValidator->requiredString($value, 'country_id'));
        if (!preg_match('/^[A-Z]{2}$/', $countryId)) {
            throw new InvalidArgumentException(
                (string)__('Option "--country_id" must be a two-letter country code.')
            );
        }

        return $countryId;
    }

    private function validatePostcode(mixed $value): string
    {
        $postcode = is_scalar($value) ? trim((string)$value, " \f\n\r\t\v\x00") : '';
        if (strlen($postcode) > self::MAX_POSTCODE_LENGTH) {
            throw new InvalidArgumentException(
                (string)__('Option "--postcode" must not be longer than %1 characters.', self::MAX_POSTCODE_LENGTH)
            );
        }

        return $postcode === '' ? '*' : $postcode;
    }

    private function validateRegionId(mixed $value): int
    {
        if ($value === null || (is_string($value) && trim($value, " \f\n\r\t\v\x00") === '')) {
            return 0;
        }

        return $this->inputValidator->nonNegativeInt($value, 'region_id');
    }

    private function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln('<error>' . $this->safeCell->escape($message) . '</error>');
    }
}
