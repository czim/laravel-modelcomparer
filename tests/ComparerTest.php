<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Test;

use Carbon\Carbon;
use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\ModelCreatedDifference;
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
    public function it_compares_a_model_with_no_changes_at_all(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertFalse($difference->isDifferent());
        static::assertEmpty($difference->toArray());
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_only_attribute_changes(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->integer = 5;
        $model->float   = 5.3;
        $model->name    = 'Test Name After';
        $model->boolean = true;

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(4, $difference->attributes(), 'There should be 4 attribute changes');
        static::assertCount(0, $difference->relations(), 'There should be no relation changes');

        /** @var AttributeDifference $object */
        static::assertTrue($difference->attributes()->has('integer'));
        static::assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('integer'));
        static::assertEquals(10, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(5, $object->after(), 'Change value (after) incorrect');
        static::assertFalse($object->didNotExistBefore());
        static::assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        static::assertTrue($difference->attributes()->has('float'));
        static::assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('float'));
        static::assertEquals(0.5, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(5.3, $object->after(), 'Change value (after) incorrect');

        /** @var AttributeDifference $object */
        static::assertTrue($difference->attributes()->has('name'));
        static::assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('name'));
        static::assertEquals('Test Name Before', $object->before(), 'Change value (before) incorrect');
        static::assertEquals('Test Name After', $object->after(), 'Change value (after) incorrect');

        /** @var AttributeDifference $object */
        static::assertTrue($difference->attributes()->has('boolean'));
        static::assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('boolean'));
        static::assertEquals(false, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(true, $object->after(), 'Change value (after) incorrect');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_only_relation_changes(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);


        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);

        // Contents of the single relation difference are not tested here, just the relation-only aspect
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_both_attribute_and_relation_changes(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->integer = 5;
        $model->float   = 5.3;

        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(2, $difference->attributes(), 'There should be 2 attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->attributes()->has('integer'));
        static::assertTrue($difference->attributes()->has('float'));
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
    }


    // ------------------------------------------------------------------------------
    //      Relations: Singular (BelongsTo)
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_compares_a_model_with_a_removed_belongs_to_relation(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->dissociate();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertFalse($object->isPlural(), 'Should be singular relation');
        static::assertFalse($object->hasVariableModelClass(), 'Should not have variable model class');
        static::assertTrue($object->hasMessage(), 'hasMessage() should be true');
        static::assertMatchesRegularExpression('/#1/', $object->getMessage(), 'getMessage() is not as expected');
        static::assertInstanceOf(RelatedRemovedDifference::class, $object = $object->difference());
        /** @var RelatedRemovedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Removed key should be 1');
        static::assertNull($object->getClass(), 'Removed class should be null');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_an_added_belongs_to_relation(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        $model->testRelatedModel()->dissociate();
        $model->save();

        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->associate(TestRelatedModel::first());
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertFalse($object->isPlural(), 'Should be singular relation');
        static::assertFalse($object->hasVariableModelClass(), 'Should not have variable model class');
        static::assertTrue($object->hasMessage(), 'hasMessage() should be true');
        static::assertMatchesRegularExpression('/#1/', $object->getMessage(), 'getMessage() is not as expected');
        static::assertInstanceOf(RelatedAddedDifference::class, $object = $object->difference());
        /** @var RelatedAddedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Removed key should be 1');
        static::assertNull($object->getClass(), 'Removed class should be null');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_a_replaced_belongs_to_relation(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->testRelatedModel()->associate(TestRelatedModel::orderBy('id', 'desc')->first());
        $model->save();

        $model->load('testRelatedModel');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertFalse($object->isPlural(), 'Should be singular relation');
        static::assertFalse($object->hasVariableModelClass(), 'Should not have variable model class');
        static::assertTrue($object->hasMessage(), 'hasMessage() should be true');
        static::assertMatchesRegularExpression('/#1/', $object->getMessage(), 'getMessage() is not as expected');
        static::assertInstanceOf(RelatedReplacedDifference::class, $object = $object->difference());
        /** @var RelatedReplacedDifference $object */
        static::assertEquals(2, $object->getKey(), 'New key should be 1');
        static::assertEquals(1, $object->getKeyBefore(), 'Removed key should be 1');
        static::assertNull($object->getClass(), 'New class should be null');
        static::assertNull($object->getClassBefore(), 'Removed class should be null');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_the_same_belongs_to_relation_with_changes_to_the_related_model(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        /** @var TestRelatedModel $relatedModel */
        $relatedModel = $model->testRelatedModel()->first();
        $relatedModel->name = 'Changed Test Name 2';
        $relatedModel->save();

        $model->load('testRelatedModel');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertFalse($object->isPlural(), 'Should be singular relation');
        static::assertFalse($object->hasVariableModelClass(), 'Should not have variable model class');
        static::assertFalse($object->hasMessage(), 'hasMessage() should be false');
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Related key should be 1');
        static::assertNull($object->getClass(), 'Related class should be null');
        static::assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        static::assertTrue($object->isDifferent(), 'Changed model should report different');
        static::assertCount(1, $object->attributes(), 'There should be 1 attribute change');
        static::assertCount(0, $object->relations(), 'There should be no relation changes');
    }


    // ------------------------------------------------------------------------------
    //      Relations: Plural (HasMany / BelongsToMany)
    // ------------------------------------------------------------------------------

    /**
     * Remove one, keep one the same
     *
     * @test
     */
    public function it_compares_a_model_with_relation_changes_for_has_many_relation_with_removed_related_record(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedModel $model */
        $model = TestRelatedModel::first()->load('testModels');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->testModels()->first()->testRelatedModel()->dissociate()->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testModels'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        static::assertCount(1, $object->related());
        static::assertInstanceOf(RelatedRemovedDifference::class, $object = $object->related()->first());
        /** @var RelatedRemovedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Removed key should be 1');
        static::assertNull($object->getClass(), 'Removed class should be null');
    }

    /**
     * Add one, keep existing related the same
     *
     * @test
     */
    public function it_compares_a_model_with_relation_changes_for_has_many_relation_with_added_related_record(): void
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
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $removedModel->testRelatedModel()->associate($model->id)->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testModels'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        static::assertCount(1, $object->related());
        static::assertInstanceOf(RelatedAddedDifference::class, $object = $object->related()->first());
        /** @var RelatedAddedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Added key should be 1');
        static::assertNull($object->getClass(), 'Added class should be null');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_relation_changes_for_has_many_relation_with_changed_related_record(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedModel $model */
        $model = TestRelatedModel::first()->load('testModels');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        /** @var TestRelatedModel $related */
        $related = $model->testModels()->get()->last();
        $related->name = 'Changed Related Name';
        $related->save();

        $model = $model->fresh()->load('testModels');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testModels'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testModels']);
        static::assertCount(1, $object->related());
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $object->related()->first());
        /** @var RelatedChangedDifference $object */
        static::assertEquals(2, $object->getKey(), 'Changed model key should be 2');
        static::assertNull($object->getClass(), 'Changed model class should be null');
        static::assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        static::assertTrue($object->isDifferent(), 'Changed model should report different');
        static::assertCount(1, $object->attributes(), 'There should be 1 attribute change');
        static::assertCount(0, $object->relations(), 'There should be no relation changes');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_changed_belongs_to_many_connections(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestModel $model */
        $model = TestModel::first();

        $model->testRelatedAlphas()->sync([1, 2]);
        $model->load('testRelatedAlphas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);


        $model->testRelatedAlphas()->sync([2, 3]);
        TestRelatedAlpha::find(2)->update([ 'name' => 'Changed Alpha Name' ]);
        $model = $model->fresh()->load('testRelatedAlphas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedAlphas'));
        static::assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedAlphas']);
        static::assertCount(3, $relation->related(), 'There should be 3 related differences');

        static::assertTrue($relation->related()->has(1));
        static::assertInstanceOf(RelatedRemovedDifference::class, $object = $relation->related()->get(1));
        /** @var RelatedRemovedDifference $object */
        static::assertEquals(1, $object->getKey(), 'Related removed key does not match');
        static::assertNull($object->getClass(), 'Related removed class does not match');

        static::assertTrue($relation->related()->has(3));
        static::assertInstanceOf(RelatedAddedDifference::class, $object = $relation->related()->get(3));
        /** @var RelatedAddedDifference $object */
        static::assertEquals(3, $object->getKey(), 'Related added key does not match');
        static::assertNull($object->getClass(), 'Related added class does not match');
        static::assertTrue($object->hasMessage(), 'Related added should have a message');
        static::assertMatchesRegularExpression('/#3/', $object->getMessage(), 'Related added getMessage() is not as expected');

        static::assertTrue($relation->related()->has(2));
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        static::assertEquals(2, $object->getKey(), 'Related changed key does not match');
        static::assertNull($object->getClass(), 'Related changed class does not match');
        static::assertFalse($object->hasMessage(), 'Related changed should not have a message');
        static::assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        static::assertTrue($object->isDifferent(), 'Changed model should report different');
        static::assertCount(1, $object->attributes(), 'There should be 1 attribute change');
        static::assertCount(0, $object->relations(), 'There should be no relation changes');
    }


    // ------------------------------------------------------------------------------
    //      Relations with Pivot
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_compares_a_model_with_changed_pivot_attributes_for_unchanged_belongs_to_many_connection(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedAlpha $model */
        $model = TestRelatedAlpha::first();

        $model->testRelatedBetas()->sync([
            1 => [ 'position' => 1, 'date' => Carbon::create(2017) ],
            2 => [ 'position' => 2, 'date' => Carbon::create(2017) ],
        ]);
        $model->load('testRelatedBetas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);


        $model->testRelatedBetas()->sync([
            1,
            2 => [ 'position' => 5, 'date' => Carbon::create(2017, 3, 10)]
        ]);
        $model = $model->fresh()->load('testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedBetas'));
        static::assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedBetas']);
        static::assertCount(1, $relation->related(), 'There should be 1 related difference');

        static::assertTrue($relation->related()->has(2));
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        static::assertEquals(2, $object->getKey(), 'Related changed key does not match');
        static::assertNull($object->getClass(), 'Related changed class does not match');
        static::assertFalse($object->hasMessage(), 'Related changed should not have a message');
        static::assertInstanceOf(ModelDifference::class, $object->difference());
        static::assertFalse($object->difference()->isDifferent(), 'Changed model should not report different');
        static::assertTrue($object->isPivotRelated());
        static::assertInstanceOf(PivotDifference::class, $pivotDifference = $object->pivotDifference());
        /** @var PivotDifference $pivotDifference */
        static::assertTrue($pivotDifference->isDifferent(), 'Pivot attributes should be marked different');
        static::assertCount(2, $pivotDifference->attributes(), 'There should be 2 pivot attribute changes');


        /** @var AttributeDifference $object */
        static::assertTrue($pivotDifference->attributes()->has('position'));
        static::assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('position'));
        static::assertEquals(2, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(5, $object->after(), 'Change value (after) incorrect');
        static::assertFalse($object->didNotExistBefore());
        static::assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        static::assertTrue($pivotDifference->attributes()->has('date'));
        static::assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('date'));
        static::assertEquals('2017-01-01 00:00:00', $object->before(), 'Change value (before) incorrect');
        static::assertEquals('2017-03-10 00:00:00', $object->after(), 'Change value (after) incorrect');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_changed_pivot_attributes_for_unchanged_belongs_to_many_connection_with_model_change(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedAlpha $model */
        $model = TestRelatedAlpha::first();

        $model->testRelatedBetas()->sync([
            1 => [ 'position' => 1, 'date' => Carbon::create(2017) ],
            2 => [ 'position' => 2, 'date' => Carbon::create(2017) ],
        ]);
        $model->load('testRelatedBetas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);


        $model->testRelatedBetas()->sync([
            1,
            2 => [ 'position' => 5, 'date' => Carbon::create(2017, 3, 10)]
        ]);
        TestRelatedBeta::find(2)->update([ 'name' => 'Changed Beta Name' ]);
        $model = $model->fresh()->load('testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedBetas'));
        static::assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedBetas']);
        static::assertCount(1, $relation->related(), 'There should be 1 related difference');

        static::assertTrue($relation->related()->has(2));
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        static::assertEquals(2, $object->getKey(), 'Related changed key does not match');
        static::assertNull($object->getClass(), 'Related changed class does not match');
        static::assertFalse($object->hasMessage(), 'Related changed should not have a message');
        static::assertTrue($object->isPivotRelated());
        static::assertInstanceOf(PivotDifference::class, $pivotDifference = $object->pivotDifference());
        static::assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        static::assertTrue($object->isDifferent(), 'Changed model should report different');
        static::assertCount(1, $object->attributes(), 'There should be 1 attribute change');
        static::assertCount(0, $object->relations(), 'There should be no relation changes');
        static::assertTrue($object->attributes()->has('name'), "'name' attribute should be marked changed");

        /** @var PivotDifference $pivotDifference */
        static::assertTrue($pivotDifference->isDifferent(), 'Pivot attributes should be marked different');
        static::assertCount(2, $pivotDifference->attributes(), 'There should be 2 pivot attribute changes');

        /** @var AttributeDifference $object */
        static::assertTrue($pivotDifference->attributes()->has('position'));
        static::assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('position'));
        static::assertEquals(2, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(5, $object->after(), 'Change value (after) incorrect');
        static::assertFalse($object->didNotExistBefore());
        static::assertFalse($object->didNotExistAfter());

        /** @var AttributeDifference $object */
        static::assertTrue($pivotDifference->attributes()->has('date'));
        static::assertInstanceOf(AttributeDifference::class, $object = $pivotDifference->attributes()->get('date'));
        static::assertEquals('2017-01-01 00:00:00', $object->before(), 'Change value (before) incorrect');
        static::assertEquals('2017-03-10 00:00:00', $object->after(), 'Change value (after) incorrect');
    }

    /**
     * @test
     */
    public function it_compares_a_model_with_unchanged_pivot_attributes_for_unchanged_belongs_to_many_connection(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();

        /** @var TestRelatedAlpha $model */
        $model = TestRelatedAlpha::first();

        $model->testRelatedBetas()->sync([
            1 => [ 'position' => 1, 'date' => Carbon::create(2017) ],
            2 => [ 'position' => 2, 'date' => Carbon::create(2017) ],
        ]);
        $model->load('testRelatedBetas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);


        $model->testRelatedBetas()->sync([
            1,
            2,
        ]);
        TestRelatedBeta::find(2)->update([ 'name' => 'Changed Beta Name' ]);
        $model = $model->fresh()->load('testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertTrue($difference->isDifferent());
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedBetas'));
        static::assertInstanceOf(PluralRelationDifference::class, $relation = $difference->relations()['testRelatedBetas']);
        static::assertCount(1, $relation->related(), 'There should be 1 related difference');

        static::assertTrue($relation->related()->has(2));
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $relation->related()->get(2));
        /** @var RelatedChangedDifference $object */
        static::assertEquals(2, $object->getKey(), 'Related changed key does not match');
        static::assertNull($object->getClass(), 'Related changed class does not match');
        static::assertFalse($object->hasMessage(), 'Related changed should not have a message');
        static::assertTrue($object->isPivotRelated());
        static::assertInstanceOf(PivotDifference::class, $pivotDifference = $object->pivotDifference());
        static::assertInstanceOf(ModelDifference::class, $object = $object->difference());
        /** @var ModelDifference $object */
        static::assertTrue($object->isDifferent(), 'Changed model should report different');
        static::assertCount(1, $object->attributes(), 'There should be 1 attribute change');
        static::assertCount(0, $object->relations(), 'There should be no relation changes');
        static::assertTrue($object->attributes()->has('name'), "'name' attribute should be marked changed");

        /** @var PivotDifference $pivotDifference */
        static::assertFalse($pivotDifference->isDifferent(), 'Pivot attributes should not be marked different');
        static::assertCount(0, $pivotDifference->attributes(), 'There should be no pivot attribute changes');

    }

    // ------------------------------------------------------------------------------
    //      Limit nested lookups
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_can_be_configured_to_ignore_model_changes_for_models_of_specific_model_relations(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([1]);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        // Test
        $comparer = $this->makeComparer();
        $comparer->setNestedCompareRelations(['testRelatedModel']);
        $comparer->setBeforeState($model);

        TestRelatedModel::find(1)->update(['name' => 'Unignored changed name']);
        TestRelatedAlpha::find(1)->update(['name' => 'Ignored changed name']);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertCount(1, $difference->relations(), 'Only 1 relation should be changed');
        static::assertTrue($difference->relations()->has('testRelatedModel'));

        // Also test the reversed situation

        /** @var TestModel $model */
        $model->testRelatedAlphas()->sync([1]);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        // Test
        $comparer = $this->makeComparer();
        $comparer->setNestedCompareRelations(['testRelatedAlphas']);
        $comparer->setBeforeState($model);

        TestRelatedModel::find(1)->update(['name' => 'Ignored changed name']);
        TestRelatedAlpha::find(1)->update(['name' => 'Unignored changed name']);
        $model->load(['testRelatedModel', 'testRelatedAlphas']);

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertCount(1, $difference->relations(), 'Only 1 relation should be changed');
        static::assertTrue($difference->relations()->has('testRelatedAlphas'));
    }


    // ------------------------------------------------------------------------------
    //      Ignored & 'not real' changes
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_ignores_timestamp_changes_by_default(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->created_at = $model->created_at->addDay();
        $model->updated_at = $model->updated_at->subDay();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
    }

    /**
     * @test
     */
    public function it_tracks_timestamp_changes_if_configured_to(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();

        // Test
        $comparer = $this->makeComparer();
        $comparer->ignoreTimestamps(false);
        $comparer->setBeforeState($model);

        $model->created_at = $model->created_at->addDay();
        $model->updated_at = $model->updated_at->subDay();
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(2, $difference->attributes(), 'There should be no attribute changes');
        static::assertTrue($difference->attributes()->has('updated_at'));
        static::assertTrue($difference->attributes()->has('created_at'));
    }

    /**
     * @test
     */
    public function it_ignores_changes_that_are_detected_on_loosy_comparison_by_default(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->float = 0.0;
        $model->integer = 0;

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->float = null;
        $model->integer = null;
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
    }

    /**
     * @test
     */
    public function it_tracks_changes_that_are_detected_on_loosy_comparison_if_configured_to(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->float = 0.0;
        $model->integer = 0;

        // Test
        $comparer = $this->makeComparer();
        $comparer->useStrictComparison(true);
        $comparer->setBeforeState($model);

        $model->float = null;
        $model->integer = null;
        $model->save();

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(2, $difference->attributes(), 'There should be 2 attribute changes');
    }

    /**
     * @test
     */
    public function it_allows_configuring_specific_attributes_to_ignore_per_model(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([1]);
        $model->load('testRelatedModel', 'testRelatedAlphas');

        // Test
        $comparer = $this->makeComparer();

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
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(0, $difference->attributes(), 'There should be no attribute changes');
        static::assertCount(1, $difference->relations(), 'There should be 1 relation change');
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        static::assertTrue($object->difference()->isDifferent());
        static::assertCount(1, $object->difference()->attributes(), 'Only 1 attribute of testRelatedModel should be changed');
        static::assertTrue($object->difference()->attributes()->has('flag'), "Only 'flag' attribute of testRelatedModel should be changed");
        static::assertFalse($difference->relations()->has('testRelatedAlphas'));
    }


    // ------------------------------------------------------------------------------
    //      Creating and Deleting Models
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_tracks_models_related_after_that_were_created_since_the_before_state(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->load('testRelatedModel', 'testRelatedAlphas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $create = TestRelatedModel::create([
            'name' => 'Create New',
        ]);
        $model->testRelatedModel()->associate($create->id);
        $model->save();

        $create = TestRelatedAlpha::create([
            'name' => 'Create New Alpha',
        ]);
        $model->testRelatedAlphas()->sync([ $create->id ]);

        $model->load('testRelatedModel', 'testRelatedAlphas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(2, $difference->relations(), 'There should be 2 relation changes');

        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertInstanceOf(RelatedReplacedDifference::class, $object = $object->difference());
        /** @var RelatedReplacedDifference $object */
        static::assertInstanceOf(ModelCreatedDifference::class, $object->difference());
        static::assertTrue($object->difference()->isDifferent());
        static::assertEquals(1, $object->getKeyBefore());
        static::assertEquals(3, $object->getKey());
        static::assertTrue($object->difference()->attributes()->has('name'), 'Created attribute not listed');
        static::assertTrue($object->difference()->attributes()->has('flag'), 'Created attribute not listed');

        static::assertTrue($difference->relations()->has('testRelatedAlphas'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testRelatedAlphas']);
        /** @var PluralRelationDifference $object */
        static::assertCount(1, $object->related());
        static::assertInstanceOf(RelatedAddedDifference::class, $object = $object->related()->first());
        /** @var RelatedAddedDifference $object */
        static::assertInstanceOf(ModelCreatedDifference::class, $object->difference());
        static::assertTrue($object->difference()->isDifferent());
        static::assertEquals(4, $object->getKey());
        static::assertTrue($object->difference()->attributes()->has('name'), 'Created attribute not listed');
    }

    /**
     * @test
     */
    public function it_tracks_models_related_before_that_were_deleted_since_the_before_state(): void
    {
        // Set up
        $this->setUpSimpleBeforeState();
        /** @var TestModel $model */
        $model = TestModel::first();
        $model->testRelatedAlphas()->sync([2]);
        $model->load('testRelatedModel', 'testRelatedAlphas');

        // Test
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        //$model->testRelatedModel()->dissociate();
        TestRelatedModel::destroy(1);
        TestRelatedAlpha::destroy(2);

        $model->load('testRelatedModel', 'testRelatedAlphas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(2, $difference->relations(), 'There should be 2 relation changes');

        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()['testRelatedModel']);
        /** @var SingleRelationDifference $object */
        static::assertInstanceOf(RelatedRemovedDifference::class, $object = $object->difference());
        /** @var RelatedRemovedDifference $object */
        static::assertTrue($object->wasDeleted());

        static::assertTrue($difference->relations()->has('testRelatedAlphas'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()['testRelatedAlphas']);
        /** @var PluralRelationDifference $object */
        static::assertCount(1, $object->related());
        static::assertInstanceOf(RelatedRemovedDifference::class, $object = $object->related()->first());
        /** @var RelatedRemovedDifference $object */
        static::assertTrue($object->wasDeleted());
    }

    // ------------------------------------------------------------------------------
    //      Special cases
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    public function it_compares_a_complex_of_deeply_changed_related_models(): void
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
        $comparer = $this->makeComparer();
        $comparer->setBeforeState($model);

        $model->boolean = true;

        TestRelatedModel::find(1)->update(['name' => 'Changed!']);
        TestRelatedAlpha::find(1)->testRelatedBetas()->sync([1]);
        TestRelatedAlpha::find(2)->testRelatedBetas()->sync([
            1,
            3 => [ 'position' => 2, 'date' => Carbon::create(2017) ]
        ]);
        TestRelatedBeta::find(1)->update(['name' => 'Changed Beta!']);

        $model->load('testRelatedModel');
        $model->load('testRelatedAlphas.testRelatedBetas');

        $difference = $comparer->compareWithBefore($model);

        // Assert
        static::assertInstanceOf(ModelDifference::class, $difference);
        static::assertCount(1, $difference->attributes(), 'There should be 1 attribute change');
        static::assertCount(2, $difference->relations(), 'There should be 2 relation changes');

        /** @var AttributeDifference $object */
        static::assertTrue($difference->attributes()->has('boolean'));
        static::assertInstanceOf(AttributeDifference::class, $object = $difference->attributes()->get('boolean'));
        static::assertEquals(false, $object->before(), 'Change value (before) incorrect');
        static::assertEquals(true, $object->after(), 'Change value (after) incorrect');

        // TestRelatedModel
        /** @var SingleRelationDifference $object */
        static::assertTrue($difference->relations()->has('testRelatedModel'));
        static::assertInstanceOf(SingleRelationDifference::class, $object = $difference->relations()->get('testRelatedModel'));
        static::assertInstanceOf(RelatedChangedDifference::class, $object = $object->difference());
        /** @var RelatedChangedDifference $object */
        static::assertTrue($object->difference()->isDifferent(), 'TestRelatedModel should be marked different');
        static::assertCount(1, $object->difference()->attributes(), 'TestRelatedModel should have 1 changed attribute');
        static::assertTrue($object->difference()->attributes()->has('name'),
            "TestRelatedModel should have changed 'name' attribute");

        /** @var PluralRelationDifference $object */
        static::assertTrue($difference->relations()->has('testRelatedAlphas'));
        static::assertInstanceOf(PluralRelationDifference::class, $object = $difference->relations()->get('testRelatedAlphas'));
        static::assertCount(2, $object->related(), 'testRelatedAlphas should have 2 related changes');

        // TestRelatedAlphas
        static::assertTrue($object->related()->has(1), 'testRelatedAlphas key 1 should be present');
        static::assertTrue($object->related()->has(2), 'testRelatedAlphas key 2 should be present');
        $first  = $object->related()->get(1);
        $second = $object->related()->get(2);

        // TestRelatedAlpha: 1
        static::assertInstanceOf(RelatedChangedDifference::class, $first);
        /** @var RelatedChangedDifference $first */
        static::assertTrue($first->difference()->isDifferent());
        static::assertCount(0, $first->difference()->attributes());
        static::assertCount(1, $first->difference()->relations());
        static::assertTrue($first->difference()->relations()->has('testRelatedBetas'));

        static::assertTrue($first->difference()->relations()->get('testRelatedBetas')->related()->has(1));
        static::assertInstanceof(
            RelatedAddedDifference::class,
            $object = $first->difference()->relations()->get('testRelatedBetas')->related()->get(1)
        );
        /** @var RelatedAddedDifference $object */
        static::assertEquals($object->getKey(), 1);

        static::assertTrue($first->difference()->relations()->get('testRelatedBetas')->related()->has(2));
        static::assertInstanceof(
            RelatedRemovedDifference::class,
            $object = $first->difference()->relations()->get('testRelatedBetas')->related()->get(2)
        );
        /** @var RelatedRemovedDifference $object */
        static::assertEquals($object->getKey(), 2);

        // TestRelatedAlpha: 2
        static::assertInstanceOf(RelatedChangedDifference::class, $second);
        /** @var RelatedChangedDifference $second */
        static::assertTrue($second->difference()->isDifferent());

        static::assertTrue($second->difference()->relations()->get('testRelatedBetas')->related()->has(1));
        static::assertInstanceof(
            RelatedChangedDifference::class,
            $object = $second->difference()->relations()->get('testRelatedBetas')->related()->get(1)
        );
        /** @var RelatedChangedDifference $object */
        static::assertEquals($object->getKey(), 1);
        static::assertFalse($object->pivotDifference()->isDifferent());
        static::assertTrue($object->difference()->isDifferent());
        static::assertCount(1, $object->difference()->attributes());
        static::assertTrue($object->difference()->attributes()->has('name'));

        static::assertTrue($second->difference()->relations()->get('testRelatedBetas')->related()->has(3));
        static::assertInstanceof(
            RelatedChangedDifference::class,
            $object = $second->difference()->relations()->get('testRelatedBetas')->related()->get(3)
        );
        /** @var RelatedChangedDifference $object */
        static::assertEquals($object->getKey(), 3);
        static::assertFalse($object->difference()->isDifferent());
        static::assertTrue($object->pivotDifference()->isDifferent());
        static::assertCount(2, $object->pivotDifference()->attributes());
        static::assertTrue($object->pivotDifference()->attributes()->has('position'));
        static::assertTrue($object->pivotDifference()->attributes()->has('date'));
    }


    // ------------------------------------------------------------------------------
    //      Helper methods
    // ------------------------------------------------------------------------------

    /**
     * Sets up a simple 'before' state for basic tests.
     */
    protected function setUpSimpleBeforeState(): void
    {
        /** @var TestModel $model */
        $model = TestModel::forceCreate([
            'name'    => 'Test Name Before',
            'text'    => 'Test Text Before',
            'integer' => 10,
            'float'   => 0.5,
            'boolean' => false,
        ]);

        /** @var TestRelatedModel $relatedModel */
        $relatedModel = TestRelatedModel::forceCreate([
            'name' => 'Test Related Name A',
        ]);
        $model->testRelatedModel()->associate($relatedModel);
        $model->save();

        /** @var TestModel $model */
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

    protected function makeComparer(): Comparer
    {
        return app(Comparer::class);
    }
}
