<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Sql;

use PhpMyAdmin\Bookmarks\BookmarkRepository;
use PhpMyAdmin\CheckUserPrivileges;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationCleanup;
use PhpMyAdmin\Controllers\Sql\EnumValuesController;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Operations;
use PhpMyAdmin\Sql;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PhpMyAdmin\Transformations;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EnumValuesController::class)]
class EnumValuesControllerTest extends AbstractTestCase
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

    public function testGetEnumValuesError(): void
    {
        $this->dummyDbi->addResult('SHOW COLUMNS FROM `cvv`.`enums` LIKE \'set\'', false);
        $this->dummyDbi->addResult('SHOW INDEXES FROM `cvv`.`enums`', false);

        Current::$database = 'cvv';
        Current::$table = 'enums';

        $request = $this->createStub(ServerRequest::class);
        $request->method('getParsedBodyParam')->willReturnMap([
            ['db', null, 'cvv'],
            ['table', null, 'enums'],
            ['column', null, 'set'],
            ['curr_value', null, 'b&c'],
        ]);

        $responseRenderer = new ResponseRenderer();
        $template = new Template();
        $relation = new Relation($this->dbi);
        $bookmarkRepository = new BookmarkRepository($this->dbi, $relation);
        $sql = new Sql(
            $this->dbi,
            $relation,
            self::createStub(RelationCleanup::class),
            self::createStub(Operations::class),
            self::createStub(Transformations::class),
            $template,
            $bookmarkRepository,
        );

        $sqlController = new EnumValuesController(
            $responseRenderer,
            $template,
            $sql,
            new CheckUserPrivileges($this->dbi),
        );
        $sqlController($request);

        $this->assertFalse($responseRenderer->hasSuccessState(), 'expected the request to fail');

        $this->assertSame(['message' => 'Error in processing request'], $responseRenderer->getJSONResult());
    }

    public function testGetEnumValuesSuccess(): void
    {
        $this->dummyDbi->addResult(
            'SHOW COLUMNS FROM `cvv`.`enums` LIKE \'set\'',
            [
                [
                    'set',
                    "set('<script>alert(\"ok\")</script>','a&b','b&c','vrai&amp','','漢字','''','\\\\','\"\\\\''')",
                    'No',
                    '',
                    'NULL',
                    '',
                ],
            ],
            ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'],
        );
        $this->dummyDbi->addResult('SHOW INDEXES FROM `cvv`.`enums`', []);

        Current::$database = 'cvv';
        Current::$table = 'enums';

        $request = $this->createStub(ServerRequest::class);
        $request->method('getParsedBodyParam')->willReturnMap([
            ['db', null, 'cvv'],
            ['table', null, 'enums'],
            ['column', null, 'set'],
            ['curr_value', null, 'b&c'],
        ]);

        $responseRenderer = new ResponseRenderer();
        $template = new Template();
        $relation = new Relation($this->dbi);
        $bookmarkRepository = new BookmarkRepository($this->dbi, $relation);
        $sql = new Sql(
            $this->dbi,
            $relation,
            self::createStub(RelationCleanup::class),
            self::createStub(Operations::class),
            self::createStub(Transformations::class),
            $template,
            $bookmarkRepository,
        );

        $sqlController = new EnumValuesController(
            $responseRenderer,
            $template,
            $sql,
            new CheckUserPrivileges($this->dbi),
        );
        $sqlController($request);

        $this->assertTrue($responseRenderer->hasSuccessState(), 'expected the request not to fail');

        $this->assertSame(
            [
                'dropdown' => '<select>' . "\n"
                    . '      <option value="&lt;script&gt;alert(&quot;ok&quot;)&lt;/script&gt;">'
                    . '&lt;script&gt;alert(&quot;ok&quot;)&lt;/script&gt;</option>' . "\n"
                    . '      <option value="a&amp;b">a&amp;b</option>' . "\n"
                    . '      <option value="b&amp;c" selected>b&amp;c</option>' . "\n"
                    . '      <option value="vrai&amp;amp">vrai&amp;amp</option>' . "\n"
                    . '      <option value=""></option>' . "\n"
                    . '      <option value="漢字">漢字</option>' . "\n"
                    . '      <option value="&#039;">&#039;</option>' . "\n"
                    . '      <option value="\">\</option>' . "\n"
                    . '      <option value="&quot;\&#039;">&quot;\&#039;</option>' . "\n"
                    . '  </select>' . "\n",
            ],
            $responseRenderer->getJSONResult(),
        );
    }
}
