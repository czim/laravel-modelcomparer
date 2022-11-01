<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyInterface;

class SimpleStrategy implements CompareStrategyInterface
{
    /**
     * {@inheritDoc}
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
