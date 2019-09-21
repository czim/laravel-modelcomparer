<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;

/**
 * Difference rapport for a related model that was removed for a plural relation.
 */
class RelatedRemovedDifference extends AbstractRelatedDifference implements DifferenceLeafInterface
{

    /**
     * The related model's key (before).
     *
     * @var mixed|false
     */
    protected $key;

    /**
     * The related model class (before).
     *
     * Only set if the relation allows variable model classes.
     *
     * @var string|null
     */
    protected $class;

    /**
     * Whether the previously related model was deleted since the before state.
     *
     * @var bool
     */
    protected $deleted;


    /**
     * @param mixed|false $key key for the previously related model
     * @param string|null $class
     * @param bool        $deleted
     */
    public function __construct($key, ?string $class = null, bool $deleted = false)
    {
        $this->key     = $key;
        $this->class   = $class;
        $this->deleted = $deleted;
    }

    /**
     * Returns related model key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Returns related model class, if not a morphTo relation.
     *
     * @return string|null
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
     * @return mixed|string
     */
    public function getModelReference()
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
             . ($this->class ? $this->class . ' ' : null) . '#' . $this->key;
    }

}
