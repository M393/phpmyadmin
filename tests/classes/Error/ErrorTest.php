<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Error;

use PhpMyAdmin\Error\Error;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function preg_match;

use const DIRECTORY_SEPARATOR;

#[CoversClass(Error::class)]
class ErrorTest extends AbstractTestCase
{
    protected Error $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->object = new Error(2, 'Compile Error', 'error.txt', 15);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    /**
     * Test for setBacktrace
     */
    public function testSetBacktrace(): void
    {
        $bt = [['file' => 'bt1', 'line' => 2, 'function' => 'bar', 'args' => ['foo' => $this]]];
        $this->object->setBacktrace($bt);
        $bt[0]['args']['foo'] = '<Class:PhpMyAdmin\Tests\Error\ErrorTest>';
        self::assertEquals($bt, $this->object->getBacktrace());
    }

    /**
     * Test for setLine
     */
    public function testSetLine(): void
    {
        $this->object->setLine(15);
        self::assertEquals(15, $this->object->getLine());
    }

    /**
     * Test for setFile
     *
     * @param string $file     actual
     * @param string $expected expected
     */
    #[DataProvider('filePathProvider')]
    public function testSetFile(string $file, string $expected): void
    {
        $this->object->setFile($file);
        self::assertEquals($expected, $this->object->getFile());
    }

    /**
     * Data provider for setFile
     *
     * @return mixed[]
     */
    public static function filePathProvider(): array
    {
        return [
            ['./ChangeLog', '.' . DIRECTORY_SEPARATOR . 'ChangeLog'],
            [
                __FILE__,
                '.' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR
                    . 'classes' . DIRECTORY_SEPARATOR . 'Error' . DIRECTORY_SEPARATOR . 'ErrorTest.php',
            ],
            ['./NONEXISTING', 'NONEXISTING'],
        ];
    }

    /**
     * Test for getHash
     */
    public function testGetHash(): void
    {
        self::assertEquals(
            1,
            preg_match('/^([a-z0-9]*)$/', $this->object->getHash()),
        );
    }

    /**
     * Test for getBacktraceDisplay
     */
    public function testGetBacktraceDisplay(): void
    {
        self::assertStringContainsString(
            'PHPUnit\Framework\TestRunner->run(<Class:PhpMyAdmin\Tests\Error\ErrorTest>)',
            $this->object->getBacktraceDisplay(),
        );
    }

    /**
     * Test for getDisplay
     */
    public function testGetDisplay(): void
    {
        $actual = $this->object->getDisplay();
        self::assertStringStartsWith(
            '<div class="alert alert-danger" role="alert"><p><strong>Warning</strong> in error.txt#15</p>'
            . '<img src="themes/dot.gif" title="" alt="" class="icon ic_s_error"> Compile Error'
            . '<p class="mt-3"><strong>Backtrace</strong></p><ol class="list-group"><li class="list-group-item">',
            $actual,
        );
        self::assertStringContainsString(
            'PHPUnit\Framework\TestRunner->run(<Class:PhpMyAdmin\Tests\Error\ErrorTest>)</li>'
            . '<li class="list-group-item">',
            $actual,
        );
        self::assertStringEndsWith('</li></ol></div>' . "\n", $actual);
    }

    /**
     * Test for getHtmlTitle
     */
    public function testGetHtmlTitle(): void
    {
        self::assertEquals('Warning: Compile Error', $this->object->getHtmlTitle());
    }

    /**
     * Test for getTitle
     */
    public function testGetTitle(): void
    {
        self::assertEquals('Warning: Compile Error', $this->object->getTitle());
    }

    /**
     * Test for getBacktrace
     */
    public function testGetBacktrace(): void
    {
        $bt = [
            ['file' => 'bt1', 'line' => 2, 'function' => 'bar', 'args' => ['foo' => 1]],
            ['file' => 'bt2', 'line' => 2, 'function' => 'bar', 'args' => ['foo' => 2]],
            ['file' => 'bt3', 'line' => 2, 'function' => 'bar', 'args' => ['foo' => 3]],
            ['file' => 'bt4', 'line' => 2, 'function' => 'bar', 'args' => ['foo' => 4]],
        ];

        $this->object->setBacktrace($bt);

        // case: full backtrace
        self::assertCount(4, $this->object->getBacktrace());

        // case: first 2 frames
        self::assertCount(2, $this->object->getBacktrace(2));
    }
}
