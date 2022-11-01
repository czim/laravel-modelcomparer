<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Traits;

use Illuminate\Contracts\Support\Jsonable;
use RuntimeException;

/**
 * @see Jsonable
 */
trait ToArrayJsonable
{
    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string|false
     */
    public function toJson($options = 0): string|false
    {
        if (! method_exists($this, 'toArray')) {
            throw new RuntimeException('Can only use ToArrayJsonable on Arrayable object');
        }

        return json_encode($this->toArray(), $options);
    }
}
