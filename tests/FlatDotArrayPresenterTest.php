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
use Czim\ModelComparer\Presenters\FlatDotArrayPresenter;
use Czim\ModelComparer\Test\Helpers\TestModel;
use Czim\ModelComparer\Test\Helpers\TestRelatedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FlatDotArrayPresenterTest extends TestCase
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

        $presenter = new FlatDotArrayPresenter();

        $output = $presenter->present($difference);

        static::assertIsArray($output);
        static::assertCount(2, $output);
        static::assertArrayHasKey('name', $output);
        static::assertArrayHasKey('integer', $output);
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

        $presenter = new FlatDotArrayPresenter();

        $output = $presenter->present($difference);

        static::assertIsArray($output);
        static::assertCount(8, $output);

        static::assertArrayHasKey('boolean', $output);
        static::assertArrayHasKey('testRelation.related.1.testing', $output);
        static::assertArrayHasKey('testRelation2.related', $output);
        static::assertArrayHasKey('testRelation3.related', $output);
        static::assertArrayHasKey('testPivotRelation.1.related.1.testing', $output);
        static::assertArrayHasKey('testPivotRelation.2.related.2.testing', $output);
        static::assertArrayHasKey('testPivotRelation.2.related.pivot.testing', $output);
        static::assertArrayHasKey('testPivotRelation.3.related', $output);
    }
}
