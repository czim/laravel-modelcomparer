<?php
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
     * Differences in model attributes.
     *
     * @var DifferenceCollection|AttributeDifference[]
     */
    protected $attributes;


    public function __construct(DifferenceCollection $attributes)
    {
        $this->attributes = $attributes;
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
     * @return AttributeDifference[]|DifferenceCollection
     */
    public function attributes(): DifferenceCollection
    {
        return $this->attributes;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        if ( ! count($this->attributes)) {
            return [];
        }

        return array_map(
            static function (AttributeDifference $item) {
                return (string) $item;
            },
            $this->attributes->toArray()
        );
    }

}
