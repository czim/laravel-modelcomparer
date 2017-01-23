<?php
namespace Czim\ModelComparer\Test;

use Illuminate\Support\Facades\Schema;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    const TABLE_NAME_SIMPLE  = 'test_models';
    const TABLE_NAME_RELATED = 'test_related_models';


    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }


    public function setUp()
    {
        parent::setUp();

        $this->migrateDatabase();
    }


    protected function migrateDatabase()
    {
        Schema::create(self::TABLE_NAME_SIMPLE, function($table) {
            $table->increments('id');
            $table->string('name', 255)->nullable();
            $table->integer('integer')->unsigned()->nullable();
            $table->decimal('float', 11, 3)->nullable();
            $table->boolean('boolean')->nullable();
            $table->text('text')->nullable();
            $table->integer('test_related_model_id')->nullable()->unsigned();
            $table->timestamps();
        });

        Schema::create(self::TABLE_NAME_RELATED, function($table) {
            $table->increments('id');
            $table->string('name', 255)->nullable();
            $table->timestamps();
        });
    }

}
