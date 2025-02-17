<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Navigation\Nodes;

use PhpMyAdmin\Config;
use PhpMyAdmin\Navigation\Nodes\NodeFunction;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NodeFunction::class)]
class NodeFunctionTest extends AbstractTestCase
{
    /**
     * Test for __construct
     */
    public function testConstructor(): void
    {
        $parent = new NodeFunction(new Config(), 'default');
        self::assertEquals(
            [
                'text' => [
                    'route' => '/database/routines',
                    'params' => ['item_type' => 'FUNCTION', 'edit_item' => 1, 'db' => null, 'item_name' => null],
                ],
                'icon' => [
                    'route' => '/database/routines',
                    'params' => ['item_type' => 'FUNCTION', 'execute_dialog' => 1, 'db' => null, 'item_name' => null],
                ],
            ],
            $parent->links,
        );
    }
}
