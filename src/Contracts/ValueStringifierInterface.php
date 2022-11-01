<?php

namespace Czim\ModelComparer\Contracts;

interface ValueStringifierInterface
{
    public function make(mixed $value, ?string $wrap = '"'): string;
}
