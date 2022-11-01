<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;

/**
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
     * @var DifferenceCollection<RelatedAddedDifference|RelatedRemovedDifference|RelatedChangedDifference>
     */
    protected DifferenceCollection $related;

    /**
     *
     * @param string                                                                                         $method
     * @param string                                                                                         $type
     * @param DifferenceCollection<RelatedAddedDifference|RelatedRemovedDifference|RelatedChangedDifference> $related
     */
    public function __construct(string $method, string $type, DifferenceCollection $related)
    {
        parent::__construct($method, $type, true);

        $this->related = $related;
    }

    /**
     * @return DifferenceCollection<RelatedAddedDifference|RelatedRemovedDifference|RelatedChangedDifference>
     */
    public function related(): DifferenceCollection
    {
        return $this->related;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $difference = [];

        if ($this->hasMessage()) {
            $difference['relation'] = $this->getMessage();
        }

        if (count($this->related)) {
            $difference['related'] = [];

            foreach ($this->related as $key => $related) {
                if ($related instanceof DifferenceLeafInterface) {
                    $difference['related'][ $key ] = (string) $related;
                    continue;
                }

                $difference['related'][ $key ] = $related->toArray();
            }
        }

        return $difference;
    }
}
