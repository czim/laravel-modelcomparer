<?php
namespace Czim\ModelComparer\Comparer;

use Czim\ModelComparer\Data\AbstractRelationDifference;
use Czim\ModelComparer\Data\AttributeDifference;
use Czim\ModelComparer\Data\DifferenceCollection;
use Czim\ModelComparer\Data\ModelDifference;
use Czim\ModelComparer\Data\RelatedAddedDifference;
use Czim\ModelComparer\Data\RelatedRemovedDifference;
use Czim\ModelComparer\Data\SingleRelationDifference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
 * @todo
 *  Ideally you would get the keys that were altered and there before/after
 *  state: 'model.relation.attribute' => [ 'before' => X, 'after' => Y ]
 *
 *  This could include logic like: a before but no after = deleted, or
 *  the reverse = created.
 *
 *  This should be within the scope of a (related) model for updated
 *  models that already existed:
 *      model => relation => first related item => [ attributeA => [ before, after ], etc ]
 *
 *  Also, consider whether flattened would be better:
 *      model.relation.0.attributeA => [ before, after ]
 *  A bonus for this: it would be easy to make 'keys to ignore' list with wildcards,
 *  Then filter the difference array (by key) for these.
 *
 */
class Comparer
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
     * @var bool
     */
    protected $ignoreTimestamps = true;


    // todo:
    //[
    //    'translations',
    //    'articleSorts',
    //    'articleSorts.translations',
    //    // anything not in here gets compared by link/id only
    //];

    /**
     * Sets comparisons to always be completed in full.
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
     */
    public function setNestedCompareRelations(array $compareFully)
    {
        $this->nestedCompareFully = $compareFully;
    }

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

        $difference = $this->buildDifferenceTree($model, $this->before, $after);

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
        $relationKeys = array_keys($model->relationsToArray());
        $relationTree = [];

        // Keep a list of foreign keys for BelongsTo, MorphTo relations
        $localForeignKeys = [];

        foreach ($relationKeys as $relationKey) {

            /** @var Relation $relationInstance */
            $relationName     = camel_case($relationKey);
            $relationInstance = $model->{$relationName}();
            $nestedParent     = ($parent ? $parent . '.' : '') . $relationName;

            $isSingle = $this->isSingleRelation($relationInstance);
            $isMorph  = $this->isMorphTo($relationInstance);

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
                'items'        => $items,
                'foreign_keys' => $foreignKeys,
            ];

            // todo: list pivot attributes
            // ignoring timestamps if configured
        }

        // Handle attributes of this model itself
        $attributes = array_except($model->attributesToArray(), $localForeignKeys);

        // todo: filter configured ignored keys
        if ($this->ignoreTimestamps && $model->timestamps) {
            $attributes = array_except($attributes, [ $model::CREATED_AT, $model::UPDATED_AT ]);
        }

        return [
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
     * Returns the difference object tree for a the model
     *
     * @param Model $model
     * @param array $before
     * @param array $after
     * @return ModelDifference
     */
    protected function buildDifferenceTree(Model $model, array $before, array $after)
    {
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

        return new ModelDifference(get_class($model), $attributeDiff, $relationDiff);
    }

    /**
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
            if ( ! $this->isValueEqual($value, $after[ $key ])) {
                $differences->put($key, $this->getAttributeDifference($value, $after[ $key ]));
            }
        }

        // List differences for newly added keys not present before
        foreach (array_diff(array_keys($after), array_keys($before)) as $key) {
            $differences->put($key, new AttributeDifference(false, $after[$key], true));
        }

        return $differences;
    }

    /**
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
     * Returns an attribute difference object for a changed attribute value.
     *
     * @param mixed $before
     * @param mixed $after
     * @return AttributeDifference
     */
    protected function getAttributeDifference($before, $after)
    {
        $diff = new AttributeDifference($before, $after);

        $diff->setIgnored($this->isChangeToBeIgnored($before, $after));
        $diff->setRealChange($this->isRealChange($before, $after));

        return $diff;
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

        $beforeItems = array_get($before, 'items', []);
        $afterItems  = array_get($after, 'items', []);

        if ($single) {

            if ( ! count($beforeItems) && ! count($afterItems)) {
                // Nothing changed, still unconnected
                return false;

            } elseif ( ! count($beforeItems)) {

                $key       = head(array_keys($afterItems));
                $class     = null;

                if ($morph) {
                    list($key, $class) = explode(':', $key, 2);
                }

                $difference = new RelatedAddedDifference($key, $class);

            } elseif ( ! count($afterItems)) {

                $key   = head(array_keys($beforeItems));
                $class = null;

                if ($morph) {
                    list($key, $class) = explode(':', $key, 2);
                }

                $difference = new RelatedRemovedDifference($key, $class);

            } else {

                // See if the connection was changed
                // or if not, whether the related model was changed
                return false;
            }

            return new SingleRelationDifference($method, $type, $difference);
        }

        // Plural relations

        // todo: list removed, added and changed
        
        return false;
    }

    /**
     * Returns whether two values are the same.
     *
     * @param mixed $before
     * @param mixed $after
     * @return true|false|null  null if the difference is only loose.
     */
    protected function isValueEqual($before, $after)
    {
        // todo: do array and special object comparison

        if ($before === $after) {
            return true;
        }

        if ($before == $after) {
            return null;
        }

        return false;
    }

    /**
     * Returns whether the change between two values should be ignored for the difference tree.
     *
     * @param mixed $before
     * @param mixed $after
     * @return bool
     */
    protected function isChangeToBeIgnored($before, $after)
    {
        return null === $this->isValueEqual($before, $after);
    }

    /**
     * Returns whether the change is a 'real' change.
     *
     * @param mixed $before
     * @param mixed $after
     * @return bool
     */
    protected function isRealChange($before, $after)
    {
        return ! $this->isValueEqual($before, $after);
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
