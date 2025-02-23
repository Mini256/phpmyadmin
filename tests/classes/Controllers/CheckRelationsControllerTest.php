<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers;

use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Controllers\CheckRelationsController;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

#[CoversClass(CheckRelationsController::class)]
class CheckRelationsControllerTest extends AbstractTestCase
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

    public function testCheckRelationsController(): void
    {
        Current::$database = '';
        Current::$table = '';

        $request = $this->createStub(ServerRequest::class);
        $request->method('getParsedBodyParam')->willReturnMap([
            ['create_pmadb', null, null],
            ['fixall_pmadb', null, null],
            ['fix_pmadb', null, null],
        ]);

        $response = new ResponseRenderer();
        Config::getInstance()->selectedServer['pmadb'] = '';
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, null);
        $controller = new CheckRelationsController($response, new Template(), new Relation($this->dbi));
        $controller($request);

        $actual = $response->getHTMLResult();

        $this->assertStringContainsString('phpMyAdmin configuration storage', $actual);
        $this->assertStringContainsString(
            'Configuration of pmadb…      <span class="text-danger"><strong>not OK</strong></span>',
            $actual,
        );
        $this->assertStringContainsString(
            'Create</a> a database named \'phpmyadmin\' and setup the phpMyAdmin configuration storage there.',
            $actual,
        );
    }
}
