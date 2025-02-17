<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\ZipExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use ZipArchive;

use function file_put_contents;
use function tempnam;
use function unlink;

#[CoversClass(ZipExtension::class)]
#[RequiresPhpExtension('zip')]
class ZipExtensionTest extends AbstractTestCase
{
    private ZipExtension $zipExtension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zipExtension = new ZipExtension(new ZipArchive());
    }

    /**
     * Test for getContents
     *
     * @param string                $file          path to zip file
     * @param string|null           $specificEntry regular expression to match a file
     * @param array<string, string> $output        expected output
     * @psalm-param array{error: string, data: string} $output
     */
    #[DataProvider('provideTestGetContents')]
    public function testGetContents(string $file, string|null $specificEntry, array $output): void
    {
        self::assertEquals(
            $this->zipExtension->getContents($file, $specificEntry),
            $output,
        );
    }

    /**
     * @return array<string, array<int, array<string, string>|string|null>>
     * @psalm-return array<string, array{string, string|null, array{error: string, data: string}}>
     */
    public static function provideTestGetContents(): array
    {
        return [
            'null as specific entry' => [
                './tests/test_data/test.zip',
                null,
                ['error' => '', 'data' => 'TEST FILE' . "\n"],
            ],
            'an existent specific entry' => [
                './tests/test_data/test.zip',
                '/test.file/',
                ['error' => '', 'data' => 'TEST FILE' . "\n"],
            ],
            'a nonexistent specific entry' => [
                './tests/test_data/test.zip',
                '/foobar/',
                ['error' => 'Error in ZIP archive: Could not find "/foobar/"', 'data' => ''],
            ],
        ];
    }

    /**
     * Test for findFile
     *
     * @param string      $file       path to zip file
     * @param string      $fileRegexp regular expression for the file name to match
     * @param string|bool $output     expected output
     * @psalm-param string|false $output
     */
    #[DataProvider('provideTestFindFile')]
    public function testFindFile(string $file, string $fileRegexp, string|bool $output): void
    {
        self::assertEquals(
            $this->zipExtension->findFile($file, $fileRegexp),
            $output,
        );
    }

    /**
     * Provider for testFindFileFromZipArchive
     *
     * @return array<int, array<int, string|bool>>
     * @psalm-return array<int, array{string, string, string|false}>
     */
    public static function provideTestFindFile(): array
    {
        return [
            ['./tests/test_data/test.zip', '/test/', 'test.file'],
            ['./tests/test_data/test.zip', '/invalid/', false],
        ];
    }

    /**
     * Test for getNumberOfFiles
     */
    public function testGetNumberOfFiles(): void
    {
        self::assertEquals(
            $this->zipExtension->getNumberOfFiles('./tests/test_data/test.zip'),
            1,
        );
    }

    /**
     * Test for extract
     */
    public function testExtract(): void
    {
        self::assertFalse(
            $this->zipExtension->extract(
                './tests/test_data/test.zip',
                'wrongName',
            ),
        );
        self::assertEquals(
            "TEST FILE\n",
            $this->zipExtension->extract(
                './tests/test_data/test.zip',
                'test.file',
            ),
        );
    }

    /**
     * Test for createFile
     */
    public function testCreateSingleFile(): void
    {
        $file = $this->zipExtension->createFile('Test content', 'test.txt');
        self::assertIsString($file);
        self::assertNotEmpty($file);

        $tmp = tempnam('./', 'zip-test');
        self::assertNotFalse($tmp);
        self::assertNotFalse(file_put_contents($tmp, $file));

        $zip = new ZipArchive();
        self::assertTrue(
            $zip->open($tmp),
        );

        self::assertEquals(0, $zip->locateName('test.txt'));

        $zip->close();
        unlink($tmp);
    }

    /**
     * Test for createFile
     */
    public function testCreateFailure(): void
    {
        self::assertFalse(
            $this->zipExtension->createFile(
                'Content',
                ['name1.txt', 'name2.txt'],
            ),
        );
    }

    /**
     * Test for createFile
     */
    public function testCreateMultiFile(): void
    {
        $file = $this->zipExtension->createFile(
            ['Content', 'Content2'],
            ['name1.txt', 'name2.txt'],
        );
        self::assertIsString($file);
        self::assertNotEmpty($file);

        $tmp = tempnam('./', 'zip-test');
        self::assertNotFalse($tmp);
        self::assertNotFalse(file_put_contents($tmp, $file));

        $zip = new ZipArchive();
        self::assertTrue(
            $zip->open($tmp),
        );

        self::assertEquals(0, $zip->locateName('name1.txt'));
        self::assertEquals(1, $zip->locateName('name2.txt'));

        $zip->close();
        unlink($tmp);
    }
}
