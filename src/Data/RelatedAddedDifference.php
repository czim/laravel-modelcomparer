<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;

/**
 * Class RelatedAddedDifference
 *
 * Difference rapport for a related model that was newly added for a plural relation.
 */
class RelatedAddedDifference extends AbstractRelatedDifference implements DifferenceNodeInterface
{
    use ToArrayJsonable;

    /**
     * The related model's key (after).
     *
     * @var mixed|false
     */
    protected $key;

    /**
     * The related model class (after).
     *
     * Only set if the relation allows variable model classes.
     *
     * @var string|null
     */
    protected $class;

    /**
     * The difference tree for the related model.
     *
     * @var ModelDifference
     */
    protected $difference;


    /**
     * @param mixed|false $key      key for the newly related model
     * @param string|null $class
     */
    public function __construct($key, $class = null)
    {
        $this->key   = $key;
        $this->class = $class;
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
    public function getClass()
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
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        if ($this->difference) {
            return $this->difference->toArray();
        }

        return [];
    }

    /**
     * Whether this node has a difference message itself.
     *
     * @return bool
     */
    public function hasMessage()
    {
        return true;
    }

    /**
     * Returns a string representation of difference on the node level itself.
     *
     * @return string|null
     */
    public function getMessage()
    {
        return "Newly connected to " . ($this->class ? $this->class . ' ' : null) . '#' . $this->key;
    }

}
