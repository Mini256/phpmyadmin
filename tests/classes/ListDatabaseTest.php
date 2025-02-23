<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\Config;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\ListDatabase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListDatabase::class)]
class ListDatabaseTest extends AbstractTestCase
{
    /**
     * ListDatabase instance
     */
    private ListDatabase $object;

    /**
     * SetUp for test cases
     */
    protected function setUp(): void
    {
        parent::setUp();

        DatabaseInterface::$instance = $this->createDatabaseInterface();
        $config = Config::getInstance();
        $config->selectedServer['DisableIS'] = false;
        $config->selectedServer['only_db'] = ['single\\_db'];
        $this->object = new ListDatabase();
    }

    /**
     * Test for ListDatabase::getDefault
     */
    public function testEmpty(): void
    {
        $arr = new ListDatabase();
        $this->assertEquals('', $arr->getDefault());
    }

    /**
     * Test for ListDatabase::exists
     */
    public function testExists(): void
    {
        $arr = new ListDatabase();
        $this->assertTrue($arr->exists('single_db'));
    }

    public function testGetList(): void
    {
        $arr = new ListDatabase();

        Current::$database = 'db';
        $this->assertEquals(
            [['name' => 'single_db', 'is_selected' => false]],
            $arr->getList(),
        );

        Current::$database = 'single_db';
        $this->assertEquals(
            [['name' => 'single_db', 'is_selected' => true]],
            $arr->getList(),
        );
    }

    /**
     * Test for checkHideDatabase
     */
    public function testCheckHideDatabase(): void
    {
        Config::getInstance()->selectedServer['hide_db'] = 'single\\_db';
        $this->assertEquals(
            $this->callFunction(
                $this->object,
                ListDatabase::class,
                'checkHideDatabase',
                [],
            ),
            '',
        );
    }

    /**
     * Test for getDefault
     */
    public function testGetDefault(): void
    {
        Current::$database = '';
        $this->assertEquals(
            $this->object->getDefault(),
            '',
        );

        Current::$database = 'mysql';
        $this->assertEquals(
            $this->object->getDefault(),
            'mysql',
        );
    }
}
