<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Properties\Plugins;

use PhpMyAdmin\Properties\Plugins\ImportPluginProperties;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImportPluginProperties::class)]
class ImportPluginPropertiesTest extends AbstractTestCase
{
    protected ImportPluginProperties $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->object = new ImportPluginProperties();
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    public function testGetItemType(): void
    {
        self::assertEquals(
            'import',
            $this->object->getItemType(),
        );
    }

    /**
     * Test for
     *     - PhpMyAdmin\Properties\Plugins\ImportPluginProperties::getOptionsText
     *     - PhpMyAdmin\Properties\Plugins\ImportPluginProperties::setOptionsText
     */
    public function testSetGetOptionsText(): void
    {
        $this->object->setOptionsText('options123');

        self::assertEquals(
            'options123',
            $this->object->getOptionsText(),
        );
    }

    /**
     * Test for
     *     - PhpMyAdmin\Properties\Plugins\ImportPluginProperties::setMimeType
     *     - PhpMyAdmin\Properties\Plugins\ImportPluginProperties::getMimeType
     */
    public function testSetGetMimeType(): void
    {
        $this->object->setMimeType('mime123');

        self::assertEquals(
            'mime123',
            $this->object->getMimeType(),
        );
    }
}
