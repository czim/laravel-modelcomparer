<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * Difference rapport on a pivot table for an Eloquent relation.
 */
class PivotDifference implements Arrayable, Jsonable
{
    use ToArrayJsonable;

    /**
     * @param DifferenceCollection<AttributeDifference> $attributes
     */
    public function __construct(protected readonly DifferenceCollection $attributes)
    {
    }

    /**
     * Returns whether there are any differences at all.
     *
     * @return bool
     */
    public function isDifferent(): bool
    {
        return count($this->attributes) > 0;
    }

    /**
     * @return DifferenceCollection<AttributeDifference>
     */
    public function attributes(): DifferenceCollection
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! count($this->attributes)) {
            return [];
        }

        return array_map(
            static fn (AttributeDifference $item): string => (string) $item,
            $this->attributes->toArray()
        );
    }
}
