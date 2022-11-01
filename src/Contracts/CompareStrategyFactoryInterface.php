<?php

namespace Czim\ModelComparer\Contracts;

interface CompareStrategyFactoryInterface
{
    public function make(mixed $valueBefore, mixed $valueAfter): CompareStrategyInterface;
}
