<?php
namespace Czim\ModelComparer\Comparer;

use Czim\ModelComparer\Comparer\Strategies\SimpleStrategy;
use Czim\ModelComparer\Contracts\ComparerInterface;
use Czim\ModelComparer\Contracts\CompareStrategyInterface;
use Czim\ModelComparer\Data;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Class Comparer
 *
 * Renders insight in the difference of a model structure to any depth,
 * depending on the (eager) loaded relation tree. This helps generate
 * clean and non-redundant changelogs for model updates.
 *
 */
class Comparer implements ComparerInterface
{

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * Before state as a normalized tree.
     *
     * @var array|null
     */
    protected $before;

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
     * Whether comparison of values should be done strictly.
     * If not strict, filters out out potentially meaningless changes.
     * (boolean false to 0, for instance).
     *
     * @var bool
     */
    protected $strictComparison = false;

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
     * Whether model events should be listened to.
     *
     * @var bool
     */
    protected $listening = false;

    /**
     * A list of lists of keys per model FQN, for models that were created since setting the last before state.
     *
     * @var mixed[][]
     */
    protected $createdSinceBeforeState = [];

    /**
     * A list of lists of keys per model FQN, for models that were deleted since setting the last before state.
     *
     * @var mixed[][]
     */
    protected $deletedSinceBeforeState = [];



    public function __construct()
    {
        $this->events = app('events');

        $this->listenForEvents();
    }

    // ------------------------------------------------------------------------------
    //      Configuration
    // ------------------------------------------------------------------------------

    /**
     * Sets whether the comparer should ignore all timestamp attributes.
     *
     * @param bool $ignore
     * @return $this
     */
    public function ignoreTimestamps(bool $ignore = true): ComparerInterface
    {
        $this->ignoreTimestamps = $ignore;

        return $this;
    }

    /**
     * Sets whether all comparison should be done strict.
     *
     * @param bool $strict
     * @return $this
     */
    public function useStrictComparison(bool $strict = true): ComparerInterface
    {
        $this->strictComparison = $strict;

        return $this;
    }

    /**
     * Sets comparisons to always be completed in full.
     *
     * @return $this
     */
    public function alwaysCompareFully(): ComparerInterface
    {
        $this->nestedCompareFully = false;

        return $this;
    }

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.transations, article.articleSorts.translations ]
     *
     * @param array $compareFully
     * @return $this
     */
    public function setNestedCompareRelations(array $compareFully): ComparerInterface
    {
        $this->nestedCompareFully = $compareFully;

        return $this;
    }

    /**
     * Sets a list of attributes per model.
     *
     * This overwrites all currently set ignores per model.
     *
     * @param array $ignoredPerModel    array of arrays with attribute name strings, keyed by model FQN
     * @return $this
     */
    public function setIgnoredAttributesForModels(array $ignoredPerModel): ComparerInterface
    {
        $this->ignoreAttributesPerModel = $ignoredPerModel;

        return $this;
    }

    /**
     * Sets a list of attributes to ignore for a given model.
     *
     * @param string|Model $model
     * @param array        $ignored
     * @return $this
     */
    public function setIgnoredAttributesForModel($model, array $ignored): ComparerInterface
    {
        if (is_object($model)) {
            $model = get_class($model);
        }

        $this->ignoreAttributesPerModel[ $model ] = $ignored;

        return $this;
    }


    // ------------------------------------------------------------------------------
    //      Comparison
    // ------------------------------------------------------------------------------

    /**
     * Sets the before state to be compared with an after state later.
     *
     * @param Model $model
     * @return $this
     */
    public function setBeforeState(Model $model): ComparerInterface
    {
        $this->resetBeforeState();
        $this->before = $this->buildNormalizedArrayTree($model);

        return $this->startListening();
    }

    /**
     * Clears any previously set before state.
     *
     * @return $this
     */
    public function clearBeforeState(): ComparerInterface
    {
        $this->resetBeforeState();

        $this->stopListening();

        return $this;
    }

    /**
     * Compares the earlier set before state with a new after state.
     *
     * @param Model $model
     * @return Data\ModelDifference
     */
    public function compareWithBefore(Model $model): Data\ModelDifference
    {
        if (null === $this->before) {
            throw new RuntimeException('No before state was set for comparison');
        }

        $after = $this->buildNormalizedArrayTree($model);

        $difference = $this->buildDifferenceTree(get_class($model), $this->before, $after);

        $this->stopListening();

        return $difference;
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
                if (    false !== $this->nestedCompareFully
                    &&  ( ! $this->alwaysCompareTranslationsFully || $relationName !== 'translations')
                    &&  ! in_array($nestedParent, $this->nestedCompareFully, true)
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
        return  (   $relation instanceof Relations\BelongsTo
                ||  $relation instanceof Relations\HasOne
                ||  $relation instanceof Relations\MorphTo
                ||  $relation instanceof Relations\MorphOne
                );
    }

    protected function isMorphTo(Relations\Relation $relation): bool
    {
        return $relation instanceof Relations\MorphTo;
    }

    protected function hasPivotTable(Relations\Relation $relation): bool
    {
        return  $relation instanceof Relations\BelongsToMany
            ||  $relation instanceof Relations\MorphToMany;
    }

    /**
     * Returns the difference object tree for a the model
     *
     * @param string|null $modelClass
     * @param array|mixed $before       if not an array, model difference should not be tracked
     * @param array|mixed $after        if not an array, model difference should not be tracked
     * @return Data\ModelDifference
     */
    protected function buildDifferenceTree(?string $modelClass, $before, $after): Data\ModelDifference
    {
        // If difference is ignored, don't build difference tree
        if ( ! is_array($before) || ! is_array($after)) {
            return new Data\ModelDifference(
                $modelClass,
                new Data\DifferenceCollection,
                new Data\DifferenceCollection
            );
        }

        // Check attributes
        // Filter out foreign key attributes
        $localForeignKeys = $this->collectLocalForeignKeys($before)
                          + $this->collectLocalForeignKeys($after);

        $attributesBefore = Arr::except(Arr::get($before, 'attributes', []), $localForeignKeys);
        $attributesAfter  = Arr::except(Arr::get($after, 'attributes', []), $localForeignKeys);

        $attributeDiff = $this->buildAttributesDifferenceList($attributesBefore, $attributesAfter);

        // Check relations
        $relationDiff = $this->buildRelationsDifferenceList(
            Arr::get($before, 'relations', []),
            Arr::get($after, 'relations', [])
        );

        return new Data\ModelDifference($modelClass, $attributeDiff, $relationDiff);
    }

    /**
     * Builds difference object for pivot record's attributes.
     *
     * @param array|mixed $before   if not an array, model difference should not be tracked
     * @param array|mixed $after    if not an array, model difference should not be tracked
     * @return Data\PivotDifference
     */
    protected function buildPivotDifference($before, $after): Data\PivotDifference
    {
        // If ignored, return empty difference
        if ( ! is_array($before) || ! is_array($after)) {
            return new Data\PivotDifference(new Data\DifferenceCollection);
        }

        return new Data\PivotDifference(
            $this->buildAttributesDifferenceList($before, $after)
        );
    }

    /**
     * Builds difference collection with attribute differences.
     *
     * @param array $before
     * @param array $after
     * @return Data\DifferenceCollection
     */
    protected function buildAttributesDifferenceList(array $before, array $after): Data\DifferenceCollection
    {
        $differences = new Data\DifferenceCollection;

        foreach ($before as $key => $value) {

            // If the key does not exist in the new array
            if ( ! array_key_exists($key, $after)) {
                $differences->put($key, new Data\AttributeDifference($value, false, false, true));
                continue;
            }

            // If the key does exist, check if the value is difference
            if ( ! $this->isAttributeValueEqual($value, $after[ $key ])) {
                $differences->put($key, new Data\AttributeDifference($value, $after[ $key ]));
            }
        }

        // List differences for newly added keys not present before
        foreach (array_diff(array_keys($after), array_keys($before)) as $key) {
            $differences->put($key, new Data\AttributeDifference(false, $after[$key], true));
        }

        return $differences;
    }

    /**
     * Builds difference collection with relation differences.
     *
     * This may recursively create nested model difference objects.
     *
     * @param array $before
     * @param array $after
     * @return Data\DifferenceCollection
     */
    protected function buildRelationsDifferenceList(array $before, array $after): Data\DifferenceCollection
    {
        $differences = new Data\DifferenceCollection;

        foreach ($before as $key => $value) {

            // Only treat differences for relations present before & after
            if ( ! array_key_exists($key, $after)) {
                continue;
            }

            $difference = $this->getRelationDifference($value, $after[ $key ]);

            if (false === $difference) {
                continue;
            }

            $differences->put($key, $difference);
        }

        return $differences;
    }

    /**
     * Returns a relation difference object for potentially changed relation data.
     *
     * @param array  $before
     * @param array  $after
     * @return Data\AbstractRelationDifference|false     false if the data is 100% the same.
     */
    protected function getRelationDifference(array $before, array $after)
    {
        $method = Arr::get($before, 'method');
        $type   = Arr::get($before, 'type');
        $single = Arr::get($before, 'single', true);
        $morph  = Arr::get($before, 'morph', true);
        $pivot  = Arr::get($before, 'pivot', false);

        $beforeItems = Arr::get($before, 'items', []);
        $afterItems  = Arr::get($after, 'items', []);

        if ($single) {

            if ( ! count($beforeItems) && ! count($afterItems)) {
                // Nothing changed, still unconnected
                return false;
            }

            if ( ! count($beforeItems)) {

                $key = head(array_keys($afterItems));
                [$key, $class] = $this->getKeyAndClassFromReference($key, $morph);

                $modelClass = $class ?: $afterItems[ $key ]['class'];

                $difference = null;
                if ($this->wasModelCreated($modelClass, $key)) {
                    $difference = new Data\ModelCreatedDifference(
                        $modelClass,
                        $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $key ], 'attributes', [])),
                        new Data\DifferenceCollection
                    );
                }

                $difference = new Data\RelatedAddedDifference($key, $class, $difference);

            } elseif ( ! count($afterItems)) {

                $key = head(array_keys($beforeItems));
                [$key, $class] = $this->getKeyAndClassFromReference($key, $morph);

                $modelClass = $class ?: $beforeItems[ $key ]['class'];
                $deleted = $this->wasModelDeleted($modelClass, $key);

                $difference = new Data\RelatedRemovedDifference($key, $class, $deleted);

            } else {

                $keyBefore = head(array_keys($beforeItems));
                $keyAfter  = head(array_keys($afterItems));

                if ($keyBefore === $keyAfter) {
                    // The same model is still related, but it may be altered
                    $modelClass = $afterItems[ $keyAfter ]['class'];

                    $difference = $this->buildDifferenceTree(
                        $modelClass,
                        $beforeItems[ $keyBefore ],
                        $afterItems[ $keyAfter ]
                    );

                    if ( ! $difference->isDifferent()) {
                        return false;
                    }

                    [$keyOnlyBefore, $classBefore] = $this->getKeyAndClassFromReference($keyBefore, $morph);

                    $difference = new Data\RelatedChangedDifference($keyOnlyBefore, $classBefore, $difference);

                } else {
                    // The model related before was replaced by another
                    [$keyOnlyBefore, $classBefore] = $this->getKeyAndClassFromReference($keyBefore, $morph);
                    [$keyOnlyAfter, $classAfter] = $this->getKeyAndClassFromReference($keyAfter, $morph);

                    $modelClass = $classAfter ?: $afterItems[ $keyAfter ]['class'];

                    // If the newly added model was created, track this as the difference
                    if ($this->wasModelCreated($modelClass, $keyOnlyAfter)) {
                        $difference = new Data\ModelCreatedDifference(
                            $classAfter ?: $afterItems[ $keyAfter ]['class'],
                            $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $keyAfter ], 'attributes', [])),
                            new Data\DifferenceCollection
                        );
                    } else {
                        $difference = new Data\ModelDifference(
                            $classAfter ?: $afterItems[ $keyAfter ]['class'],
                            new Data\DifferenceCollection,
                            new Data\DifferenceCollection
                        );
                    }

                    $difference = new Data\RelatedReplacedDifference(
                        $keyOnlyAfter,
                        $classAfter,
                        $difference,
                        $keyOnlyBefore,
                        $classBefore
                    );
                }
            }

            return new Data\SingleRelationDifference($method, $type, $difference);
        }

        // Plural relations

        $differences = new Data\DifferenceCollection;

        if ( ! count($beforeItems) && ! count($afterItems)) {
            // Nothing changed, still 0 connections
            return false;
        }

        // Find relations that are no longer present
        $removedKeys = array_diff(array_keys($beforeItems), array_keys($afterItems));
        foreach ($removedKeys as $key) {

            [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

            $modelClass = $class ?: $beforeItems[ $key ]['class'];

            $deleted = $this->wasModelDeleted($modelClass, $keyOnly);

            $differences->put($key, new Data\RelatedRemovedDifference($keyOnly, $class, $deleted));
        }

        // Find relations that are newly present
        $addedKeys = array_diff(array_keys($afterItems), array_keys($beforeItems));
        foreach ($addedKeys as $key) {

            [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

            $modelClass = $class ?: $afterItems[ $key ]['class'];

            $difference = null;
            if ($this->wasModelCreated($modelClass, $keyOnly)) {
                $difference = new Data\ModelCreatedDifference(
                    $modelClass,
                    $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $keyOnly ], 'attributes', [])),
                    new Data\DifferenceCollection
                );
            }

            $differences->put($key, new Data\RelatedAddedDifference($keyOnly, $class, $difference));
        }

        // Check for changes on previously related models
        $relatedKeys = array_intersect(array_keys($beforeItems), array_keys($afterItems));
        foreach ($relatedKeys as $key) {

            $modelClass = $beforeItems[$key]['class'];

            $difference      = $this->buildDifferenceTree($modelClass, $beforeItems[$key], $afterItems[$key]);
            $pivotDifference = null;

            // For pivot-based relations, check the pivot differences
            if ($pivot) {
                $pivotDifference = $this->buildPivotDifference($beforeItems[$key]['pivot'], $afterItems[$key]['pivot']);
            }

            if ($difference->isDifferent() || $pivotDifference && $pivotDifference->isDifferent()) {

                [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

                $differences->put($key, new Data\RelatedChangedDifference($keyOnly, $class, $difference, $pivotDifference));
            }
        }

        // If no actual (unignored) differences are found, the entire relation is unchanged.
        if ( ! count($differences)) {
            return false;
        }

        return new Data\PluralRelationDifference($method, $type, $differences);
    }


    /**
     * @param string $key
     * @param bool   $morph
     * @return array    key, model class
     */
    protected function getKeyAndClassFromReference(string $key, bool $morph = false): array
    {
        $class = null;

        if ( ! $morph) {
            return [ $key, $class ];
        }

        return explode(':', $key, 2);
    }

    /**
     * Returns whether two values are the same.
     *
     * @param mixed $before
     * @param mixed $after
     * @return bool|null  null if the difference is only loose.
     */
    protected function isAttributeValueEqual($before, $after): ?bool
    {
        // Determine the strategy
        $strategy = SimpleStrategy::class;

        /** @var CompareStrategyInterface $strategyInstance */
        $strategyInstance = App::make($strategy);

        return $strategyInstance->equal($before, $after, $this->strictComparison);
    }

    /**
     * Returns list of all local foreign keys marked in a normalized state array.
     *
     * @param array $tree
     * @return string[]
     */
    protected function collectLocalForeignKeys(array $tree): array
    {
        $keys = [];

        foreach ($tree['relations'] as $relation) {
            $keys += Arr::get($relation, 'foreign_keys', []);
        }

        return array_unique($keys);
    }


    // ------------------------------------------------------------------------------
    //      Events & Tracking
    // ------------------------------------------------------------------------------

    /**
     * Resets tracked data connected to the last before state set.
     */
    protected function resetBeforeState(): void
    {
        $this->before = null;
        $this->createdSinceBeforeState = [];
        $this->deletedSinceBeforeState = [];
    }

    /**
     * Returns whether a model was tracked as created since the before state was set.
     *
     * @param string $class
     * @param mixed  $key
     * @return bool
     */
    protected function wasModelCreated(string $class, $key): bool
    {
        if ( ! array_key_exists($class, $this->createdSinceBeforeState)) {
            return false;
        }

        return in_array($key, $this->createdSinceBeforeState[ $class ]);
    }

    /**
     * Returns whether a model was tracked as deleted since the before state was set.
     *
     * @param string $class
     * @param mixed  $key
     * @return bool
     */
    protected function wasModelDeleted(string $class, $key): bool
    {
        if ( ! array_key_exists($class, $this->deletedSinceBeforeState)) {
            return false;
        }

        return in_array($key, $this->deletedSinceBeforeState[ $class ]);
    }

    /**
     * Sets up listening for relevant model events.
     */
    protected function listenForEvents(): void
    {
        $this->events->listen(['eloquent.created: *'], function() {

            /** @var Model $model */
            if (func_num_args() > 1) {
                $model = head(func_get_arg(1));
            } else {
                $model = func_get_arg(0);
            }

            if ( ! $this->listening) {
                return;
            }

            $class = get_class($model);

            if ( ! array_key_exists($class, $this->createdSinceBeforeState)) {
                $this->createdSinceBeforeState[ $class ] = [];
            }

            $this->createdSinceBeforeState[ $class ][] = $model->getKey();
        });

        $this->events->listen(['eloquent.deleted: *'], function() {

            /** @var Model $model */
            if (func_num_args() > 1) {
                $model = head(func_get_arg(1));
            } else {
                $model = func_get_arg(0);
            }

            if ( ! $this->listening) {
                return;
            }

            $class = get_class($model);

            if ( ! array_key_exists($class, $this->deletedSinceBeforeState)) {
                $this->deletedSinceBeforeState[ $class ] = [];
            }

            $this->deletedSinceBeforeState[ $class ][] = $model->getKey();
        });

    }

    /**
     * Stops ignoring model events.
     *
     * @return $this
     */
    protected function startListening(): ComparerInterface
    {
        $this->listening = true;

        return $this;
    }

    /**
     * Starts ignoring model events (again).
     *
     * @return $this
     */
    protected function stopListening(): ComparerInterface
    {
        $this->listening = false;

        return $this;
    }

}
