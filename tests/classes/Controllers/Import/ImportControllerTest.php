<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Import;

use PhpMyAdmin\Bookmarks\BookmarkRepository;
use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationCleanup;
use PhpMyAdmin\Controllers\Import\ImportController;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Import\Import;
use PhpMyAdmin\Operations;
use PhpMyAdmin\Sql;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PhpMyAdmin\Transformations;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImportController::class)]
class ImportControllerTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;
    }

    public function testIndexParametrized(): void
    {
        parent::setLanguage();

        Config::getInstance()->selectedServer['user'] = 'user';

        // Some params were not added as they are not required for this test
        Current::$database = 'pma_test';
        Current::$table = 'table1';
        $GLOBALS['sql_query'] = 'SELECT A.*' . "\n"
            . 'FROM table1 A' . "\n"
            . 'WHERE A.nomEtablissement = :nomEta AND foo = :1 AND `:a` IS NULL';

        $request = $this->createStub(ServerRequest::class);
        $request->method('getParsedBodyParam')->willReturnMap([
            ['db', null, Current::$database],
            ['table', null, Current::$table],
            ['parameters', null, [':nomEta' => 'Saint-Louis - Châteaulin', ':1' => '4']],
            ['sql_query', null, $GLOBALS['sql_query']],
        ]);
        $request->method('hasBodyParam')->willReturnMap([
            ['parameterized', true],
            ['rollback_query', false],
            ['allow_interrupt', false],
            ['skip', false],
        ]);

        $this->dummyDbi->addResult(
            'SELECT A.* FROM table1 A WHERE A.nomEtablissement = \'Saint-Louis - Châteaulin\''
            . ' AND foo = 4 AND `:a` IS NULL LIMIT 0, 25',
            [],
            ['nomEtablissement', 'foo'],
        );

        $this->dummyDbi->addResult(
            'SHOW CREATE TABLE `pma_test`.`table1`',
            [],
        );

        $this->dummyDbi->addResult(
            'SHOW FULL COLUMNS FROM `pma_test`.`table1`',
            [],
        );

        $responseRenderer = new ResponseRenderer();
        $relation = new Relation($this->dbi);
        $bookmarkRepository = new BookmarkRepository($this->dbi, $relation);
        $template = new Template();
        $sql = new Sql(
            $this->dbi,
            $relation,
            self::createStub(RelationCleanup::class),
            self::createStub(Operations::class),
            self::createStub(Transformations::class),
            $template,
            $bookmarkRepository,
        );

        $importController = new ImportController(
            $responseRenderer,
            $template,
            new Import(),
            $sql,
            $this->dbi,
            $bookmarkRepository,
        );

        $this->dummyDbi->addSelectDb('pma_test');
        $this->dummyDbi->addSelectDb('pma_test');
        $importController($request);
        $this->dummyDbi->assertAllSelectsConsumed();
        self::assertTrue($responseRenderer->hasSuccessState(), 'expected the request not to fail');

        $output = $responseRenderer->getHTMLResult();

        $this->assertStringContainsString('MySQL returned an empty result set (i.e. zero rows).', $output);

        $this->assertStringContainsString(
            'SELECT A.*' . "\n" . 'FROM table1 A' . "\n"
                . 'WHERE A.nomEtablissement = \'Saint-Louis - Châteaulin\' AND foo = 4 AND `:a` IS NULL',
            $output,
        );

        $this->dummyDbi->assertAllQueriesConsumed();
    }
}
