<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Plugins\Export;

use PhpMyAdmin\Column;
use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationParameters;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Dbal\ConnectionType;
use PhpMyAdmin\Export\Export;
use PhpMyAdmin\Identifiers\TableName;
use PhpMyAdmin\Identifiers\TriggerName;
use PhpMyAdmin\Plugins\Export\ExportOdt;
use PhpMyAdmin\Properties\Options\Groups\OptionsPropertyMainGroup;
use PhpMyAdmin\Properties\Options\Groups\OptionsPropertyRootGroup;
use PhpMyAdmin\Properties\Options\Items\BoolPropertyItem;
use PhpMyAdmin\Properties\Options\Items\RadioPropertyItem;
use PhpMyAdmin\Properties\Options\Items\TextPropertyItem;
use PhpMyAdmin\Properties\Plugins\ExportPluginProperties;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\FieldHelper;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\DummyResult;
use PhpMyAdmin\Transformations;
use PhpMyAdmin\Triggers\Event;
use PhpMyAdmin\Triggers\Timing;
use PhpMyAdmin\Triggers\Trigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use ReflectionMethod;
use ReflectionProperty;

use function __;
use function bin2hex;

use const MYSQLI_BLOB_FLAG;
use const MYSQLI_NUM_FLAG;
use const MYSQLI_TYPE_BLOB;
use const MYSQLI_TYPE_DECIMAL;
use const MYSQLI_TYPE_STRING;

#[CoversClass(ExportOdt::class)]
#[Group('medium')]
#[RequiresPhpExtension('zip')]
class ExportOdtTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    protected ExportOdt $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;
        $GLOBALS['output_kanji_conversion'] = false;
        $GLOBALS['output_charset_conversion'] = false;
        $GLOBALS['buffer_needed'] = false;
        $GLOBALS['asfile'] = true;
        $GLOBALS['save_on_server'] = false;
        $GLOBALS['plugin_param'] = [];
        $GLOBALS['plugin_param']['export_type'] = 'table';
        $GLOBALS['plugin_param']['single_table'] = false;
        Config::getInstance()->selectedServer['DisableIS'] = true;
        $this->object = new ExportOdt(
            new Relation($this->dbi),
            new Export($this->dbi),
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
        $GLOBALS['plugin_param']['export_type'] = '';
        $GLOBALS['plugin_param']['single_table'] = false;

        $relationParameters = RelationParameters::fromArray([
            'db' => 'db',
            'relation' => 'relation',
            'column_info' => 'column_info',
            'relwork' => true,
            'mimework' => true,
        ]);
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, $relationParameters);

        $method = new ReflectionMethod(ExportOdt::class, 'setProperties');
        $properties = $method->invoke($this->object, null);

        self::assertInstanceOf(ExportPluginProperties::class, $properties);

        self::assertEquals(
            'OpenDocument Text',
            $properties->getText(),
        );

        self::assertEquals(
            'odt',
            $properties->getExtension(),
        );

        self::assertEquals(
            'application/vnd.oasis.opendocument.text',
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
        $generalOptionsArray->next();

        self::assertInstanceOf(OptionsPropertyMainGroup::class, $generalOptions);

        self::assertEquals(
            'general_opts',
            $generalOptions->getName(),
        );

        self::assertEquals(
            'Dump table',
            $generalOptions->getText(),
        );

        $generalProperties = $generalOptions->getProperties();

        $property = $generalProperties->current();

        self::assertInstanceOf(RadioPropertyItem::class, $property);

        self::assertEquals(
            'structure_or_data',
            $property->getName(),
        );

        self::assertEquals(
            ['structure' => __('structure'), 'data' => __('data'), 'structure_and_data' => __('structure and data')],
            $property->getValues(),
        );

        $generalOptions = $generalOptionsArray->current();
        $generalOptionsArray->next();

        self::assertInstanceOf(OptionsPropertyMainGroup::class, $generalOptions);

        self::assertEquals(
            'structure',
            $generalOptions->getName(),
        );

        self::assertEquals(
            'Object creation options',
            $generalOptions->getText(),
        );

        self::assertEquals(
            'data',
            $generalOptions->getForce(),
        );

        $generalProperties = $generalOptions->getProperties();

        $property = $generalProperties->current();
        $generalProperties->next();

        self::assertInstanceOf(BoolPropertyItem::class, $property);

        self::assertEquals(
            'relation',
            $property->getName(),
        );

        self::assertEquals(
            'Display foreign key relationships',
            $property->getText(),
        );

        $property = $generalProperties->current();
        $generalProperties->next();

        self::assertInstanceOf(BoolPropertyItem::class, $property);

        self::assertEquals(
            'comments',
            $property->getName(),
        );

        self::assertEquals(
            'Display comments',
            $property->getText(),
        );

        $property = $generalProperties->current();

        self::assertInstanceOf(BoolPropertyItem::class, $property);

        self::assertEquals(
            'mime',
            $property->getName(),
        );

        self::assertEquals(
            'Display media types',
            $property->getText(),
        );

        // hide structure
        $generalOptions = $generalOptionsArray->current();

        self::assertInstanceOf(OptionsPropertyMainGroup::class, $generalOptions);

        self::assertEquals(
            'data',
            $generalOptions->getName(),
        );

        self::assertEquals(
            'Data dump options',
            $generalOptions->getText(),
        );

        self::assertEquals(
            'structure',
            $generalOptions->getForce(),
        );

        $generalProperties = $generalOptions->getProperties();

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

        self::assertInstanceOf(TextPropertyItem::class, $property);

        self::assertEquals(
            'null',
            $property->getName(),
        );

        self::assertEquals(
            'Replace NULL with:',
            $property->getText(),
        );

        // case 2
        $GLOBALS['plugin_param']['export_type'] = 'table';
        $GLOBALS['plugin_param']['single_table'] = false;

        $method->invoke($this->object, null);

        $generalOptionsArray = $options->getProperties();

        self::assertCount(3, $generalOptionsArray);
    }

    public function testExportHeader(): void
    {
        self::assertTrue(
            $this->object->exportHeader(),
        );

        self::assertStringContainsString('<office:document-content', $GLOBALS['odt_buffer']);
        self::assertStringContainsString('office:version', $GLOBALS['odt_buffer']);
    }

    public function testExportFooter(): void
    {
        $GLOBALS['odt_buffer'] = 'header';
        self::assertTrue($this->object->exportFooter());
        $output = $this->getActualOutputForAssertion();
        self::assertMatchesRegularExpression('/^504b.*636f6e74656e742e786d6c/', bin2hex($output));
        self::assertStringContainsString('header', $GLOBALS['odt_buffer']);
        self::assertStringContainsString(
            '</office:text></office:body></office:document-content>',
            $GLOBALS['odt_buffer'],
        );
    }

    public function testExportDBHeader(): void
    {
        $GLOBALS['odt_buffer'] = 'header';

        self::assertTrue(
            $this->object->exportDBHeader('d&b'),
        );

        self::assertStringContainsString('header', $GLOBALS['odt_buffer']);

        self::assertStringContainsString('Database d&amp;b</text:h>', $GLOBALS['odt_buffer']);
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
                'type' => MYSQLI_TYPE_BLOB,
                'flags' => MYSQLI_BLOB_FLAG,
                'charsetnr' => 63,
            ]),
            FieldHelper::fromArray(['type' => MYSQLI_TYPE_DECIMAL, 'flags' => MYSQLI_NUM_FLAG]),
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
            ->willReturn(4);

        $resultStub->expects(self::exactly(2))
            ->method('fetchRow')
            ->willReturn([null, 'a<b', 'a>b', 'a&b'], []);

        DatabaseInterface::$instance = $dbi;
        $GLOBALS['what'] = 'foo';
        $GLOBALS['foo_null'] = '&';
        unset($GLOBALS['foo_columns']);

        self::assertTrue(
            $this->object->exportData(
                'db',
                'ta<ble',
                'example.com',
                'SELECT',
            ),
        );

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" ' .
            'text:is-list-header="true">Dumping data for table ta&lt;ble</text:h>' .
            '<table:table table:name="ta&lt;ble_structure"><table:table-column ' .
            'table:number-columns-repeated="4"/><table:table-row>' .
            '<table:table-cell office:value-type="string"><text:p>&amp;</text:p>' .
            '</table:table-cell><table:table-cell office:value-type="string">' .
            '<text:p></text:p></table:table-cell><table:table-cell ' .
            'office:value-type="float" office:value="a>b" ><text:p>a&gt;b</text:p>' .
            '</table:table-cell><table:table-cell office:value-type="string">' .
            '<text:p>a&amp;b</text:p></table:table-cell></table:table-row>' .
            '</table:table>',
            $GLOBALS['odt_buffer'],
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
            '<text:h text:outline-level="2" text:style-name="Heading_2" text:' .
            'is-list-header="true">Dumping data for table table</text:h><table:' .
            'table table:name="table_structure"><table:table-column table:number-' .
            'columns-repeated="2"/><table:table-row><table:table-cell office:' .
            'value-type="string"><text:p>fna\&quot;me</text:p></table:table-cell>' .
            '<table:table-cell office:value-type="string"><text:p>fnam/&lt;e2' .
            '</text:p></table:table-cell></table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
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
        $GLOBALS['odt_buffer'] = '';

        self::assertTrue(
            $this->object->exportData(
                'db',
                'table',
                'example.com',
                'SELECT',
            ),
        );

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" ' .
            'text:is-list-header="true">Dumping data for table table</text:h>' .
            '<table:table table:name="table_structure"><table:table-column ' .
            'table:number-columns-repeated="0"/><table:table-row>' .
            '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );
    }

    public function testGetTableDefStandIn(): void
    {
        $this->dummyDbi->addSelectDb('test_db');
        self::assertSame(
            $this->object->getTableDefStandIn('test_db', 'test_table'),
            '',
        );
        $this->dummyDbi->assertAllSelectsConsumed();

        self::assertEquals(
            '<table:table table:name="test_table_data">'
            . '<table:table-column table:number-columns-repeated="4"/><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>Column</text:p>'
            . '</table:table-cell><table:table-cell office:value-type="string"><text:p>Type</text:p>'
            . '</table:table-cell><table:table-cell office:value-type="string"><text:p>Null</text:p>'
            . '</table:table-cell><table:table-cell office:value-type="string"><text:p>Default</text:p>'
            . '</table:table-cell></table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>id</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>int(11)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>name</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>varchar(20)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>datetimefield</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>datetime</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );
    }

    public function testGetTableDef(): void
    {
        $this->object = $this->getMockBuilder(ExportOdt::class)
            ->onlyMethods(['formatOneColumnDefinition'])
            ->setConstructorArgs([new Relation($this->dbi), new Export($this->dbi), new Transformations()])
            ->getMock();

        // case 1

        $resultStub = self::createMock(DummyResult::class);

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects(self::exactly(2))
            ->method('fetchResult')
            ->willReturn(
                [],
                ['fieldname' => ['values' => 'test-', 'transformation' => 'testfoo', 'mimetype' => 'test<']],
            );

        $column = new Column('fieldname', '', false, '', null, '');
        $dbi->expects(self::once())
            ->method('getColumns')
            ->with('database', '')
            ->willReturn([$column]);

        $dbi->expects(self::once())
            ->method('tryQueryAsControlUser')
            ->willReturn($resultStub);

        $resultStub->expects(self::once())
            ->method('numRows')
            ->willReturn(1);

        $resultStub->expects(self::once())
            ->method('fetchAssoc')
            ->willReturn(['comment' => 'testComment']);

        DatabaseInterface::$instance = $dbi;
        $this->object->relation = new Relation($dbi);

        $this->object->expects(self::exactly(2))
            ->method('formatOneColumnDefinition')
            ->with($column)
            ->willReturn('1');

        $relationParameters = RelationParameters::fromArray([
            'relwork' => true,
            'commwork' => true,
            'mimework' => true,
            'db' => 'database',
            'relation' => 'rel',
            'column_info' => 'col',
        ]);
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, $relationParameters);

        self::assertTrue(
            $this->object->getTableDef(
                'database',
                '',
                true,
                true,
                true,
            ),
        );

        self::assertStringContainsString(
            '<table:table table:name="_structure"><table:table-column table:number-columns-repeated="6"/>',
            $GLOBALS['odt_buffer'],
        );

        self::assertStringContainsString(
            '<table:table-cell office:value-type="string"><text:p>Comments</text:p></table:table-cell>',
            $GLOBALS['odt_buffer'],
        );

        self::assertStringContainsString(
            '<table:table-cell office:value-type="string"><text:p>Media type</text:p></table:table-cell>',
            $GLOBALS['odt_buffer'],
        );

        self::assertStringContainsString(
            '</table:table-row>1<table:table-cell office:value-type="string">' .
            '<text:p></text:p></table:table-cell><table:table-cell office:value-' .
            'type="string"><text:p>Test&lt;</text:p></table:table-cell>' .
            '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );

        // case 2

        $resultStub = self::createMock(DummyResult::class);

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects(self::exactly(2))
            ->method('fetchResult')
            ->willReturn(
                ['fieldname' => ['foreign_table' => 'ftable', 'foreign_field' => 'ffield']],
                ['field' => ['values' => 'test-', 'transformation' => 'testfoo', 'mimetype' => 'test<']],
            );

        $column = new Column('fieldname', '', false, '', null, '');
        $dbi->expects(self::once())
            ->method('getColumns')
            ->with('database', '')
            ->willReturn([$column]);

        $dbi->expects(self::once())
            ->method('tryQueryAsControlUser')
            ->willReturn($resultStub);

        $resultStub->expects(self::once())
            ->method('numRows')
            ->willReturn(1);

        $resultStub->expects(self::once())
            ->method('fetchAssoc')
            ->willReturn(['comment' => 'testComment']);

        DatabaseInterface::$instance = $dbi;
        $this->object->relation = new Relation($dbi);
        $GLOBALS['odt_buffer'] = '';
        $relationParameters = RelationParameters::fromArray([
            'relwork' => true,
            'commwork' => true,
            'mimework' => true,
            'db' => 'database',
            'relation' => 'rel',
            'column_info' => 'col',
        ]);
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, $relationParameters);

        self::assertTrue(
            $this->object->getTableDef(
                'database',
                '',
                true,
                true,
                true,
            ),
        );

        self::assertStringContainsString('<text:p>ftable (ffield)</text:p>', $GLOBALS['odt_buffer']);
    }

    public function testGetTriggers(): void
    {
        $triggers = [
            new Trigger(
                TriggerName::from('tna"me'),
                Timing::After,
                Event::Insert,
                TableName::from('ta<ble'),
                'def',
                'test_user@localhost',
            ),
        ];

        $method = new ReflectionMethod(ExportOdt::class, 'getTriggers');
        $result = $method->invoke($this->object, 'ta<ble', $triggers);

        self::assertSame($result, $GLOBALS['odt_buffer']);

        self::assertStringContainsString('<table:table table:name="ta&lt;ble_triggers">', $result);

        self::assertStringContainsString('<text:p>tna&quot;me</text:p>', $result);

        self::assertStringContainsString('<text:p>AFTER</text:p>', $result);

        self::assertStringContainsString('<text:p>INSERT</text:p>', $result);

        self::assertStringContainsString('<text:p>def</text:p>', $result);
    }

    public function testExportStructure(): void
    {
        // case 1
        $this->dummyDbi->addSelectDb('test_db');
        self::assertTrue(
            $this->object->exportStructure(
                'test_db',
                'test_table',
                'create_table',
                'test',
            ),
        );
        $this->dummyDbi->assertAllSelectsConsumed();

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" text:is-list-header="true">'
            . 'Table structure for table test_table</text:h><table:table table:name="test_table_structure">'
            . '<table:table-column table:number-columns-repeated="4"/><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>Column</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Type</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Null</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Default</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>id</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>int(11)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>name</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>varchar(20)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>datetimefield</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>datetime</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );

        // case 2
        $GLOBALS['odt_buffer'] = '';

        self::assertTrue(
            $this->object->exportStructure(
                'test_db',
                'test_table',
                'triggers',
                'test',
            ),
        );

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" text:is-list-header="true">'
            . 'Triggers test_table</text:h><table:table table:name="test_table_triggers">'
            . '<table:table-column table:number-columns-repeated="4"/><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>Name</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Time</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Event</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Definition</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>test_trigger</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>AFTER</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>INSERT</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>BEGIN END</text:p></table:table-cell>'
            . '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );

        // case 3
        $GLOBALS['odt_buffer'] = '';

        $this->dummyDbi->addSelectDb('test_db');
        self::assertTrue(
            $this->object->exportStructure(
                'test_db',
                'test_table',
                'create_view',
                'test',
            ),
        );
        $this->dummyDbi->assertAllSelectsConsumed();

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" text:is-list-header="true">'
            . 'Structure for view test_table</text:h><table:table table:name="test_table_structure">'
            . '<table:table-column table:number-columns-repeated="4"/><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>Column</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Type</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Null</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Default</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>id</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>int(11)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>name</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>varchar(20)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>datetimefield</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>datetime</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );

        // case 4
        $this->dummyDbi->addSelectDb('test_db');
        $GLOBALS['odt_buffer'] = '';
        self::assertTrue(
            $this->object->exportStructure(
                'test_db',
                'test_table',
                'stand_in',
                'test',
            ),
        );
        $this->dummyDbi->assertAllSelectsConsumed();

        self::assertEquals(
            '<text:h text:outline-level="2" text:style-name="Heading_2" text:is-list-header="true">'
            . 'Stand-in structure for view test_table</text:h><table:table table:name="test_table_data">'
            . '<table:table-column table:number-columns-repeated="4"/><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>Column</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Type</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Null</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>Default</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>id</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>int(11)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>name</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>varchar(20)</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row><table:table-row>'
            . '<table:table-cell office:value-type="string"><text:p>datetimefield</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>datetime</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>No</text:p></table:table-cell>'
            . '<table:table-cell office:value-type="string"><text:p>NULL</text:p></table:table-cell>'
            . '</table:table-row></table:table>',
            $GLOBALS['odt_buffer'],
        );
    }

    public function testFormatOneColumnDefinition(): void
    {
        $method = new ReflectionMethod(ExportOdt::class, 'formatOneColumnDefinition');

        $column = new Column('field', 'set(abc)enum123', true, 'PRI', null, '');

        $colAlias = 'alias';

        self::assertEquals(
            '<table:table-row><table:table-cell office:value-type="string">' .
            '<text:p>alias</text:p></table:table-cell><table:table-cell off' .
            'ice:value-type="string"><text:p>set(abc)</text:p></table:table' .
            '-cell><table:table-cell office:value-type="string"><text:p>Yes' .
            '</text:p></table:table-cell><table:table-cell office:value-typ' .
            'e="string"><text:p>NULL</text:p></table:table-cell>',
            $method->invoke($this->object, $column, $colAlias),
        );

        $column = new Column('fields', '', false, 'COMP', 'def', '');

        self::assertEquals(
            '<table:table-row><table:table-cell office:value-type="string">' .
            '<text:p>fields</text:p></table:table-cell><table:table-cell off' .
            'ice:value-type="string"><text:p>&amp;nbsp;</text:p></table:table' .
            '-cell><table:table-cell office:value-type="string"><text:p>No' .
            '</text:p></table:table-cell><table:table-cell office:value-type=' .
            '"string"><text:p>def</text:p></table:table-cell>',
            $method->invoke($this->object, $column, ''),
        );
    }
}
