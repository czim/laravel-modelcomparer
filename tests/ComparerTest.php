<?php
namespace Czim\ModelComparer\Test;

use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Test\Helpers\TestModel;
use Czim\ModelComparer\Test\Helpers\TestRelatedModel;

class ComparerTest extends TestCase
{

    // ------------------------------------------------------------------------------
    //      Simple comparisons
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_model_with_no_changes_at_all()
    {
        // Set up and retrieve the before state
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Prepare the comparer
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $difference = $comparer->compareWithBefore($model);

        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertFalse($difference->isDifferent());
        $this->assertEmpty($difference->toArray());
    }

    /**
     * @test
     */
    function it_compares_a_model_with_only_attribute_changes()
    {
        // Set up and retrieve the before state
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Prepare the comparer
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        // Make changes
        $model->integer = 5;
        $model->float   = 5.3;
        $model->name    = 'Test Name After';
        $model->boolean = true;

        $difference = $comparer->compareWithBefore($model);

        $this->assertInstanceOf(ModelDifference::class, $difference);

        $array = $difference->toArray();

        $this->assertCount(1, $array, "Only the 'attributes' key should be set");
        $this->assertArrayHasKey('attributes', $array, "The 'attributes' key should be set");

        $this->assertCount(4, $array['attributes'], "Attribute changes count is incorrect");
        $this->assertArrayHasKey('integer', $array['attributes'], "Attribute changes should include the key");
        $this->assertArrayHasKey('float', $array['attributes'], "Attribute changes should include the key");
        $this->assertArrayHasKey('name', $array['attributes'], "Attribute changes should include the key");
        $this->assertArrayHasKey('boolean', $array['attributes'], "Attribute changes should include the key");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_only_relation_changes()
    {
        // Set up and retrieve the before state
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Prepare the comparer
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        // Make changes
        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        $this->assertInstanceOf(ModelDifference::class, $difference);

        $array = $difference->toArray();

        $this->assertCount(1, $array, "Only the 'relations' key should be set");
        $this->assertArrayHasKey('relations', $array, "The 'relations' key should be set");

        $this->assertCount(1, $array['relations'], "Relation changes count is incorrect");
        $this->assertArrayHasKey('test_related_model', $array['relations'], "Relation changes should include the key");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_both_attribute_and_relation_changes()
    {
        // Set up and retrieve the before state
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Prepare the comparer
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        // Make changes
        $model->integer = 5;
        $model->float   = 5.3;

        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        $this->assertInstanceOf(ModelDifference::class, $difference);

        $array = $difference->toArray();

        $this->assertCount(2, $array, "'attributes' and 'relations' keys should be set");
        $this->assertArrayHasKey('attributes', $array, "The 'attributes' key should be set");
        $this->assertArrayHasKey('relations', $array, "The 'relations' key should be set");
    }


    // ------------------------------------------------------------------------------
    //      Related nested changes
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_model_with_an_unchanged_relation_with_changes_to_the_related_model()
    {

    }


    // ------------------------------------------------------------------------------
    //      Helper methods
    // ------------------------------------------------------------------------------

    protected function setUpSimpleBeforeState()
    {
        $model = TestModel::forceCreate([
            'name'    => 'Test Name Before',
            'text'    => 'Test Text Before',
            'integer' => 10,
            'float'   => 0.5,
            'boolean' => false,
        ]);

        $relatedModel = TestRelatedModel::forceCreate([
            'name' => 'Test Related Name',
        ]);

        $model->testRelatedModel()->associate($relatedModel);
        $model->save();
    }
}
