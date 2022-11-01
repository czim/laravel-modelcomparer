<?php

namespace Czim\ModelComparer\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ComparableDataTreeFactoryInterface
{
    /**
     * @param Model       $model
     * @param string|null $parent
     * @return array<string, mixed>
     */
    public function make(Model $model, ?string $parent = null): array;

    /**
     * Sets comparisons to always be completed in full.
     */
    public function alwaysCompareFully(): void;

    /**
     * Set relation dot-notation strings for relations to fully compare recursively.
     *
     * Ex.:
     *      [ article.translations, article.articleSorts.translations ]
     *
     * @param string[] $compareFully
     */
    public function setNestedCompareRelations(array $compareFully): void;

    /**
     * Sets whether the comparer should ignore all timestamp attributes.
     *
     * @param bool $ignore
     */
    public function ignoreTimestamps(bool $ignore = true): void;

    /**
     * Sets a list of attributes per model.
     *
     * This overwrites all currently set ignores per model.
     *
     * @param array<class-string<Model>, string[]> $ignoredPerModel arrays with attribute names, keyed by model FQN
     */
    public function setIgnoredAttributesForModels(array $ignoredPerModel): void;

    /**
     * Sets a list of attributes to ignore for a given model.
     *
     * @param class-string<Model> $modelClass
     * @param string[]            $ignored
     */
    public function setIgnoredAttributesForModel(string $modelClass, array $ignored): void;
}
