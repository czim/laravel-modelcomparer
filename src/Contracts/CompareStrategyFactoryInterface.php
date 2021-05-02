<?php

namespace Czim\ModelComparer\Contracts;

interface CompareStrategyFactoryInterface
{
    /**
     * @param mixed $valueBefore
     * @param mixed $valueAfter
     * @return CompareStrategyInterface
     */
    public function make($valueBefore, $valueAfter): CompareStrategyInterface;
}
