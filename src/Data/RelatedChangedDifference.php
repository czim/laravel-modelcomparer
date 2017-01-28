<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;

/**
 * Class RelatedChangedDifference
 *
 * Difference rapport on a single related model for an Eloquent relation,
 * that was connected before but may have been changed.
 */
class RelatedChangedDifference extends AbstractRelatedDifference implements DifferenceNodeInterface
{
    use ToArrayJsonable;

    /**
     * The related model's key.
     *
     * @var mixed|false
     */
    protected $key;

    /**
     * The related model class.
     *
     * Only set if the relation allows variable model classes.
     *
     * @var string|null
     */
    protected $class;

    /**
     * Model difference instance, describing how the related model itself was changed.
     *
     * @var ModelDifference
     */
    protected $difference;

    /**
     * Difference for pivot attributes
     *
     * @var false|PivotDifference
     */
    protected $pivotDifference;


    /**
     * @param mixed|false     $key false if the model was not related before.
     * @param string|null     $class
     * @param ModelDifference $difference
     * @param PivotDifference $pivotDifference
     */
    public function __construct($key, $class, ModelDifference $difference, PivotDifference $pivotDifference = null)
    {
        $this->key             = $key;
        $this->class           = $class;
        $this->difference      = $difference;
        $this->pivotDifference = $pivotDifference ?: false;
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
     * Returns difference object for related model.
     *
     * @return ModelDifference
     */
    public function difference()
    {
        return $this->difference;
    }

    /**
     * Returns whether this difference data is for a pivot related model.
     *
     * @return bool
     */
    public function isPivotRelated()
    {
        return !! $this->pivotDifference;
    }

    /**
     * Returns differences for pivot attributes, or false if this is not a pivot relation.
     *
     * @return false|PivotDifference
     */
    public function pivotDifference()
    {
        if ( ! $this->pivotDifference) {
            return false;
        }

        return $this->pivotDifference;
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
        return $this->difference->toArray();
    }

    /**
     * Whether this node has a difference message itself.
     *
     * @return bool
     */
    public function hasMessage()
    {
        return false;
    }

    /**
     * Returns a string representation of difference on the node level itself.
     *
     * @return string|null
     */
    public function getMessage()
    {
        return null;
    }

}
