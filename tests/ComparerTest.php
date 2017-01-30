<?php
namespace Czim\ModelComparer\Test;

use Carbon\Carbon;
use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PivotDifference;
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
    function it_compares_a_model_with_changed_belongs_to_many_connections()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestModel $model */
        $model = TestModel::first();

        $model->testRelatedAlphas()->sync([1, 2]);
        $model->load('testRelatedAlphas');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);


        $model->testRelatedAlphas()->sync([2, 3]);
        TestRelatedAlpha::find(2)->update([ 'name' => 'Changed Alpha Name' ]);
        $model = $model->fresh()->load('testRelatedAlphas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedAlphas'));
        $this->assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedAlphas']);
        $this->assertCount(3, $relation->related(), "There should be 3 related differences");

        $this->assertTrue($relation->related()->has(1));
        $this->assertInstanceOf(RelatedRemovedDifference::class, $object = $relation->related()->get(1));
        /** @var RelatedRemovedDifference $object */
        $this->assertEquals(1, $object->getKey(), "Related removed key does not match");
        $this->assertNull($object->getClass(), "Related removed class does not match");

        $this->assertTrue($relation->related()->has(3));
        $this->assertInstanceOf(RelatedAddedDifference::class, $object = $relation->related()->get(3));
        /** @var RelatedAddedDifference $object */
        $this->assertEquals(3, $object->getKey(), "Related added key does not match");
        $this->assertNull($object->getClass(), "Related added class does not match");
        $this->assertTrue($object->hasMessage(), "Related added should have a message");
        $this->assertRegExp('/#3/', $object->getMessage(), "Related added getMessage() is not as expected");

        $this->assertTrue($relation->related()->has(2));
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        $this->assertEquals(2, $object->getKey(), "Related changed key does not match");
        $this->assertNull($object->getClass(), "Related changed class does not match");
        $this->assertFalse($object->hasMessage(), "Related changed should not have a message");
        $this->assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        $this->assertTrue($object->isDifferent(), "Changed model should report different");
        $this->assertCount(1, $object->attributes(), "There should be 1 attribute change");
        $this->assertCount(0, $object->relations(), "There should be no relation changes");
    }


    // ------------------------------------------------------------------------------
    //      Relations with Pivot
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_model_with_changed_pivot_attributes_for_unchanged_belongs_to_many_connection()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedAlpha $model */
        $model = TestRelatedAlpha::first();

        $model->testRelatedBetas()->sync([
            1 => [ 'position' => 1, 'date' => Carbon::create(2017, 1, 1, 0, 0, 0) ],
            2 => [ 'position' => 2, 'date' => Carbon::create(2017, 1, 1, 0, 0, 0) ],
        ]);
        $model->load('testRelatedBetas');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);


        $model->testRelatedBetas()->sync([
            1,
            2 => [ 'position' => 5, 'date' => Carbon::create(2017, 3, 10, 0, 0, 0)]
        ]);
        $model = $model->fresh()->load('testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedBetas'));
        $this->assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedBetas']);
        $this->assertCount(1, $relation->related(), "There should be 1 related difference");

        $this->assertTrue($relation->related()->has(2));
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        $this->assertEquals(2, $object->getKey(), "Related changed key does not match");
        $this->assertNull($object->getClass(), "Related changed class does not match");
        $this->assertFalse($object->hasMessage(), "Related changed should not have a message");
        $this->assertInstanceOf(ModelDifference::class, $object->difference());
        $this->assertFalse($object->difference()->isDifferent(), "Changed model should not report different");
        $this->assertTrue($object->isPivotRelated());
        $this->assertInstanceOf(PivotDifference::class, $pivotDifference = $object->pivotDifference());
        /** @var PivotDifference $pivotDifference */
        $this->assertTrue($pivotDifference->isDifferent(), "Pivot attributes should be marked different");
        $this->assertCount(2, $pivotDifference->attributes(), "There should be 2 pivot attribute changes");


        /** @var AttributeDifference $object */
        $this->assertTrue($pivotDifference->attributes()->has('position'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('position'));
        $this->assertEquals(2, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(5, $object->after(), "Change value (after) incorrect");
        $this->assertFalse($object->didNotExistBefore());
        $this->assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        $this->assertTrue($pivotDifference->attributes()->has('date'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('date'));
        $this->assertEquals('2017-01-01 00:00:00', $object->before(), "Change value (before) incorrect");
        $this->assertEquals('2017-03-10 00:00:00', $object->after(), "Change value (after) incorrect");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_changed_pivot_attributes_for_unchanged_belongs_to_many_connection_with_model_change()
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedAlpha $model */
        $model = TestRelatedAlpha::first();

        $model->testRelatedBetas()->sync([
            1 => [ 'position' => 1, 'date' => Carbon::create(2017, 1, 1, 0, 0, 0) ],
            2 => [ 'position' => 2, 'date' => Carbon::create(2017, 1, 1, 0, 0, 0) ],
        ]);
        $model->load('testRelatedBetas');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);


        $model->testRelatedBetas()->sync([
            1,
            2 => [ 'position' => 5, 'date' => Carbon::create(2017, 3, 10, 0, 0, 0)]
        ]);
        TestRelatedBeta::find(2)->update([ 'name' => 'Changed Beta Name' ]);
        $model = $model->fresh()->load('testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertTrue($difference->isDifferent());
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedBetas'));
        $this->assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedBetas']);
        $this->assertCount(1, $relation->related(), "There should be 1 related difference");

        $this->assertTrue($relation->related()->has(2));
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        $this->assertEquals(2, $object->getKey(), "Related changed key does not match");
        $this->assertNull($object->getClass(), "Related changed class does not match");
        $this->assertFalse($object->hasMessage(), "Related changed should not have a message");
        $this->assertTrue($object->isPivotRelated());
        $this->assertInstanceOf(PivotDifference::class, $pivotDifference = $object->pivotDifference());
        $this->assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        $this->assertTrue($object->isDifferent(), "Changed model should report different");
        $this->assertCount(1, $object->attributes(), "There should be 1 attribute change");
        $this->assertCount(0, $object->relations(), "There should be no relation changes");
        $this->assertTrue($object->attributes()->has('name'), "'name' attribute should be marked changed");

        /** @var PivotDifference $pivotDifference */
        $this->assertTrue($pivotDifference->isDifferent(), "Pivot attributes should be marked different");
        $this->assertCount(2, $pivotDifference->attributes(), "There should be 2 pivot attribute changes");

        /** @var AttributeDifference $object */
        $this->assertTrue($pivotDifference->attributes()->has('position'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('position'));
        $this->assertEquals(2, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(5, $object->after(), "Change value (after) incorrect");
        $this->assertFalse($object->didNotExistBefore());
        $this->assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        $this->assertTrue($pivotDifference->attributes()->has('date'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('date'));
        $this->assertEquals('2017-01-01 00:00:00', $object->before(), "Change value (before) incorrect");
        $this->assertEquals('2017-03-10 00:00:00', $object->after(), "Change value (after) incorrect");
    }

    /**
     * @test
     */
    function it_compares_a_model_with_unchanged_pivot_attributes_for_unchanged_belongs_to_many_connection()
    {

    }

    // ------------------------------------------------------------------------------
    //      Limit nested lookups
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_can_be_configured_to_ignore_model_changes_for_models_of_specific_model_relations()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([1]);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        // Test
        $comparer = new Comparer();
        $comparer->setNestedCompareRelations(['testRelatedModel']);
        $comparer->setBeforeState($model);

        TestRelatedModel::find(1)->update(['name' => 'Unignored changed name']);
        TestRelatedAlpha::find(1)->update(['name' => 'Ignored changed name']);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertCount(1, $difference->relations(), "Only 1 relation should be changed");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));

        // Also test the reversed situation

        /** @var TestModel $model */
        $model->testRelatedAlphas()->sync([1]);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        // Test
        $comparer = new Comparer();
        $comparer->setNestedCompareRelations(['testRelatedAlphas']);
        $comparer->setBeforeState($model);

        TestRelatedModel::find(1)->update(['name' => 'Ignored changed name']);
        TestRelatedAlpha::find(1)->update(['name' => 'Unignored changed name']);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertCount(1, $difference->relations(), "Only 1 relation should be changed");
        $this->assertTrue($difference->relations()->has('testRelatedAlphas'));
    }
    

    // ------------------------------------------------------------------------------
    //      Ignored & 'not real' changes
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_ignores_timestamp_changes_by_default()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->created_at = $model->created_at->addDay();
        $model->updated_at = $model->updated_at->subDay();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
    }
    
    /**
     * @test
     */
    function it_tracks_timestamp_changes_if_configured_to()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = new Comparer();
        $comparer->ignoreTimestamps(false);
        $comparer->setBeforeState($model);

        $model->created_at = $model->created_at->addDay();
        $model->updated_at = $model->updated_at->subDay();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(2, $difference->attributes(), "There should be no attribute changes");
        $this->assertTrue($difference->attributes()->has('updated_at'));
        $this->assertTrue($difference->attributes()->has('created_at'));
    }

    /**
     * @test
     */
    function it_ignores_changes_that_are_detected_on_loosy_comparison_by_default()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->float = 0.0;
        $model->integer = 0;

        // Test
        $comparer = new Comparer();
        //$comparer->useStrictComparison(true);
        $comparer->setBeforeState($model);

        $model->float = null;
        $model->integer = null;
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
    }

    /**
     * @test
     */
    function it_tracks_changes_that_are_detected_on_loosy_comparison_if_configured_to()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->float = 0.0;
        $model->integer = 0;

        // Test
        $comparer = new Comparer();
        $comparer->useStrictComparison(true);
        $comparer->setBeforeState($model);

        $model->float = null;
        $model->integer = null;
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(2, $difference->attributes(), "There should be 2 attribute changes");
    }

    /**
     * @test
     */
    function it_allows_configuring_specific_attributes_to_ignore_per_model()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([1]);
        $model->load('testRelatedModel', 'testRelatedAlphas');

        // Test
        $comparer = new Comparer();

        $comparer->setIgnoredAttributesForModels([
            TestRelatedModel::class => [ 'name' ],
            TestRelatedAlpha::class => [ 'name' ],
        ]);
        $comparer->setIgnoredAttributesForModel(TestModel::class, [
            'float',
            'integer',
        ]);

        $comparer->setBeforeState($model);

        $model->float = 30.0;
        $model->integer = 15;
        $model->save();

        /** @var TestRelatedModel $relatedModel */
        $relatedModel = $model->testRelatedModel()->first();
        $relatedModel->name = 'Changed Test Name 2';
        $relatedModel->flag = true;
        $relatedModel->save();

        TestRelatedAlpha::find(1)->update(['name' => 'Should be ignored']);

        $model->load('testRelatedModel', 'testRelatedAlphas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(0, $difference->attributes(), "There should be no attribute changes");
        $this->assertCount(1, $difference->relations(), "There should be 1 relation change");
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        $this->assertTrue($object->difference()->isDifferent());
        $this->assertCount(1, $object->difference()->attributes(), "Only 1 attribute of testRelatedModel should be changed");
        $this->assertTrue($object->difference()->attributes()->has('flag'), "Only 'flag' attribute of testRelatedModel should be changed");
        $this->assertFalse($difference->relations()->has('testRelatedAlphas'));
    }


    // ------------------------------------------------------------------------------
    //      Special cases
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_compares_a_complex_of_deeply_changed_related_models()
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([1, 2]);
        TestRelatedAlpha::find(1)->testRelatedBetas()->sync([2]);
        TestRelatedAlpha::find(2)->testRelatedBetas()->sync([1, 3 => [ 'position' => 1 ]]);
        $model->load('testRelatedModel');
        $model->load('testRelatedAlphas.testRelatedBetas');

        // Test
        $comparer = new Comparer();
        $comparer->setBeforeState($model);

        $model->boolean = true;

        TestRelatedModel::find(1)->update(['name' => 'Changed!']);
        TestRelatedAlpha::find(1)->testRelatedBetas()->sync([1]);
        TestRelatedAlpha::find(2)->testRelatedBetas()->sync([
            1,
            3 => [ 'position' => 2, 'date' => Carbon::create(2017, 1, 1, 0, 0, 0)]
        ]);
        TestRelatedBeta::find(1)->update(['name' => 'Changed Beta!']);

        $model->load('testRelatedModel');
        $model->load('testRelatedAlphas.testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        $this->assertInstanceOf(ModelDifference::class, $difference);
        $this->assertCount(1, $difference->attributes(), "There should be 1 attribute change");
        $this->assertCount(2, $difference->relations(), "There should be 2 relation changes");

        /** @var AttributeDifference $object */
        $this->assertTrue($difference->attributes()->has('boolean'));
        $this->assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('boolean'));
        $this->assertEquals(false, $object->before(), "Change value (before) incorrect");
        $this->assertEquals(true, $object->after(), "Change value (after) incorrect");

        // TestRelatedModel
        /** @var SingleRelationDifference $object */
        $this->assertTrue($difference->relations()->has('testRelatedModel'));
        $this->assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()->get('testRelatedModel'));
        $this->assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        $this->assertTrue($object->difference()->isDifferent(), "TestRelatedModel should be marked different");
        $this->assertCount(1, $object->difference()->attributes(), "TestRelatedModel should have 1 changed attribute");
        $this->assertTrue($object->difference()->attributes()->has('name'), "TestRelatedModel should have changed 'name' attribute");

        /** @var PluralRelationDifference $object */
        $this->assertTrue($difference->relations()->has('testRelatedAlphas'));
        $this->assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()->get('testRelatedAlphas'));
        $this->assertCount(2, $object->related(), "testRelatedAlphas should have 2 related changes");

        // TestRelatedAlphas
        $this->assertTrue($object->related()->has(1), "testRelatedAlphas key 1 should be present");
        $this->assertTrue($object->related()->has(2), "testRelatedAlphas key 2 should be present");
        $first  = $object->related()->get(1);
        $second = $object->related()->get(2);

        // TestRelatedAlpha: 1
        $this->assertInstanceOf(RelatedChangedDifference::class, $first);
        /** @var RelatedChangedDifference $first */
        $this->assertTrue($first->difference()->isDifferent());
        $this->assertCount(0, $first->difference()->attributes());
        $this->assertCount(1, $first->difference()->relations());
        $this->assertTrue($first->difference()->relations()->has('testRelatedBetas'));

        $this->assertTrue($first->difference()->relations()->get('testRelatedBetas')->related()->has(1));
        $this->assertInstanceof(
            RelatedAddedDifference::class,
            $object = $first->difference()->relations()->get('testRelatedBetas')->related()->get(1)
        );
        /** @var RelatedAddedDifference $object */
        $this->assertEquals($object->getKey(), 1);

        $this->assertTrue($first->difference()->relations()->get('testRelatedBetas')->related()->has(2));
        $this->assertInstanceof(
            RelatedRemovedDifference::class,
            $object = $first->difference()->relations()->get('testRelatedBetas')->related()->get(2)
        );
        /** @var RelatedRemovedDifference $object */
        $this->assertEquals($object->getKey(), 2);

        // TestRelatedAlpha: 2
        $this->assertInstanceOf(RelatedChangedDifference::class, $second);
        /** @var RelatedChangedDifference $second */
        $this->assertTrue($second->difference()->isDifferent());

        $this->assertTrue($second->difference()->relations()->get('testRelatedBetas')->related()->has(1));
        $this->assertInstanceof(
            RelatedChangedDifference::class,
            $object = $second->difference()->relations()->get('testRelatedBetas')->related()->get(1)
        );
        /** @var RelatedChangedDifference $object */
        $this->assertEquals($object->getKey(), 1);
        $this->assertFalse($object->pivotDifference()->isDifferent());
        $this->assertTrue($object->difference()->isDifferent());
        $this->assertCount(1, $object->difference()->attributes());
        $this->assertTrue($object->difference()->attributes()->has('name'));

        $this->assertTrue($second->difference()->relations()->get('testRelatedBetas')->related()->has(3));
        $this->assertInstanceof(
            RelatedChangedDifference::class,
            $object = $second->difference()->relations()->get('testRelatedBetas')->related()->get(3)
        );
        /** @var RelatedChangedDifference $object */
        $this->assertEquals($object->getKey(), 3);
        $this->assertFalse($object->difference()->isDifferent());
        $this->assertTrue($object->pivotDifference()->isDifferent());
        $this->assertCount(2, $object->pivotDifference()->attributes());
        $this->assertTrue($object->pivotDifference()->attributes()->has('position'));
        $this->assertTrue($object->pivotDifference()->attributes()->has('date'));
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
