<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * Class RelationDifference
 *
 * Difference rapport on an Eloquent relation of a model.
 */
class ModelDifference implements Arrayable, Jsonable
{
    use ToArrayJsonable;

    /**
     * The FQN of the model for which differences are represented.
     *
     * @var string
     */
    protected $modelClass;

    /**
     * Differences in model attributes.
     *
     * @var DifferenceCollection|AttributeDifference[]
     */
    protected $attributes;

    /**
     * Differences in related models.
     *
     * @var DifferenceCollection|AbstractRelationDifference[]
     */
    protected $relations;


    /**
     * @param string               $class
     * @param DifferenceCollection $attributes
     * @param DifferenceCollection $relations
     */
    public function __construct($class, DifferenceCollection $attributes, DifferenceCollection $relations)
    {
        $this->modelClass = $class;
        $this->attributes = $attributes;
        $this->relations  = $relations;
    }

    /**
     * @return string
     */
    public function modelClass()
    {
        return $this->modelClass;
    }

    /**
     * Returns whether there are any differences at all.
     * @return bool
     */
    public function isDifferent()
    {
        return count($this->attributes) > 0 || count($this->relations) > 0;
    }

    /**
     * @return AttributeDifference[]|DifferenceCollection
     */
    public function attributes()
    {
        return $this->attributes;
    }

    /**
     * @return AbstractRelationDifference[]|DifferenceCollection
     */
    public function relations()
    {
        return $this->relations;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $difference = [];

        if (count($this->attributes)) {
            $difference['attributes'] = array_map(
                function (AttributeDifference $item) {
                    return (string) $item;
                },
                $this->attributes->toArray()
            );
        }

        if (count($this->relations)) {
            $difference['relations'] = $this->relations->toArray();
        }

        return $difference;
    }

}
