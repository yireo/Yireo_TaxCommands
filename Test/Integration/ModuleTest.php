<?php
declare(strict_types=1);

namespace Yireo\TaxCommands\Test\Integration;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Module\Manager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    public function testRequiredModulesAreRegisteredAndEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $registrar = $objectManager->get(ComponentRegistrar::class);
        $moduleManager = $objectManager->get(Manager::class);

        foreach (['Yireo_TaxCommands', 'Magento_Tax'] as $moduleName) {
            self::assertNotNull($registrar->getPath(ComponentRegistrar::MODULE, $moduleName), $moduleName);
            self::assertTrue($moduleManager->isEnabled($moduleName), $moduleName);
        }
    }
}
