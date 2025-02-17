<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Plugins\Export;

use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Dbal\ConnectionType;
use PhpMyAdmin\Export\Export;
use PhpMyAdmin\Plugins\Export\ExportOds;
use PhpMyAdmin\Properties\Options\Groups\OptionsPropertyMainGroup;
use PhpMyAdmin\Properties\Options\Groups\OptionsPropertyRootGroup;
use PhpMyAdmin\Properties\Options\Items\BoolPropertyItem;
use PhpMyAdmin\Properties\Options\Items\HiddenPropertyItem;
use PhpMyAdmin\Properties\Options\Items\TextPropertyItem;
use PhpMyAdmin\Properties\Plugins\ExportPluginProperties;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\FieldHelper;
use PhpMyAdmin\Tests\Stubs\DummyResult;
use PhpMyAdmin\Transformations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use ReflectionMethod;
use ReflectionProperty;

use function bin2hex;

use const MYSQLI_BLOB_FLAG;
use const MYSQLI_TYPE_DATE;
use const MYSQLI_TYPE_DATETIME;
use const MYSQLI_TYPE_DECIMAL;
use const MYSQLI_TYPE_STRING;
use const MYSQLI_TYPE_TIME;
use const MYSQLI_TYPE_TINY_BLOB;

#[CoversClass(ExportOds::class)]
#[Group('medium')]
#[RequiresPhpExtension('zip')]
class ExportOdsTest extends AbstractTestCase
{
    protected ExportOds $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $dbi = $this->createDatabaseInterface();
        DatabaseInterface::$instance = $dbi;
        $GLOBALS['output_kanji_conversion'] = false;
        $GLOBALS['output_charset_conversion'] = false;
        $GLOBALS['buffer_needed'] = false;
        $GLOBALS['asfile'] = true;
        $GLOBALS['save_on_server'] = false;
        $this->object = new ExportOds(
            new Relation($dbi),
            new Export($dbi),
            new Transformations(),
        );
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        DatabaseInterface::$instance = null;
        unset($this->object);
    }

    public function testSetProperties(): void
    {
        $method = new ReflectionMethod(ExportOds::class, 'setProperties');
        $method->invoke($this->object, null);

        $attrProperties = new ReflectionProperty(ExportOds::class, 'properties');
        $properties = $attrProperties->getValue($this->object);

        self::assertInstanceOf(ExportPluginProperties::class, $properties);

        self::assertEquals(
            'OpenDocument Spreadsheet',
            $properties->getText(),
        );

        self::assertEquals(
            'ods',
            $properties->getExtension(),
        );

        self::assertEquals(
            'application/vnd.oasis.opendocument.spreadsheet',
            $properties->getMimeType(),
        );

        self::assertEquals(
            'Options',
            $properties->getOptionsText(),
        );

        self::assertTrue(
            $properties->getForceFile(),
        );

        $options = $properties->getOptions();

        self::assertInstanceOf(OptionsPropertyRootGroup::class, $options);

        self::assertEquals(
            'Format Specific Options',
            $options->getName(),
        );

        $generalOptionsArray = $options->getProperties();
        $generalOptions = $generalOptionsArray->current();

        self::assertInstanceOf(OptionsPropertyMainGroup::class, $generalOptions);

        self::assertEquals(
            'general_opts',
            $generalOptions->getName(),
        );

        $generalProperties = $generalOptions->getProperties();

        $property = $generalProperties->current();
        $generalProperties->next();

        self::assertInstanceOf(TextPropertyItem::class, $property);

        self::assertEquals(
            'null',
            $property->getName(),
        );

        self::assertEquals(
            'Replace NULL with:',
            $property->getText(),
        );

        $property = $generalProperties->current();
        $generalProperties->next();

        self::assertInstanceOf(BoolPropertyItem::class, $property);

        self::assertEquals(
            'columns',
            $property->getName(),
        );

        self::assertEquals(
            'Put columns names in the first row',
            $property->getText(),
        );

        $property = $generalProperties->current();

        self::assertInstanceOf(HiddenPropertyItem::class, $property);

        self::assertEquals(
            'structure_or_data',
            $property->getName(),
        );
    }

    public function testExportHeader(): void
    {
        self::assertArrayHasKey('ods_buffer', $GLOBALS);

        self::assertTrue(
            $this->object->exportHeader(),
        );
    }

    public function testExportFooter(): void
    {
        $GLOBALS['ods_buffer'] = 'header';
        self::assertTrue($this->object->exportFooter());
        $output = $this->getActualOutputForAssertion();
        self::assertMatchesRegularExpression('/^504b.*636f6e74656e742e786d6c/', bin2hex($output));
        self::assertStringContainsString('header', $GLOBALS['ods_buffer']);
        self::assertStringContainsString('</office:spreadsheet>', $GLOBALS['ods_buffer']);
        self::assertStringContainsString('</office:body>', $GLOBALS['ods_buffer']);
        self::assertStringContainsString('</office:document-content>', $GLOBALS['ods_buffer']);
    }

    public function testExportDBHeader(): void
    {
        self::assertTrue(
            $this->object->exportDBHeader('testDB'),
        );
    }

    public function testExportDBFooter(): void
    {
        self::assertTrue(
            $this->object->exportDBFooter('testDB'),
        );
    }

    public function testExportDBCreate(): void
    {
        self::assertTrue(
            $this->object->exportDBCreate('testDB', 'database'),
        );
    }

    public function testExportData(): void
    {
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fields = [
            FieldHelper::fromArray(['type' => -1]),
            FieldHelper::fromArray([
                'type' => MYSQLI_TYPE_TINY_BLOB,
                'flags' => MYSQLI_BLOB_FLAG,
                'charsetnr' => 63,
            ]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_DATE]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_TIME]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_DATETIME]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_DECIMAL]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_DECIMAL]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_STRING]),
        ];
        $resultStub = self::createMock(DummyResult::class);

        $dbi->expects(self::once())
            ->method('getFieldsMeta')
            ->with($resultStub)
            ->willReturn($fields);

        $dbi->expects(self::once())
            ->method('query')
            ->with('SELECT', ConnectionType::User, DatabaseInterface::QUERY_UNBUFFERED)
            ->willReturn($resultStub);

        $resultStub->expects(self::once())
            ->method('numFields')
            ->willReturn(8);

        $resultStub->expects(self::exactly(2))
            ->method('fetchRow')
            ->willReturn(
                [null, '01-01-2000', '01-01-2000', '01-01-2000 10:00:00', '01-01-2014 10:02:00', 't>s', 'a&b', '<'],
                [],
            );

        DatabaseInterface::$instance = $dbi;
        $GLOBALS['mediawiki_caption'] = true;
        $GLOBALS['mediawiki_headers'] = true;
        $GLOBALS['what'] = 'foo';
        $GLOBALS['foo_null'] = '&';

        self::assertTrue(
            $this->object->exportData(
                'db',
                'table',
                'example.com',
                'SELECT',
            ),
        );

        self::assertEquals(
            '<table:table table:name="table"><table:table-row><table:table-cell ' .
            'office:value-type="string"><text:p>&amp;</text:p></table:table-cell>' .
            '<table:table-cell office:value-type="string"><text:p></text:p>' .
            '</table:table-cell><table:table-cell office:value-type="date" office:' .
            'date-value="2000-01-01" table:style-name="DateCell"><text:p>01-01' .
            '-2000</text:p></table:table-cell><table:table-cell office:value-type=' .
            '"time" office:time-value="PT10H00M00S" table:style-name="TimeCell">' .
            '<text:p>01-01-2000 10:00:00</text:p></table:table-cell><table:table-' .
            'cell office:value-type="date" office:date-value="2014-01-01T10:02:00"' .
            ' table:style-name="DateTimeCell"><text:p>01-01-2014 10:02:00' .
            '</text:p></table:table-cell><table:table-cell office:value-type=' .
            '"float" office:value="t>s" ><text:p>t&gt;s</text:p>' .
            '</table:table-cell><table:table-cell office:value-type="float" ' .
            'office:value="a&b" ><text:p>a&amp;b</text:p></table:table-cell>' .
            '<table:table-cell office:value-type="string"><text:p>&lt;</text:p>' .
            '</table:table-cell></table:table-row></table:table>',
            $GLOBALS['ods_buffer'],
        );
    }

    public function testExportDataWithFieldNames(): void
    {
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fields = [
            FieldHelper::fromArray([
                'type' => MYSQLI_TYPE_STRING,
                'name' => 'fna\"me',
                'length' => 20,
            ]),
            FieldHelper::fromArray([
                'type' => MYSQLI_TYPE_STRING,
                'name' => 'fnam/<e2',
                'length' => 20,
            ]),
        ];

        $resultStub = self::createMock(DummyResult::class);

        $dbi->expects(self::once())
            ->method('getFieldsMeta')
            ->with($resultStub)
            ->willReturn($fields);

        $dbi->expects(self::once())
            ->method('query')
            ->with('SELECT', ConnectionType::User, DatabaseInterface::QUERY_UNBUFFERED)
            ->willReturn($resultStub);

        $resultStub->expects(self::once())
            ->method('numFields')
            ->willReturn(2);

        $resultStub->expects(self::exactly(1))
            ->method('fetchRow')
            ->willReturn([]);

        DatabaseInterface::$instance = $dbi;
        $GLOBALS['mediawiki_caption'] = true;
        $GLOBALS['mediawiki_headers'] = true;
        $GLOBALS['what'] = 'foo';
        $GLOBALS['foo_null'] = '&';
        $GLOBALS['foo_columns'] = true;

        self::assertTrue(
            $this->object->exportData(
                'db',
                'table',
                'example.com',
                'SELECT',
            ),
        );

        self::assertEquals(
            '<table:table table:name="table"><table:table-row><table:table-cell ' .
            'office:value-type="string"><text:p>fna\&quot;me</text:p></table:table' .
            '-cell><table:table-cell office:value-type="string"><text:p>' .
            'fnam/&lt;e2</text:p></table:table-cell></table:table-row>' .
            '</table:table>',
            $GLOBALS['ods_buffer'],
        );

        // with no row count
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $flags = [];

        $resultStub = self::createMock(DummyResult::class);

        $dbi->expects(self::once())
            ->method('getFieldsMeta')
            ->with($resultStub)
            ->willReturn($flags);

        $dbi->expects(self::once())
            ->method('query')
            ->with('SELECT', ConnectionType::User, DatabaseInterface::QUERY_UNBUFFERED)
            ->willReturn($resultStub);

        $resultStub->expects(self::once())
            ->method('numFields')
            ->willReturn(0);

        $resultStub->expects(self::once())
            ->method('fetchRow')
            ->willReturn([]);

        DatabaseInterface::$instance = $dbi;
        $GLOBALS['mediawiki_caption'] = true;
        $GLOBALS['mediawiki_headers'] = true;
        $GLOBALS['what'] = 'foo';
        $GLOBALS['foo_null'] = '&';
        $GLOBALS['ods_buffer'] = '';

        self::assertTrue(
            $this->object->exportData(
                'db',
                'table',
                'example.com',
                'SELECT',
            ),
        );

        self::assertEquals(
            '<table:table table:name="table"><table:table-row></table:table-row></table:table>',
            $GLOBALS['ods_buffer'],
        );
    }
}
