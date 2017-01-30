<?php
namespace Czim\ModelComparer\Comparer;

use Czim\ModelComparer\Contracts\ComparerInterface;
use Czim\ModelComparer\Data\AbstractRelationDifference;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\DifferenceCollection;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\PivotDifference;
use Czim\ModelComparer\Data\PluralRelationDifference;
use Czim\ModelComparer\Data\RelatedAddedDifference;
use Czim\ModelComparer\Data\RelatedChangedDifference;
use Czim\ModelComparer\Data\RelatedRemovedDifference;
use Czim\ModelComparer\Data\RelatedReplacedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
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
     * Whether comparison of values should be done loosely, to filter out potentially meaningless changes.
     * (boolean false to 0, for instance).
     *
     * @var bool
     */
    protected $loosyValueComparison = true;

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


    // ------------------------------------------------------------------------------
    //      Configuration
    // ------------------------------------------------------------------------------

    /**
     * Sets whether the comparer should ignore all timestamp attributes.
     *
     * @param bool $ignore
     * @return $this
     */
    public function ignoreTimestamps($ignore = true)
    {
        $this->ignoreTimestamps = (bool) $ignore;

        return $this;
    }

    /**
     * Sets whether all comparison should be done strict.
     *
     * @param bool $strict
     * @return $this
     */
    public function useStrictComparison($strict = true)
    {
        $this->loosyValueComparison = ! $strict;

        return $this;
    }

    /**
     * Sets comparisons to always be completed in full.
     *
     * @return $this
     */
    public function alwaysCompareFully()
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
    public function setNestedCompareRelations(array $compareFully)
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
    public function setIgnoredAttributesForModels(array $ignoredPerModel)
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
    public function setIgnoredAttributesForModel($model, array $ignored)
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
    public function setBeforeState(Model $model)
    {
        $this->before = $this->buildNormalizedArrayTree($model);

        return $this;
    }

    /**
     * Clears any previously set before state.
     *
     * @return $this
     */
    public function clearBeforeState()
    {
        $this->before = null;

        return $this;
    }

    /**
     * Compares the earlier set before state with a new after state.
     *
     * @param Model $model
     * @return ModelDifference
     */
    public function compareWithBefore(Model $model)
    {
        if (null === $this->before) {
            throw new RuntimeException("No before state was set for comparison");
        }

        $after = $this->buildNormalizedArrayTree($model);

        $difference = $this->buildDifferenceTree(get_class($model), $this->before, $after);

        return $difference;
    }



    /**
     * Builds a normalized tree that is ready for comparison-usage.
     *
     * @param Model       $model
     * @param null|string $parent       dot-notation parent chain
     * @return array
     */
    protected function buildNormalizedArrayTree(Model $model, $parent = null)
    {
        // Analyze relations, build nested tree
        $relationKeys = array_keys($model->getRelations());
        $relationTree = [];
        $pivot = [];

        // Keep a list of foreign keys for BelongsTo, MorphTo relations
        $localForeignKeys = [];

        foreach ($relationKeys as $relationKey) {

            if (    $relationKey == 'pivot'
                &&  (   ($pivotObject = array_get($model->getRelations(), 'pivot')) instanceof Pivot
                    ||  ($pivotObject = array_get($model->getRelations(), 'pivot')) instanceof MorphPivot
                    )
            ) {
                /** @var MorphPivot $pivotObject */
                // todo: get morph type keys

                /** @var Pivot $pivotObject */
                $pivotKeys = array_filter([
                    $pivotObject->getKey(),
                    $pivotObject->getOtherKey(),
                    $pivotObject->getForeignKey(),
                ]);

                if ($pivotObject->hasTimestampAttributes()) {
                    $pivotKeys[] = $pivotObject->getCreatedAtColumn();
                    $pivotKeys[] = $pivotObject->getUpdatedAtColumn();
                }

                $pivotAttributes = array_except($pivotObject->attributesToArray(), $pivotKeys);

                if (count($pivotAttributes)) {
                    $pivot = $pivotAttributes;
                }

                continue;
            }

            /** @var Relation $relationInstance */
            $relationName     = camel_case($relationKey);
            $relationInstance = $model->{$relationName}();
            $nestedParent     = ($parent ? $parent . '.' : '') . $relationName;

            $isSingle = $this->isSingleRelation($relationInstance);
            $isMorph  = $this->isMorphTo($relationInstance);
            $hasPivot = $this->hasPivotTable($relationInstance);

            $foreignKeys = [];

            if ($relationInstance instanceof BelongsTo) {
                $foreignKeys[] = $relationInstance->getForeignKey();
            } elseif ($relationInstance instanceof MorphTo) {
                $foreignKeys[] = $relationInstance->getForeignKey();
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
                    &&  ! in_array($nestedParent, $this->nestedCompareFully)
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

        $attributes = array_except($model->attributesToArray(), $ignoreKeys);

        return [
            'class'      => get_class($model),
            'pivot'      => $pivot,
            'attributes' => $attributes,
            'relations'  => $relationTree,
        ];
    }

    /**
     * @param Relation $relation
     * @return bool
     */
    protected function isSingleRelation(Relation $relation)
    {
        return  (   $relation instanceof BelongsTo
                ||  $relation instanceof HasOne
                ||  $relation instanceof MorphTo
                ||  $relation instanceof MorphOne
                );
    }

    /**
     * @param Relation $relation
     * @return bool
     */
    protected function isMorphTo(Relation $relation)
    {
        return $relation instanceof MorphTo;
    }

    /**
     * @param Relation $relation
     * @return bool
     */
    protected function hasPivotTable(Relation $relation)
    {
        return  $relation instanceof BelongsToMany
            ||  $relation instanceof MorphToMany;
    }

    /**
     * Returns the difference object tree for a the model
     *
     * @param string      $modelClass
     * @param array|mixed $before       if not an array, model difference should not be tracked
     * @param array|mixed $after        if not an array, model difference should not be tracked
     * @return ModelDifference
     */
    protected function buildDifferenceTree($modelClass, $before, $after)
    {
        // If difference is ignored, don't build difference tree
        if ( ! is_array($before) || ! is_array($after)) {
            return new ModelDifference($modelClass, new DifferenceCollection, new DifferenceCollection);
        }

        // Check attributes
        // Filter out foreign key attributes
        $localForeignKeys = $this->collectLocalForeignKeys($before)
                          + $this->collectLocalForeignKeys($after);

        $attributesBefore = array_except(array_get($before, 'attributes', []), $localForeignKeys);
        $attributesAfter  = array_except(array_get($after, 'attributes', []), $localForeignKeys);

        $attributeDiff = $this->buildAttributesDifferenceList($attributesBefore, $attributesAfter);

        // Check relations
        $relationDiff = $this->buildRelationsDifferenceList(
            array_get($before, 'relations', []),
            array_get($after, 'relations', [])
        );

        return new ModelDifference($modelClass, $attributeDiff, $relationDiff);
    }

    /**
     * Builds difference object for pivot record's attributes.
     *
     * @param array|mixed $before   if not an array, model difference should not be tracked
     * @param array|mixed $after    if not an array, model difference should not be tracked
     * @return PivotDifference
     */
    protected function buildPivotDifference($before, $after)
    {
        // If ignored, return empty difference
        if ( ! is_array($before) || ! is_array($after)) {
            return new PivotDifference(new DifferenceCollection);
        }

        return new PivotDifference(
            $this->buildAttributesDifferenceList($before, $after)
        );
    }

    /**
     * Builds difference collection with attribute differences.
     *
     * @param array $before
     * @param array $after
     * @return DifferenceCollection
     */
    protected function buildAttributesDifferenceList(array $before, array $after)
    {
        $differences = new DifferenceCollection;

        foreach ($before as $key => $value) {

            // If the key does not exist in the new array
            if ( ! array_key_exists($key, $after)) {
                $differences->put($key, new AttributeDifference($value, false, false, true));
                continue;
            }

            // If the key does exist, check if the value is difference
            if ( ! $this->isAttributeValueEqual($value, $after[ $key ])) {
                $differences->put($key, new AttributeDifference($value, $after[ $key ]));
            }
        }

        // List differences for newly added keys not present before
        foreach (array_diff(array_keys($after), array_keys($before)) as $key) {
            $differences->put($key, new AttributeDifference(false, $after[$key], true));
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
     * @return DifferenceCollection
     */
    protected function buildRelationsDifferenceList(array $before, array $after)
    {
        $differences = new DifferenceCollection;

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
     * @return AbstractRelationDifference|false     false if the data is 100% the same.
     */
    protected function getRelationDifference(array $before, array $after)
    {
        $method = array_get($before, 'method');
        $type   = array_get($before, 'type');
        $single = array_get($before, 'single', true);
        $morph  = array_get($before, 'morph', true);
        $pivot  = array_get($before, 'pivot', false);

        $beforeItems = array_get($before, 'items', []);
        $afterItems  = array_get($after, 'items', []);

        if ($single) {

            if ( ! count($beforeItems) && ! count($afterItems)) {
                // Nothing changed, still unconnected
                return false;
            }

            if ( ! count($beforeItems)) {

                $key = head(array_keys($afterItems));
                list($key, $class) = $this->getKeyAndClassFromReference($key, $morph);

                $difference = new RelatedAddedDifference($key, $class);

            } elseif ( ! count($afterItems)) {

                $key = head(array_keys($beforeItems));
                list($key, $class) = $this->getKeyAndClassFromReference($key, $morph);

                $difference = new RelatedRemovedDifference($key, $class);

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

                    list($keyOnlyBefore, $classBefore) = $this->getKeyAndClassFromReference($keyBefore, $morph);

                    $difference = new RelatedChangedDifference($keyOnlyBefore, $classBefore, $difference);

                } else {
                    // The model related before was replaced by another
                    list($keyOnlyBefore, $classBefore) = $this->getKeyAndClassFromReference($keyBefore, $morph);
                    list($keyOnlyAfter, $classAfter)   = $this->getKeyAndClassFromReference($keyAfter, $morph);

                    $difference = new RelatedReplacedDifference(
                        $keyOnlyAfter,
                        $classAfter,
                        new ModelDifference(
                            $classAfter ?: $afterItems[ $keyAfter ]['class'],
                            new DifferenceCollection,
                            new DifferenceCollection
                        ),
                        $keyOnlyBefore,
                        $classBefore
                    );
                }
            }

            return new SingleRelationDifference($method, $type, $difference);
        }

        // Plural relations

        $differences = new DifferenceCollection;

        if ( ! count($beforeItems) && ! count($afterItems)) {
            // Nothing changed, still 0 connections
            return false;
        }

        // Find relations that are no longer present
        $removedKeys = array_diff(array_keys($beforeItems), array_keys($afterItems));
        foreach ($removedKeys as $key) {

            list($keyOnly, $class) = $this->getKeyAndClassFromReference($key, $morph);

            $differences->put($key, new RelatedRemovedDifference($keyOnly, $class));
        }

        // Find relations that are newly present
        $addedKeys = array_diff(array_keys($afterItems), array_keys($beforeItems));
        foreach ($addedKeys as $key) {

            list($keyOnly, $class) = $this->getKeyAndClassFromReference($key, $morph);

            $differences->put($key, new RelatedAddedDifference($keyOnly, $class));
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

                list($keyOnly, $class) = $this->getKeyAndClassFromReference($key, $morph);

                $differences->put($key, new RelatedChangedDifference($keyOnly, $class, $difference, $pivotDifference));
            }
        }

        // If no actual (unignored) differences are found, the entire relation is unchanged.
        if ( ! count($differences)) {
            return false;
        }

        return new PluralRelationDifference($method, $type, $differences);
    }


    /**
     * @param string $key
     * @param bool   $morph
     * @return array    key, model class
     */
    protected function getKeyAndClassFromReference($key, $morph = false)
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
     * @return true|false|null  null if the difference is only loose.
     */
    protected function isAttributeValueEqual($before, $after)
    {
        // todo: do array and special object comparison
        // todo: strategy-based comparison, only count real & unignored changes

        if ($before === $after) {
            return true;
        }

        if ($this->loosyValueComparison && $before == $after) {
            return true;
        }

        return false;
    }

    /**
     * Returns list of all local foreign keys marked in a normalized state array.
     *
     * @param array $tree
     * @return string[]
     */
    protected function collectLocalForeignKeys(array $tree)
    {
        $keys = [];

        foreach ($tree['relations'] as $relation) {
            $keys += array_get($relation, 'foreign_keys', []);
        }

        return array_unique($keys);
    }

}
