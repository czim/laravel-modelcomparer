<?php
namespace Czim\ModelComparer\Test;

use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PluralRelationDifference;
use Czim\ModelComparer\Data\RelatedAddedDifference;
use Czim\ModelComparer\Data\RelatedChangedDifference;
use Czim\ModelComparer\Data\RelatedRemovedDifference;
use Czim\ModelComparer\Data\RelatedReplacedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;
use Czim\ModelComparer\Test\Helpers\TestModel;
use Czim\ModelComparer\Test\Helpers\TestRelatedAlpha;
use Czim\ModelComparer\Test\Helpers\TestRelatedBeta;
use Czim\ModelComparer\Test\Helpers\TestRelatedModel;

class ComparerTest extends TestCase
{

    // ------------------------------------------------------------------------------
    //      Simple comparison
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_model_with_no_changes_at_all()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertFalse($difference->isDifferent());
        $this->assertEmpty($difference->toArray());
    }

    /**
     * @test
     */
    function it_compares_a_model_with_only_attribute_changes()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->integer = 5;
        $model->float   = 5.3;
        $model->name    = 'Test Name After';
        $model->boolean = true;

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(4, $difference->attributes(), "There should be 4 attribute changes");
        $this->assertCount(0, $difference->relations(), "There should be no relation changes");

        /** @var AttributeDifference $object */
        $this->assertTrue($difference->attributes()->has('integer'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('integer'));
        $this->assertEquals(10, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(5, $object->after(), "Change value (after) incorrect");
        $this->assertFalse($object->didNotExistBefore());
        $this->assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        $this->assertTrue($difference->attributes()->has('float'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('float'));
        $this->assertEquals(0.5, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(5.3, $object->after(), "Change value (after) incorrect");

        /** @var AttributeDifference $object */
        $this->assertTrue($difference->attributes()->has('name'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('name'));
        $this->assertEquals('Test Name Before', $object->before(), "Change value (before) incorrect");
        $this->assertEquals('Test Name After', $object->after(), "Change value (after) incorrect");

        /** @var AttributeDifference $object */
        $this->assertTrue($difference->attributes()->has('boolean'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('boolean'));
        $this->assertEquals(false, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(true, $object->after(), "Change value (after) incorrect");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_only_relation_changes()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);


        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);

        // Contents of the single relation difference are not tested here, just the relation-only aspect
    }

    /**
     * @test
     */
    function it_compares_a_model_with_both_attribute_and_relation_changes()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->integer = 5;
        $model->float   = 5.3;

        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);

        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(2, $difference->attributes(), "There should be 2 attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->attributes()->has('integer'));
        $this->assertTrue($difference->attributes()->has('float'));
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
    }
    
    
    // ------------------------------------------------------------------------------
    //      Relations: Singular (BelongsTo)
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_model_with_a_removed_belongs_to_relation()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        $this->assertFalse($object->isPlural(), "Should be singular relation");
        $this->assertFalse($object->hasVariableModelClass(), "Should not have variable model class");
        $this->assertTrue($object->hasMessage(), "hasMessage() should be true");
        $this->assertRegExp('/#1/', $object->getMessage(), "getMessage() is not as expected");
        $this->assertInstanceOf(RelatedRemovedDifference::class, $object = $object->difference());
        /** @var RelatedRemovedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Removed key should be 1");
        $this->assertNull($object->getClass(), "Removed class should be null");
    }
    
    /**
     * @test
     */
    function it_compares_a_model_with_an_added_belongs_to_relation()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        $model->testRelatedModel()->dissociate();
        $model->save();

        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->associate(TestRelatedModel::first());
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        $this->assertFalse($object->isPlural(), "Should be singular relation");
        $this->assertFalse($object->hasVariableModelClass(), "Should not have variable model class");
        $this->assertTrue($object->hasMessage(), "hasMessage() should be true");
        $this->assertRegExp('/#1/', $object->getMessage(), "getMessage() is not as expected");
        $this->assertInstanceOf(RelatedAddedDifference::class, $object = $object->difference());
        /** @var RelatedAddedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Removed key should be 1");
        $this->assertNull($object->getClass(), "Removed class should be null");
    }
    
    /**
     * @test
     */
    function it_compares_a_model_with_a_replaced_belongs_to_relation()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->associate(TestRelatedModel::orderBy('id', 'desc')->first());
        $model->save();

        $model->load('testRelatedModel');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        $this->assertFalse($object->isPlural(), "Should be singular relation");
        $this->assertFalse($object->hasVariableModelClass(), "Should not have variable model class");
        $this->assertTrue($object->hasMessage(), "hasMessage() should be true");
        $this->assertRegExp('/#1/', $object->getMessage(), "getMessage() is not as expected");
        $this->assertInstanceOf(RelatedReplacedDifference::class, $object = $object->difference());
        /** @var RelatedReplacedDifference $object */
        $this->assertEquals(2, $object->getKey(), "New key should be 1");
        $this->assertEquals(1, $object->getKeyBefore(), "Removed key should be 1");
        $this->assertNull($object->getClass(), "New class should be null");
        $this->assertNull($object->getClassBefore(), "Removed class should be null");
    }
    
    /**
     * @test
     */
    function it_compares_a_model_with_the_same_belongs_to_relation_with_changes_to_the_related_model()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        /** @var TestRelatedModel $relatedModel */
        $relatedModel = $model->testRelatedModel()->first();
        $relatedModel->name = 'Changed Test Name 2';
        $relatedModel->save();

        $model->load('testRelatedModel');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        $this->assertFalse($object->isPlural(), "Should be singular relation");
        $this->assertFalse($object->hasVariableModelClass(), "Should not have variable model class");
        $this->assertFalse($object->hasMessage(), "hasMessage() should be false");
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Related key should be 1");
        $this->assertNull($object->getClass(), "Related class should be null");
        $this->assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        $this->assertTrue($object->isDifferent(), "Changed model should report different");
        $this->assertCount(1, $object->attributes(), "There should be 1 attribute change");
        $this->assertCount(0, $object->relations(), "There should be no relation changes");
    }


    // ------------------------------------------------------------------------------
    //      Relations: Plural (HasMany / BelongsToMany)
    // ------------------------------------------------------------------------------
    
    /**
     * Remove one, keep one the same
     *
     * @test
     */
    function it_compares_a_model_with_relation_changes_for_has_many_relation_with_removed_related_record()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedModel $model */
        $model = TestRelatedModel::first()->load('testModels');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->testModels()->first()->testRelatedModel()->dissociate()->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testModels'));
        $this->assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        $this->assertCount(1, $object->related());
        $this->assertInstanceOf(RelatedRemovedDifference::class, $object = $object->related()->first());
        /** @var RelatedRemovedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Removed key should be 1");
        $this->assertNull($object->getClass(), "Removed class should be null");
    }

    /**
     * Add one, keep existing related the same
     *
     * @test
     */
    function it_compares_a_model_with_relation_changes_for_has_many_relation_with_added_related_record()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedModel $model */
        /** @var TestModel $removedModel */
        $model = TestRelatedModel::first();
        $removedModel = $model->testModels()->first();
        $removedModel->testRelatedModel()->dissociate()->save();
        $model->load('testModels');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $removedModel->testRelatedModel()->associate($model->id)->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testModels'));
        $this->assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        $this->assertCount(1, $object->related());
        $this->assertInstanceOf(RelatedAddedDifference::class, $object = $object->related()->first());
        /** @var RelatedAddedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Added key should be 1");
        $this->assertNull($object->getClass(), "Added class should be null");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_relation_changes_for_has_many_relation_with_changed_related_record()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedModel $model */
        $model = TestRelatedModel::first()->load('testModels');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        /** @var TestRelatedModel $related */
        $related = $model->testModels()->get()->last();
        $related->name = 'Changed Related Name';
        $related->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testModels'));
        $this->assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        $this->assertCount(1, $object->related());
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $object->related()->first());
        /** @var RelatedChangedDifference $object */
        $this->assertEquals(2, $object->getKey(), "Changed model key should be 2");
        $this->assertNull($object->getClass(), "Changed model class should be null");
        $this->assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        $this->assertTrue($object->isDifferent(), "Changed model should report different");
        $this->assertCount(1, $object->attributes(), "There should be 1 attribute change");
        $this->assertCount(0, $object->relations(), "There should be no relation changes");
    }

    /**
     * @test
     */
    {

    }


    // ------------------------------------------------------------------------------
    //      Helper methods
    // ------------------------------------------------------------------------------

    /**
     * Sets up a simple 'before' state for basic tests.
     */
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
            'name' => 'Test Related Name A',
        ]);
        $model->testRelatedModel()->associate($relatedModel);
        $model->save();


        $model = TestModel::forceCreate([
            'name'    => 'Test Name Two',
            'text'    => 'Test Text Lorum Ipsum',
            'integer' => 5,
            'float'   => 0.35,
            'boolean' => false,
        ]);
        $model->testRelatedModel()->associate($relatedModel);
        $model->save();


        // Extra available for new connections
        TestRelatedModel::forceCreate([
            'name' => 'Test Related Name B',
        ]);

        TestRelatedAlpha::forceCreate([ 'name' => 'Alpha 1' ]);
        TestRelatedAlpha::forceCreate([ 'name' => 'Alpha 2' ]);
        TestRelatedAlpha::forceCreate([ 'name' => 'Alpha 3' ]);

        TestRelatedBeta::forceCreate([ 'name' => 'Beta 1' ]);
        TestRelatedBeta::forceCreate([ 'name' => 'Beta 2' ]);
        TestRelatedBeta::forceCreate([ 'name' => 'Beta 3' ]);
    }

}
