<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Presenters;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Contracts\DifferencePresenterInterface;
use Czim\ModelComparer\Data\AbstractRelatedDifference;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\DifferenceCollection;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PluralRelationDifference;
use Czim\ModelComparer\Data\RelatedAddedDifference;
use Czim\ModelComparer\Data\RelatedChangedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;

/**
 * Returns difference as a nested array.
 */
class ArrayPresenter implements DifferencePresenterInterface
{
    /**
     * Returns a presentation for a given model difference object tree.
     *
     * @param ModelDifference $difference
     * @return array<string, mixed>
     */
    public function present(ModelDifference $difference): array
    {
        if (! $difference->isDifferent()) {
            return [];
        }

        return $this->convertModelDifference($difference);
    }

    protected function convertModelDifference(ModelDifference $difference): array
    {
        $output = [];

        $attributes = $this->convertAttributes($difference->attributes());
        if (! empty($attributes)) {
            $output['attributes'] = $attributes;
        }

        $relations = $this->convertRelations($difference->relations());
        if (! empty($relations)) {
            $output['relations'] = $relations;
        }

        return $output;
    }

    protected function convertAttributes(DifferenceCollection $attributes): array
    {
        return $attributes->transform(
            static function (AttributeDifference $difference) {
                return (string) $difference;
            }
        )->toArray();
    }

    protected function convertRelations(DifferenceCollection $relations): array
    {
        $output = [];

        foreach ($relations as $name => $relation) {
            if ($relation instanceof SingleRelationDifference) {
                $difference = $relation->difference();

                $output[ $name ] = $this->convertRelatedDifference($difference);

                continue;
            }

            if ($relation instanceof PluralRelationDifference) {
                $output[ $name ] = [];

                foreach ($relation->related() as $key => $difference) {
                    $output[ $name ][ $key ] = $this->convertRelatedDifference($difference);
                }
            }
        }

        return $output;
    }

    protected function convertRelatedDifference(AbstractRelatedDifference $difference): array
    {
        $output = [];

        if ($difference instanceof DifferenceNodeInterface) {
            if ($difference->hasMessage()) {
                $output['related'] = $difference->getMessage();
            }
        } else {
            $output['related'] = (string) $difference;
        }

        if ($difference instanceof RelatedChangedDifference) {
            if ($difference->difference()->isDifferent()) {
                $output['model'] = $this->convertModelDifference($difference->difference());
            }

            if ($difference->isPivotRelated() && $difference->pivotDifference()->isDifferent()) {
                $output['pivot'] = $this->convertAttributes($difference->pivotDifference()->attributes());
            }
        } elseif ($difference instanceof RelatedAddedDifference) {
            if ($difference->difference() && $difference->difference()->isDifferent()) {
                $output['model'] = $this->convertModelDifference($difference->difference());
            }
        }

        return $output;
    }
}
