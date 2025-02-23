<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Table;

use PhpMyAdmin\ColumnFull;
use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Controllers\Table\SearchController;
use PhpMyAdmin\Core;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\DbTableExists;
use PhpMyAdmin\Table\Search;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\FieldHelper;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer as ResponseStub;
use PhpMyAdmin\Types;
use PHPUnit\Framework\Attributes\CoversClass;

use function hash;

use const MYSQLI_TYPE_LONG;

#[CoversClass(SearchController::class)]
class SearchControllerTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    private ResponseStub $response;

    private Template $template;

    /**
     * Setup function for test cases
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;

        /**
         * SET these to avoid undefined index error
         */
        $_POST['zoom_submit'] = 'zoom';

        Current::$database = 'PMA';
        Current::$table = 'PMA_BookMark';
        Config::getInstance()->selectedServer['DisableIS'] = false;

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->types = new Types($dbi);

        $columns = [
            new ColumnFull('Field1', 'Type1', 'Collation1', false, '', null, '', '', ''),
            new ColumnFull('Field2', 'Type2', 'Collation2', false, '', null, '', '', ''),
        ];
        $dbi->expects($this->any())->method('getColumns')
            ->willReturn($columns);

        $showCreateTable = "CREATE TABLE `pma_bookmark` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `dbase` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
        `user` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
        `label` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
        `query` text COLLATE utf8_bin NOT NULL,
        PRIMARY KEY (`id`),
        KEY `foreign_field` (`foreign_db`,`foreign_table`)
        ) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_bin "
        . "COMMENT='Bookmarks'";

        $dbi->expects($this->any())->method('fetchValue')
            ->willReturn($showCreateTable);

        DatabaseInterface::$instance = $dbi;

        $this->response = new ResponseStub();
        $this->template = new Template();
    }

    /**
     * Tests for getColumnMinMax()
     */
    public function testGetColumnMinMax(): void
    {
        $expected = 'SELECT MIN(`column`) AS `min`, MAX(`column`) AS `max` FROM `PMA`.`PMA_BookMark`';

        $dbi = DatabaseInterface::getInstance();
        $dbi->expects($this->any())
            ->method('fetchSingleRow')
            ->with($expected)
            ->willReturn([$expected]);

        $ctrl = new SearchController(
            $this->response,
            $this->template,
            new Search($dbi),
            new Relation($dbi),
            $dbi,
            new DbTableExists($dbi),
        );

        $result = $ctrl->getColumnMinMax('column');
        $this->assertEquals([$expected], $result);
    }

    /**
     * Tests for getDataRowAction()
     */
    public function testGetDataRowAction(): void
    {
        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;

        $_SESSION[' HMAC_secret '] = hash('sha1', 'test');

        $this->dummyDbi->addResult(
            'SHOW FULL COLUMNS FROM `PMA`.`PMA_BookMark`',
            [],
        );

        $this->dummyDbi->addResult(
            'SHOW CREATE TABLE `PMA`.`PMA_BookMark`',
            [],
        );

        $this->dummyDbi->addResult(
            'SELECT * FROM `PMA`.`PMA_BookMark` WHERE `col1` = 1;',
            [[1, 2]],
            ['col1', 'col2'],
            [
                FieldHelper::fromArray(['type' => MYSQLI_TYPE_LONG, 'length' => 11]),
                FieldHelper::fromArray(['type' => MYSQLI_TYPE_LONG, 'length' => 11]),
            ],
        );

        $ctrl = new SearchController(
            $this->response,
            $this->template,
            new Search($this->dbi),
            new Relation($this->dbi),
            $this->dbi,
            new DbTableExists($this->dbi),
        );

        $_POST['db'] = 'PMA';
        $_POST['table'] = 'PMA_BookMark';
        $_POST['where_clause'] = '`col1` = 1';
        $_POST['where_clause_sign'] = Core::signSqlQuery($_POST['where_clause']);
        $expected = ['col1' => 1, 'col2' => 2];
        $ctrl->getDataRowAction();

        $json = $this->response->getJSONResult();
        $this->assertEquals($expected, $json['row_info']);
    }
}
