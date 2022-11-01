<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer;

use Czim\ModelComparer\Contracts\ComparableDataTreeFactoryInterface;
use Czim\ModelComparer\Contracts\ComparerInterface;
use Czim\ModelComparer\Contracts\CompareStrategyFactoryInterface;
use Czim\ModelComparer\Data;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Renders insight in the difference of a model structure to any depth, depending on the (eager) loaded relation tree.
 * This helps generate clean and non-redundant changelogs for model updates.
 */
class Comparer implements ComparerInterface
{
    /**
     * Before state as a normalized tree.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $before;

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
    protected array|false $nestedCompareFully = false;

    /**
     * Whether comparison of values should be done strictly.
     * If not strict, filters out out potentially meaningless changes. (boolean false to 0, for instance).
     *
     * @var bool
     */
    protected bool $strictComparison = false;

    /**
     * Whether changes to model timestamps should be ignored.
     *
     * @var bool
     */
    protected bool $ignoreTimestamps = true;

    /**
     * A list of attributes to ignore per model.
     *
     * An array of arrays, keyed by model FQN.
     *
     * @var array<class-string<Model>, string[]>
     */
    protected array $ignoreAttributesPerModel = [];

    /**
     * Whether model events should be listened to.
     *
     * @var bool
     */
    protected bool $listening = false;

    /**
     * A list of lists of keys per model FQN, for models that were created since setting the last before state.
     *
     * @var array<int, array<class-string<Model>, string[]>>
     */
    protected array $createdSinceBeforeState = [];

    /**
     * A list of lists of keys per model FQN, for models that were deleted since setting the last before state.
     *
     * @var array<int, array<class-string<Model>, string[]>>
     */
    protected array $deletedSinceBeforeState = [];


    public function __construct(
        protected readonly EventDispatcher $events,
        protected readonly CompareStrategyFactoryInterface $strategyFactory,
        protected readonly ComparableDataTreeFactoryInterface $dataFactory,
    ) {
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
    public function ignoreTimestamps(bool $ignore = true): static
    {
        $this->ignoreTimestamps = $ignore;

        $this->dataFactory->ignoreTimestamps($ignore);

        return $this;
    }

    /**
     * Sets whether all comparison should be done strict.
     *
     * @param bool $strict
     * @return $this
     */
    public function useStrictComparison(bool $strict = true): static
    {
        $this->strictComparison = $strict;

        return $this;
    }

    /**
     * Sets comparisons to always be completed in full.
     *
     * @return $this
     */
    public function alwaysCompareFully(): static
    {
        $this->nestedCompareFully = false;

        $this->dataFactory->alwaysCompareFully();

        return $this;
    }

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.translations, article.articleSorts.translations ]
     *
     * @param array $compareFully
     * @return $this
     */
    public function setNestedCompareRelations(array $compareFully): static
    {
        $this->nestedCompareFully = $compareFully;

        $this->dataFactory->setNestedCompareRelations($compareFully);

        return $this;
    }

    /**
     * Sets a list of attributes per model.
     *
     * This overwrites all currently set ignores per model.
     *
     * @param array<class-string<Model>, string[]> $ignoredPerModel arrays with attribute names, keyed by model FQN
     * @return $this
     */
    public function setIgnoredAttributesForModels(array $ignoredPerModel): static
    {
        $this->ignoreAttributesPerModel = $ignoredPerModel;

        $this->dataFactory->setIgnoredAttributesForModels($ignoredPerModel);

        return $this;
    }

    /**
     * Sets a list of attributes to ignore for a given model.
     *
     * @param class-string<Model>|Model $model
     * @param string[]                  $ignored
     * @return $this
     */
    public function setIgnoredAttributesForModel(string|Model $model, array $ignored): static
    {
        if (is_object($model)) {
            $model = get_class($model);
        }

        $this->ignoreAttributesPerModel[ $model ] = $ignored;

        $this->dataFactory->setIgnoredAttributesForModel($model, $ignored);

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
    public function setBeforeState(Model $model): static
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
    public function clearBeforeState(): static
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
        if ($this->before === null) {
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
     * @param string|null $parent dot-notation parent chain
     * @return array<string, mixed>
     */
    protected function buildNormalizedArrayTree(Model $model, ?string $parent = null): array
    {
        return $this->dataFactory->make($model, $parent);
    }

    /**
     * Returns the difference object tree for a the model
     *
     * @param class-string<Model>|null   $modelClass
     * @param array<string, mixed>|mixed $before if not an array, model difference should not be tracked
     * @param array<string, mixed>|mixed $after  if not an array, model difference should not be tracked
     * @return Data\ModelDifference
     */
    protected function buildDifferenceTree(?string $modelClass, mixed $before, mixed $after): Data\ModelDifference
    {
        // If difference is ignored, don't build difference tree
        if (! is_array($before) || ! is_array($after)) {
            return new Data\ModelDifference(
                $modelClass ?? '',
                new Data\DifferenceCollection(),
                new Data\DifferenceCollection()
            );
        }

        // Check attributes
        // Filter out foreign key attributes
        $localForeignKeys = $this->collectLocalForeignKeys($before) + $this->collectLocalForeignKeys($after);

        $attributesBefore = Arr::except(Arr::get($before, 'attributes', []), $localForeignKeys);
        $attributesAfter  = Arr::except(Arr::get($after, 'attributes', []), $localForeignKeys);

        $attributeDiff = $this->buildAttributesDifferenceList($attributesBefore, $attributesAfter);

        // Check relations
        $relationDiff = $this->buildRelationsDifferenceList(
            Arr::get($before, 'relations', []),
            Arr::get($after, 'relations', [])
        );

        return new Data\ModelDifference($modelClass ?? '', $attributeDiff, $relationDiff);
    }

    /**
     * Builds difference object for pivot record's attributes.
     *
     * @param array<string, mixed>|mixed $before if not an array, model difference should not be tracked
     * @param array<string, mixed>|mixed $after  if not an array, model difference should not be tracked
     * @return Data\PivotDifference
     */
    protected function buildPivotDifference(mixed $before, mixed $after): Data\PivotDifference
    {
        // If ignored, return empty difference.
        if (! is_array($before) || ! is_array($after)) {
            return new Data\PivotDifference(new Data\DifferenceCollection());
        }

        return new Data\PivotDifference(
            $this->buildAttributesDifferenceList($before, $after)
        );
    }

    /**
     * Builds difference collection with attribute differences.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return Data\DifferenceCollection
     */
    protected function buildAttributesDifferenceList(array $before, array $after): Data\DifferenceCollection
    {
        $differences = new Data\DifferenceCollection();

        foreach ($before as $key => $value) {

            // If the key does not exist in the new array.
            if (! array_key_exists($key, $after)) {
                $differences->put($key, new Data\AttributeDifference($value, false, false, true));
                continue;
            }

            // If the key does exist, check if the value is difference.
            if (! $this->isAttributeValueEqual($value, $after[ $key ])) {
                $differences->put($key, new Data\AttributeDifference($value, $after[ $key ]));
            }
        }

        // List differences for newly added keys not present before.
        foreach (array_diff(array_keys($after), array_keys($before)) as $key) {
            $differences->put(
                $key,
                new Data\AttributeDifference(false, $after[ $key ], true)
            );
        }

        return $differences;
    }

    /**
     * Builds difference collection with relation differences.
     *
     * This may recursively create nested model difference objects.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return Data\DifferenceCollection
     */
    protected function buildRelationsDifferenceList(array $before, array $after): Data\DifferenceCollection
    {
        $differences = new Data\DifferenceCollection();

        foreach ($before as $key => $value) {
            // Only treat differences for relations present before & after.
            if (! array_key_exists($key, $after)) {
                continue;
            }

            $difference = $this->getRelationDifference($value, $after[ $key ]);

            if ($difference === false) {
                continue;
            }

            $differences->put($key, $difference);
        }

        return $differences;
    }

    /**
     * Returns a relation difference object for potentially changed relation data.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return Data\AbstractRelationDifference|false false if the data is 100% the same.
     */
    protected function getRelationDifference(array $before, array $after): Data\AbstractRelationDifference|false
    {
        $method = Arr::get($before, 'method');
        $type   = Arr::get($before, 'type');
        $single = Arr::get($before, 'single', true);
        $morph  = Arr::get($before, 'morph', true);
        $pivot  = Arr::get($before, 'pivot', false);

        $beforeItems = Arr::get($before, 'items', []);
        $afterItems  = Arr::get($after, 'items', []);

        if ($single) {
            if (! count($beforeItems) && ! count($afterItems)) {
                // Nothing changed, still unconnected.
                return false;
            }

            if (! count($beforeItems)) {
                $key = head(array_keys($afterItems));

                [$key, $class] = $this->getKeyAndClassFromReference($key, $morph);

                $difference = null;

                // It may be the case that the items list is just a list of keys, not an array with nested data.
                // This occurs when the relationship is considered outside of the scope of nested compare relations.
                if (is_scalar($afterItems[ $key ])) {
                    $modelClass = null;
                } else {
                    $modelClass = $class ?: $afterItems[ $key ]['class'];
                }

                if ($modelClass !== null && $this->wasModelCreated($modelClass, $key)) {
                    $difference = new Data\ModelCreatedDifference(
                        $modelClass,
                        $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $key ], 'attributes', [])),
                        new Data\DifferenceCollection()
                    );
                }

                $difference = new Data\RelatedAddedDifference($key, $class, $difference);
            } elseif (! count($afterItems)) {
                $key = head(array_keys($beforeItems));

                [$key, $class] = $this->getKeyAndClassFromReference($key, $morph);

                // It may be the case that the items list is just a list of keys, not an array with nested data.
                if (is_scalar($beforeItems[ $key ])) {
                    $modelClass = null;
                } else {
                    $modelClass = $class ?: $beforeItems[ $key ]['class'];
                }

                $deleted = $modelClass !== null && $this->wasModelDeleted($modelClass, $key);

                $difference = new Data\RelatedRemovedDifference($key, $class, $deleted);
            } else {
                $keyBefore = head(array_keys($beforeItems));
                $keyAfter  = head(array_keys($afterItems));

                if ($keyBefore === $keyAfter) {
                    // It may be the case that the items list is just a list of keys, not an array with nested data.
                    // In that case there is no difference.
                    if (is_scalar($beforeItems[ $keyBefore ])) {
                        return false;
                    }

                    // The same model is still related, but it may be altered
                    $modelClass = $afterItems[ $keyAfter ]['class'];

                    $difference = $this->buildDifferenceTree(
                        $modelClass,
                        $beforeItems[ $keyBefore ],
                        $afterItems[ $keyAfter ]
                    );

                    if (! $difference->isDifferent()) {
                        return false;
                    }

                    [$keyOnlyBefore, $classBefore] = $this->getKeyAndClassFromReference($keyBefore, $morph);

                    $difference = new Data\RelatedChangedDifference($keyOnlyBefore, $classBefore, $difference);
                } else {
                    // The model related before was replaced by another
                    [$keyOnlyBefore, $classBefore] = $this->getKeyAndClassFromReference($keyBefore, $morph);
                    [$keyOnlyAfter, $classAfter]   = $this->getKeyAndClassFromReference($keyAfter, $morph);

                    // It may be the case that the items list is just a list of keys, not an array with nested data.
                    if (is_scalar($afterItems[ $keyAfter ])) {
                        $modelClass = null;
                    } else {
                        $modelClass = $classAfter ?: $afterItems[ $keyAfter ]['class'];
                    }

                    // If the newly added model was created, track this as the difference
                    if ($modelClass !== null && $this->wasModelCreated($modelClass, $keyOnlyAfter)) {
                        $difference = new Data\ModelCreatedDifference(
                            $classAfter ?: $modelClass,
                            $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $keyAfter ], 'attributes', [])),
                            new Data\DifferenceCollection()
                        );
                    } else {
                        $difference = new Data\ModelDifference(
                            ($classAfter ?: $modelClass) ?? '',
                            new Data\DifferenceCollection(),
                            new Data\DifferenceCollection()
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

        // Plural relations.

        $differences = new Data\DifferenceCollection();

        if (! count($beforeItems) && ! count($afterItems)) {
            // Nothing changed, still 0 connections.
            return false;
        }

        // Find relations that are no longer present.
        $removedKeys = array_diff(array_keys($beforeItems), array_keys($afterItems));

        foreach ($removedKeys as $key) {
            [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

            if (is_scalar($beforeItems[ $key ])) {
                $modelClass = null;
            } else {
                $modelClass = $class ?: $beforeItems[ $key ]['class'];
            }

            $deleted = $modelClass !== null && $this->wasModelDeleted($modelClass, $keyOnly);

            $differences->put($key, new Data\RelatedRemovedDifference($keyOnly, $class, $deleted));
        }

        // Find relations that are newly present.
        $addedKeys = array_diff(array_keys($afterItems), array_keys($beforeItems));

        foreach ($addedKeys as $key) {
            [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

            if (is_scalar($afterItems[ $key ])) {
                $modelClass = null;
            } else {
                $modelClass = $class ?: $afterItems[ $key ]['class'];
            }

            $difference = null;

            if ($modelClass !== null && $this->wasModelCreated($modelClass, $keyOnly)) {
                $difference = new Data\ModelCreatedDifference(
                    $modelClass,
                    $this->buildAttributesDifferenceList([], Arr::get($afterItems[ $keyOnly ], 'attributes', [])),
                    new Data\DifferenceCollection()
                );
            }

            $differences->put($key, new Data\RelatedAddedDifference($keyOnly, $class, $difference));
        }

        // Check for changes on previously related models.
        $relatedKeys = array_intersect(array_keys($beforeItems), array_keys($afterItems));

        foreach ($relatedKeys as $key) {
            if (is_scalar($beforeItems[ $key ])) {
                $modelClass = null;
            } else {
                $modelClass = $beforeItems[ $key ]['class'];
            }

            $difference      = $this->buildDifferenceTree($modelClass, $beforeItems[ $key ], $afterItems[ $key ]);
            $pivotDifference = null;

            // For pivot-based relations, check the pivot differences
            if ($pivot && ! is_scalar($beforeItems[ $key ])) {
                $pivotDifference =
                    $this->buildPivotDifference($beforeItems[ $key ]['pivot'], $afterItems[ $key ]['pivot']);
            }

            if ($difference->isDifferent() || $pivotDifference && $pivotDifference->isDifferent()) {
                [$keyOnly, $class] = $this->getKeyAndClassFromReference($key, $morph);

                $differences->put($key, new Data\RelatedChangedDifference($keyOnly, $class, $difference, $pivotDifference));
            }
        }

        // If no actual (unignored) differences are found, the entire relation is unchanged.
        if (! count($differences)) {
            return false;
        }

        return new Data\PluralRelationDifference($method, $type, $differences);
    }


    /**
     * @param string|int $key
     * @param bool       $morph
     * @return array{string, class-string<Model>|null} key, model class
     */
    protected function getKeyAndClassFromReference(string|int $key, bool $morph = false): array
    {
        $class = null;

        if (! $morph) {
            return [$key, $class];
        }

        return explode(':', $key, 2);
    }

    /**
     * Returns whether two values are the same.
     *
     * @param mixed $before
     * @param mixed $after
     * @return bool|null  null if the difference is non-strict.
     */
    protected function isAttributeValueEqual(mixed $before, mixed $after): ?bool
    {
        $strategyInstance = $this->strategyFactory->make($before, $after);

        return $strategyInstance->equal($before, $after, $this->strictComparison);
    }

    /**
     * Returns list of all local foreign keys marked in a normalized state array.
     *
     * @param array<string, mixed> $tree
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
        $this->before                  = null;
        $this->createdSinceBeforeState = [];
        $this->deletedSinceBeforeState = [];
    }

    /**
     * Returns whether a model was tracked as created since the before state was set.
     *
     * @param class-string<Model> $class
     * @param mixed               $key
     * @return bool
     */
    protected function wasModelCreated(string $class, mixed $key): bool
    {
        if (! array_key_exists($class, $this->createdSinceBeforeState)) {
            return false;
        }

        return in_array($key, $this->createdSinceBeforeState[ $class ]);
    }

    /**
     * Returns whether a model was tracked as deleted since the before state was set.
     *
     * @param class-string<Model> $class
     * @param mixed               $key
     * @return bool
     */
    protected function wasModelDeleted(string $class, mixed $key): bool
    {
        if (! array_key_exists($class, $this->deletedSinceBeforeState)) {
            return false;
        }

        return in_array($key, $this->deletedSinceBeforeState[ $class ]);
    }

    /**
     * Sets up listening for relevant model events.
     */
    protected function listenForEvents(): void
    {
        $this->events->listen(['eloquent.created: *'], function (): void {
            if (func_num_args() > 1) {
                /** @var Model $model */
                $model = head(func_get_arg(1));
            } else {
                /** @var Model $model */
                $model = func_get_arg(0);
            }

            if (! $this->listening) {
                return;
            }

            $class = get_class($model);

            if (! array_key_exists($class, $this->createdSinceBeforeState)) {
                $this->createdSinceBeforeState[ $class ] = [];
            }

            $this->createdSinceBeforeState[ $class ][] = $model->getKey();
        });


        $this->events->listen(['eloquent.deleted: *'], function (): void {
            if (func_num_args() > 1) {
                /** @var Model $model */
                $model = head(func_get_arg(1));
            } else {
                /** @var Model $model */
                $model = func_get_arg(0);
            }

            if (! $this->listening) {
                return;
            }

            $class = get_class($model);

            if (! array_key_exists($class, $this->deletedSinceBeforeState)) {
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
    protected function startListening(): static
    {
        $this->listening = true;

        return $this;
    }

    /**
     * Starts ignoring model events (again).
     *
     * @return $this
     */
    protected function stopListening(): static
    {
        $this->listening = false;

        return $this;
    }
}
