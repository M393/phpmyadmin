<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Partitioning;

use PhpMyAdmin\Partitioning\SubPartition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubPartition::class)]
class SubPartitionTest extends TestCase
{
    public function testSubPartition(): void
    {
        $row = [
            'TABLE_SCHEMA' => 'TABLE_SCHEMA',
            'TABLE_NAME' => 'TABLE_NAME',
            'SUBPARTITION_NAME' => 'subpartition_name',
            'SUBPARTITION_ORDINAL_POSITION' => 1,
            'SUBPARTITION_METHOD' => 'subpartition_method',
            'SUBPARTITION_EXPRESSION' => 'subpartition_expression',
            'TABLE_ROWS' => 2,
            'DATA_LENGTH' => 3,
            'INDEX_LENGTH' => 4,
            'PARTITION_COMMENT' => 'partition_comment',
        ];
        $object = new SubPartition($row);
        self::assertEquals('subpartition_name', $object->getName());
        self::assertEquals(1, $object->getOrdinal());
        self::assertEquals('subpartition_method', $object->getMethod());
        self::assertEquals('subpartition_expression', $object->getExpression());
        self::assertEquals(2, $object->getRows());
        self::assertEquals(3, $object->getDataLength());
        self::assertEquals(4, $object->getIndexLength());
        self::assertEquals('partition_comment', $object->getComment());
    }
}
