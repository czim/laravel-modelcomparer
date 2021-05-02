<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer;

use Czim\ModelComparer\Contracts\ComparableDataTreeFactoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ComparableDataTreeFactory implements ComparableDataTreeFactoryInterface
{
    /**
     * Nested relations to fully include.
     *
     * If this is false, includes everything fully.
     * If an empty array, includes nothing fully.
     *
     * Anything not fully included will be compared by related key only (not changes in the child model(s)).
     *
     * Note that only (eager) loaded relations will be included.
     * Make sure the model is provided to this class using with() or load() to include all required nested relations.
     *
     * @var string[]|false
     */
    protected $nestedCompareFully = false;

    /**
     * Whether translations will always be fully compared, regardless of $nestedCompareFully.
     *
     * @var bool
     */
    protected $alwaysCompareTranslationsFully = true;

    /**
     * Whether changes to model timestamps should be ignored.
     *
     * @var bool
     */
    protected $ignoreTimestamps = true;

    /**
     * A list of attributes to ignore per model.
     *
     * An array of arrays, keyed by model FQN.
     *
     * @var array
     */
    protected $ignoreAttributesPerModel = [];


    /**
     * @param Model       $model
     * @param string|null $parent
     * @return array<string, mixed>
     */
    public function make(Model $model, ?string $parent = null): array
    {
        return $this->buildNormalizedArrayTree($model, $parent);
    }

    /**
     * Sets comparisons to always be completed in full.
     */
    public function alwaysCompareFully(): void
    {
        $this->nestedCompareFully = false;
    }

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.transations, article.articleSorts.translations ]
     *
     * @param string[] $compareFully
     */
    public function setNestedCompareRelations(array $compareFully): void
    {
        $this->nestedCompareFully = $compareFully;
    }

    /**
     * Sets whether the comparer should ignore all timestamp attributes.
     *
     * @param bool $ignore
     */
    public function ignoreTimestamps(bool $ignore = true): void
    {
        $this->ignoreTimestamps = $ignore;
    }

    /**
     * Sets a list of attributes per model.
     *
     * This overwrites all currently set ignores per model.
     *
     * @param array<string, string[]> $ignoredPerModel    array of arrays with attribute name strings, keyed by model FQN
     */
    public function setIgnoredAttributesForModels(array $ignoredPerModel): void
    {
        $this->ignoreAttributesPerModel = $ignoredPerModel;
    }

    /**
     * Sets a list of attributes to ignore for a given model.
     *
     * @param string   $modelClass
     * @param string[] $ignored
     */
    public function setIgnoredAttributesForModel(string $modelClass, array $ignored): void
    {
        $this->ignoreAttributesPerModel[ $modelClass ] = $ignored;
    }

    /**
     * Builds a normalized tree that is ready for comparison-usage.
     *
     * @param Model       $model
     * @param null|string $parent       dot-notation parent chain
     * @return array
     */
    protected function buildNormalizedArrayTree(Model $model, ?string $parent = null): array
    {
        // Analyze relations, build nested tree
        $relationKeys = array_keys($model->getRelations());
        $relationTree = [];
        $pivot = [];

        // Keep a list of foreign keys for BelongsTo, MorphTo relations
        $localForeignKeys = [];

        foreach ($relationKeys as $relationKey) {

            if (    $relationKey === 'pivot'
                &&  (   ($pivotObject = Arr::get($model->getRelations(), 'pivot')) instanceof Relations\Pivot
                    ||  ($pivotObject = Arr::get($model->getRelations(), 'pivot')) instanceof Relations\MorphPivot
                )
            ) {
                /** @var Relations\MorphPivot $pivotObject */
                // todo: get morph type keys

                /** @var Relations\Pivot $pivotObject */
                $pivotKeys = array_filter([
                    $pivotObject->getKey(),
                    $pivotObject->getOtherKey(),
                    $pivotObject->getForeignKey(),
                ]);

                if ($pivotObject->hasTimestampAttributes()) {
                    $pivotKeys[] = $pivotObject->getCreatedAtColumn();
                    $pivotKeys[] = $pivotObject->getUpdatedAtColumn();
                }

                $pivotAttributes = Arr::except($pivotObject->attributesToArray(), $pivotKeys);

                if (count($pivotAttributes)) {
                    $pivot = $pivotAttributes;
                }

                continue;
            }

            /** @var Relations\Relation $relationInstance */
            $relationName     = Str::camel($relationKey);
            $relationInstance = $model->{$relationName}();
            $nestedParent     = ($parent ? $parent . '.' : '') . $relationName;

            $isSingle = $this->isSingleRelation($relationInstance);
            $isMorph  = $this->isMorphTo($relationInstance);
            $hasPivot = $this->hasPivotTable($relationInstance);

            $foreignKeys = [];

            if ($relationInstance instanceof Relations\BelongsTo) {
                $foreignKeys[] = $relationInstance->getForeignKeyName();
            } elseif ($relationInstance instanceof Relations\MorphTo) {
                $foreignKeys[] = $relationInstance->getForeignKeyName();
                $foreignKeys[] = $relationInstance->getMorphType();
            }

            $items = [];

            $childModels = new Collection();

            if ($isSingle) {
                /** @var Model|null $childModel */
                if ($childModel = $model->{$relationName}) {
                    $childModels->push($childModel);
                }

            } else {
                /** @var Model|null $childModel */
                $childModels = $model->{$relationName};
            }

            foreach ($childModels as $childModel) {

                $key = $childModel->getKey();
                if ($isMorph) {
                    $key = get_class($childModel) . ':' . $key;
                }

                // If the compare should not be nested contextually, only include a list of keys
                if (
                    false !== $this->nestedCompareFully
                    && (! $this->alwaysCompareTranslationsFully || $relationName !== 'translations')
                    && ! in_array($nestedParent, $this->nestedCompareFully, true)
                ) {
                    $items[ $key ] = $key;
                    continue;
                }

                $items[ $key ] = $this->buildNormalizedArrayTree($childModel, $nestedParent);
            }

            $localForeignKeys += $foreignKeys;

            $relationTree[ $relationKey ] = [
                'method'       => $relationName,
                'type'         => get_class($relationInstance),
                'single'       => $isSingle,
                'morph'        => $isMorph,
                'pivot'        => $hasPivot,
                'items'        => $items,
                'foreign_keys' => $foreignKeys,
            ];
        }

        // Handle attributes of this model itself
        $ignoreKeys = $localForeignKeys;

        if ($this->ignoreTimestamps && $model->timestamps) {
            $ignoreKeys[] = $model->getCreatedAtColumn();
            $ignoreKeys[] = $model->getUpdatedAtColumn();
        }

        if (array_key_exists(get_class($model), $this->ignoreAttributesPerModel)) {
            $ignoreKeys = array_merge($ignoreKeys, $this->ignoreAttributesPerModel[get_class($model)]);
        }

        $attributes = Arr::except($model->attributesToArray(), $ignoreKeys);

        return [
            'class'      => get_class($model),
            'pivot'      => $pivot,
            'attributes' => $attributes,
            'relations'  => $relationTree,
        ];
    }

    protected function isSingleRelation(Relations\Relation $relation): bool
    {
        return $relation instanceof Relations\BelongsTo
            || $relation instanceof Relations\HasOne
            || $relation instanceof Relations\MorphTo
            || $relation instanceof Relations\MorphOne;
    }

    protected function isMorphTo(Relations\Relation $relation): bool
    {
        return $relation instanceof Relations\MorphTo;
    }

    protected function hasPivotTable(Relations\Relation $relation): bool
    {
        return $relation instanceof Relations\BelongsToMany
            || $relation instanceof Relations\MorphToMany;
    }
}
