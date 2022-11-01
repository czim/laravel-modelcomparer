<?php
namespace Czim\ModelComparer\Contracts;

use Czim\ModelComparer\Data\ModelDifference;
use Illuminate\Database\Eloquent\Model;

interface ComparerInterface
{

    /**
     * Sets whether the comparer should ignore all timestamp attributes.
     *
     * @param bool $ignore
     * @return $this
     */
    public function ignoreTimestamps(bool $ignore = true): ComparerInterface;

    /**
     * Sets whether all comparison should be done strict.
     *
     * @param bool $strict
     * @return $this
     */
    public function useStrictComparison(bool $strict = true): ComparerInterface;

    /**
     * Sets comparisons to always be completed in full.
     *
     * @return $this
     */
    public function alwaysCompareFully(): ComparerInterface;

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.transations, article.articleSorts.translations ]
     *
     * @param array $compareFully
     * @return $this
     */
    public function setNestedCompareRelations(array $compareFully): ComparerInterface;

    /**
     * Sets a list of attributes per model.
     *
     * This overwrites all currently set ignores per model.
     *
     * @param array $ignoredPerModel    array of arrays with attribute name strings, keyed by model FQN
     * @return $this
     */
    public function setIgnoredAttributesForModels(array $ignoredPerModel);

    /**
     * Sets a list of attributes to ignore for a given model.
     *
     * @param class-string<Model>|Model $model
     * @param string[]                  $ignored
     * @return $this
     */
    public function setIgnoredAttributesForModel(string|Model $model, array $ignored): ComparerInterface;


    /**
     * Sets the before state to be compared with an after state later.
     *
     * @param Model $model
     * @return $this
     */
    public function setBeforeState(Model $model): ComparerInterface;

    /**
     * Clears any previously set before state.
     *
     * @return $this
     */
    public function clearBeforeState(): ComparerInterface;

    /**
     * Compares the earlier set before state with a new after state.
     *
     * @param Model $model
     * @return ModelDifference
     */
    public function compareWithBefore(Model $model): ModelDifference;

}
