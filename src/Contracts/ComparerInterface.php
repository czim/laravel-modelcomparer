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
    public function ignoreTimestamps($ignore = true);

    /**
     * Sets whether all comparison should be done strict.
     *
     * @param bool $strict
     * @return $this
     */
    public function useStrictComparison($strict = true);

    /**
     * Sets comparisons to always be completed in full.
     *
     * @return $this
     */
    public function alwaysCompareFully();

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.transations, article.articleSorts.translations ]
     *
     * @param array $compareFully
     * @return $this
     */
    public function setNestedCompareRelations(array $compareFully);


    /**
     * Sets the before state to be compared with an after state later.
     *
     * @param Model $model
     * @return $this
     */
    public function setBeforeState(Model $model);

    /**
     * Clears any previously set before state.
     *
     * @return $this
     */
    public function clearBeforeState();

    /**
     * Compares the earlier set before state with a new after state.
     *
     * @param Model $model
     * @return ModelDifference
     */
    public function compareWithBefore(Model $model);

}
