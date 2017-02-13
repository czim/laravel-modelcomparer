<?php
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

class ArrayPresenterTest extends TestCase
{

    /**
     * @test
     */
    function it_presents_simple_attribute_changes()
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

        $this->assertInternalType('array', $output);

        $output = array_dot($output);

        $this->assertCount(2, $output);
        $this->assertArrayHasKey('attributes.name', $output);
        $this->assertArrayHasKey('attributes.integer', $output);
    }

    /**
     * @test
     */
    function it_presents_complex_attribute_changes()
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

        $this->assertInternalType('array', $output);

        $output = array_dot($output);

        $this->assertArraySubset([
            'attributes.boolean',
            'relations.testRelation.model.attributes.testing',
            'relations.testRelation2.related',
            'relations.testRelation3.related',
            'relations.testRelation4.related',
            'relations.testRelation4.model.attributes.testing',
            'relations.testRelation5.related',
            'relations.testPivotRelation.1.model.attributes.testing',
            'relations.testPivotRelation.2.model.attributes.testing',
            'relations.testPivotRelation.2.pivot.testing',
            'relations.testPivotRelation.3.related',
        ], array_keys($output));

        $this->assertCount(11, $output);

        $this->assertContains('created', $output['relations.testRelation4.related']);
        $this->assertContains('deleted', $output['relations.testRelation5.related']);
    }

}
