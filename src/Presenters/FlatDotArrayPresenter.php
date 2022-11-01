<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Presenters;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Contracts\DifferencePresenterInterface;
use Czim\ModelComparer\Data\AbstractRelatedDifference;
use Czim\ModelComparer\Data\DifferenceCollection;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PluralRelationDifference;
use Czim\ModelComparer\Data\RelatedChangedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;

/**
 * Returns difference as a flat, single-dimensional key-value pair array, with dot notation keys for nested data.
 */
class FlatDotArrayPresenter implements DifferencePresenterInterface
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

        $output = [];

        $this->convertModelDifference($output, $difference);

        return $output;
    }

    /**
     * @param array<string, mixed> $output
     * @param ModelDifference      $difference
     * @param string|null          $parentKey
     */
    protected function convertModelDifference(
        array &$output,
        ModelDifference $difference,
        ?string $parentKey = null,
    ): void {
        $this->convertAttributes($output, $difference->attributes(), $parentKey);
        $this->convertRelations($output, $difference->relations(), $parentKey);
    }

    /**
     * @param array<string, mixed> $output
     * @param DifferenceCollection $attributes
     * @param string|null          $parentKey
     */
    protected function convertAttributes(
        array &$output,
        DifferenceCollection $attributes,
        ?string $parentKey = null,
    ): void {
        $baseKey = $parentKey ? rtrim($parentKey, '.') . '.' : null;

        foreach ($attributes as $name => $attribute) {
            $output[ $baseKey . $name ] = (string) $attribute;
        }
    }

    /**
     * @param array<string, mixed> $output
     * @param DifferenceCollection $relations
     * @param string|null          $parentKey
     */
    protected function convertRelations(
        array &$output,
        DifferenceCollection $relations,
        ?string $parentKey = null
    ): void {
        $baseKey = $parentKey ? rtrim($parentKey, '.') . '.' : null;

        foreach ($relations as $name => $relation) {
            if ($relation instanceof SingleRelationDifference) {
                $difference = $relation->difference();

                $this->convertRelatedDifference($output, $difference, $baseKey . $name);

                continue;
            }

            if ($relation instanceof PluralRelationDifference) {
                foreach ($relation->related() as $key => $difference) {
                    $this->convertRelatedDifference($output, $difference, $baseKey . $name . '.' . $key);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>      $output
     * @param AbstractRelatedDifference $difference
     * @param string|null               $parentKey
     */
    protected function convertRelatedDifference(
        array &$output,
        AbstractRelatedDifference $difference,
        ?string $parentKey = null,
    ): void {
        $baseKey = $parentKey ? rtrim($parentKey, '.') . '.' : null;

        if ($difference instanceof DifferenceNodeInterface) {
            if ($difference->hasMessage()) {
                $output[ $baseKey . 'related' ] = $difference->getMessage();
            }
        } elseif ($difference instanceof DifferenceLeafInterface) {
            $output[ $baseKey . 'related' ] = (string) $difference;
        }


        if ($difference instanceof RelatedChangedDifference) {
            if ($difference->difference()->isDifferent()) {
                $this->convertModelDifference(
                    $output,
                    $difference->difference(),
                    $baseKey . 'related.' . $difference->getModelReference()
                );
            }

            if ($difference->isPivotRelated() && $difference->pivotDifference()->isDifferent()) {
                $this->convertAttributes(
                    $output,
                    $difference->pivotDifference()->attributes(),
                    $baseKey . 'related.pivot'
                );
            }
        }
    }
}
