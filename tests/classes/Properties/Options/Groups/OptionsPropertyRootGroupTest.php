<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Properties\Options\Groups;

use PhpMyAdmin\Properties\Options\Groups\OptionsPropertyRootGroup;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OptionsPropertyRootGroup::class)]
class OptionsPropertyRootGroupTest extends AbstractTestCase
{
    protected OptionsPropertyRootGroup $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->object = new OptionsPropertyRootGroup();
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
            'root',
            $this->object->getItemType(),
        );
    }

    /**
     * Test for contable interface
     */
    public function testCountable(): void
    {
        self::assertCount(0, $this->object);
    }
}
