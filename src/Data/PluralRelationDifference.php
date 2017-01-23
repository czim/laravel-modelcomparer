<?php
namespace Czim\ModelComparer\Data;

/**
 * Class PluralRelationDifference
 *
 * Difference rapport on a plural Eloquent relation of a model.
 */
class PluralRelationDifference extends AbstractRelationDifference
{

    /**
     * Difference entries for related (and no longer related) models.
     *
     * Instances of:
     *      RelatedAddedDifference
     *      RelatedRemovedDifference
     *      RelatedChangedDifference
     *
     * @var DifferenceCollection
     */
    protected $related;

    /**
     * @param string               $method
     * @param string               $type
     * @param DifferenceCollection $related
     */
    public function __construct($method, $type, DifferenceCollection $related)
    {
        parent::__construct($method, $type, true);

        $this->related = $related;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->related->toArray();
    }

}
