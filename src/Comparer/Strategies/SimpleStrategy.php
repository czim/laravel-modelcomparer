<?php

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyInterface;

class SimpleStrategy implements CompareStrategyInterface
{
    /**
     * Returns whether two values are equal.
     *
     * @param mixed $before
     * @param mixed $after
     * @param bool  $strict     whether to only consider strict sameness
     * @return bool
     */
    public function equal($before, $after, bool $strict = false): bool
    {
        if ($strict) {
            return $before === $after;
        }

        /** @noinspection TypeUnsafeComparisonInspection */
        return $before == $after;
    }
}
