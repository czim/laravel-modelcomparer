<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Test;

use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\DifferenceCollection;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PivotDifference;
use Czim\ModelComparer\Data\PluralRelationDifference;
use Czim\ModelComparer\Data\RelatedAddedDifference;
use Czim\ModelComparer\Data\RelatedChangedDifference;
use Czim\ModelComparer\Data\RelatedRemovedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;
use Czim\ModelComparer\Presenters\ArrayPresenter;
use Czim\ModelComparer\Test\Helpers\TestModel;
use Czim\ModelComparer\Test\Helpers\TestRelatedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class ArrayPresenterTest extends TestCase
{

    /**
     * @test
     */
    public function it_presents_simple_attribute_changes(): void
    {
        $difference = new ModelDifference(
            TestModel::class,
            new DifferenceCollection([
                'name'    => new AttributeDifference('before', 'after'),
                'integer' => new AttributeDifference(1, 2),
            ]),
            new DifferenceCollection
        );

        $presenter = new ArrayPresenter();

        $output = $presenter->present($difference);

        static::assertIsArray($output);

        $output = Arr::dot($output);

        static::assertCount(2, $output);
        static::assertArrayHasKey('attributes.name', $output);
        static::assertArrayHasKey('attributes.integer', $output);
    }

    /**
     * @test
     */
    public function it_presents_complex_attribute_changes(): void
    {
        $difference = new ModelDifference(
            TestModel::class,
            new DifferenceCollection([
                'boolean' => new AttributeDifference(true, false),
            ]),
            new DifferenceCollection([
                'testRelation' => new SingleRelationDifference(
                    'testRelation',
                    BelongsTo::class,
                    new RelatedChangedDifference(
                        1,
                        null,
                        new ModelDifference(
                            TestRelatedModel::class,
                            new DifferenceCollection([
                                'testing' => new AttributeDifference('beforish', 'afterish')
                            ]),
                            new DifferenceCollection
                        )
                    )
                ),
                'testRelation2' => new SingleRelationDifference(
                    'testRelation2',
                    BelongsTo::class,
                    new RelatedAddedDifference(2)
                ),
                'testRelation3' => new SingleRelationDifference(
                    'testRelation3',
                    BelongsTo::class,
                    new RelatedRemovedDifference(3)
                ),
                'testRelation4' => new SingleRelationDifference(
                    'testRelation4',
                    BelongsTo::class,
                    new RelatedAddedDifference(
                        2,
                        null,
                        // added model was created
                        new ModelDifference(
                            TestRelatedModel::class,
                            new DifferenceCollection([
                                'testing' => new AttributeDifference(null, 'afterish', true)
                            ]),
                            new DifferenceCollection
                        )
                    )
                ),
                'testRelation5' => new SingleRelationDifference(
                    'testRelation5',
                    BelongsTo::class,
                    new RelatedRemovedDifference(3, null, true) // removed model deleted
                ),
                'testPivotRelation' => new PluralRelationDifference(
                    'testPivotRelation',
                    BelongsToMany::class,
                    new DifferenceCollection([
                        '1' => new RelatedChangedDifference(
                            1,
                            null,
                            new ModelDifference(
                                TestRelatedModel::class,
                                new DifferenceCollection([
                                    'testing' => new AttributeDifference('beforish', 'afterish')
                                ]),
                                new DifferenceCollection
                            )
                        ),
                        '2' => new RelatedChangedDifference(
                            2,
                            null,
                            new ModelDifference(
                                TestRelatedModel::class,
                                new DifferenceCollection([
                                    'testing' => new AttributeDifference('beforish2', 'afterish2')
                                ]),
                                new DifferenceCollection
                            ),
                            new PivotDifference(new DifferenceCollection([
                                'testing' => new AttributeDifference('pivotBefore', 'pivotAfter')
                            ]))
                        ),
                        '3' => new RelatedRemovedDifference(3),
                    ])
                ),
            ])
        );

        $presenter = new ArrayPresenter();

        $output = $presenter->present($difference);

        static::assertIsArray($output);

        $output = Arr::dot($output);

        static::assertArrayHasKey('attributes.boolean', $output);
        static::assertArrayHasKey('relations.testRelation.model.attributes.testing', $output);
        static::assertArrayHasKey('relations.testRelation2.related', $output);
        static::assertArrayHasKey('relations.testRelation3.related', $output);
        static::assertArrayHasKey('relations.testRelation4.related', $output);
        static::assertArrayHasKey('relations.testRelation4.model.attributes.testing', $output);
        static::assertArrayHasKey('relations.testRelation5.related', $output);
        static::assertArrayHasKey('relations.testPivotRelation.1.model.attributes.testing', $output);
        static::assertArrayHasKey('relations.testPivotRelation.2.model.attributes.testing', $output);
        static::assertArrayHasKey('relations.testPivotRelation.2.pivot.testing', $output);
        static::assertArrayHasKey('relations.testPivotRelation.3.related', $output);

        static::assertCount(11, $output);

        static::assertStringContainsString('created', $output['relations.testRelation4.related']);
        static::assertStringContainsString('deleted', $output['relations.testRelation5.related']);
    }
}
