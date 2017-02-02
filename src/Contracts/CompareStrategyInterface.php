<?php
namespace Czim\ModelComparer\Contracts;

interface CompareStrategyInterface
{

    /**
     * Returns whether two values are equal.
     *
     * @param mixed $before
     * @param mixed $after
     * @param bool  $strict     whether to only consider strict sameness
     * @return bool
     */
    public function equal($before, $after, $strict = false);

}
