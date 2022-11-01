<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Difference rapport for a related model that was removed for a plural relation.
 */
class RelatedRemovedDifference extends AbstractRelatedDifference implements DifferenceLeafInterface
{
    /**
     * @param mixed|false              $key     Key for the previously related model
     * @param class-string<Model>|null $class   Only set if the relation allows variable model classes.
     * @param bool                     $deleted Whether the previously related model was deleted since the before state
     */
    public function __construct(
        protected readonly mixed $key,
        protected readonly ?string $class = null,
        protected readonly bool $deleted = false,
    ) {
    }

    public function getKey(): mixed
    {
        return $this->key;
    }

    /**
     * Returns related model class, if not a morphTo relation.
     *
     * @return class-string<Model>|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * Returns model reference.
     *
     * Can be just the key, or class:key, depending on whether the model class is set.
     *
     * @return mixed
     */
    public function getModelReference(): mixed
    {
        if ($this->class) {
            return $this->class . ':' . $this->key;
        }

        return $this->key;
    }

    /**
     * Returns whether the model was deleted since the before state.
     *
     * @return bool
     */
    public function wasDeleted(): bool
    {
        return $this->deleted;
    }

    function __toString(): string
    {
        return 'No longer connected to '
            . ($this->deleted ? 'and deleted ' : null)
            . ($this->class ? $this->class . ' ' : null)
            . '#' . $this->key;
    }
}
