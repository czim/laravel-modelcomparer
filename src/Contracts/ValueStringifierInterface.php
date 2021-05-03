<?php

namespace Czim\ModelComparer\Contracts;

interface ValueStringifierInterface
{
    /**
     * @param mixed       $value
     * @param string|null $wrap
     * @return string
     */
    public function make($value, ?string $wrap = '"'): string;
}
