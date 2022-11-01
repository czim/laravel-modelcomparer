<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;

/**
 * Difference rapport on an Eloquent relation of a model.
 */
class ModelDifference implements Arrayable, Jsonable
{
    use ToArrayJsonable;

    /**
     * @param class-string<Model>|string                       $modelClass or empty string
     * @param DifferenceCollection<AttributeDifference>        $attributes
     * @param DifferenceCollection<AbstractRelationDifference> $relations
     */
    public function __construct(
        protected readonly string $modelClass,
        protected readonly DifferenceCollection $attributes,
        protected readonly DifferenceCollection $relations,
    ) {
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Returns whether there are any differences at all.
     *
     * @return bool
     */
    public function isDifferent(): bool
    {
        return count($this->attributes) > 0
            || count($this->relations) > 0;
    }

    /**
     * @return DifferenceCollection<AttributeDifference>
     */
    public function attributes(): DifferenceCollection
    {
        return $this->attributes;
    }

    /**
     * @return DifferenceCollection<AbstractRelationDifference>
     */
    public function relations(): DifferenceCollection
    {
        return $this->relations;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $difference = [];

        if (count($this->attributes)) {
            $difference['attributes'] = array_map(
                static fn (AttributeDifference $item): string => (string) $item,
                $this->attributes->toArray()
            );
        }

        if (count($this->relations)) {
            $difference['relations'] = $this->relations->toArray();
        }

        return $difference;
    }
}
