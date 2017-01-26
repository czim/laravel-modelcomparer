<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Contracts\DifferenceNodeInterface;

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
     * @var DifferenceCollection|DifferenceNodeInterface[]|DifferenceLeafInterface[]
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
     * @return DifferenceCollection|DifferenceLeafInterface[]|DifferenceNodeInterface[]
     */
    public function related()
    {
        return $this->related;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $difference = [];

        if ($this->hasMessage()) {
            $difference['relation'] = $this->getMessage();
        }

        if (count($this->related)) {
            $difference['related'] = [];

            foreach ($this->related as $key => $related) {

                if ($related instanceof DifferenceLeafInterface) {
                    $difference['related'][] = (string) $related;
                    continue;
                }

                $difference['related'][] = $related->toArray();
            }
        }

        return $difference;
    }

}
