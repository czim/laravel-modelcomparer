<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Contracts\DifferenceNodeInterface;

/**
 * Difference rapport on a singular Eloquent relation of a model.
 */
class SingleRelationDifference extends AbstractRelationDifference
{

    /**
     * Difference node or leaf that represents the single relation's change.
     *
     * @var AbstractRelatedDifference|DifferenceLeafInterface|DifferenceNodeInterface
     */
    protected $difference;

    public function __construct(string $method, string $type, AbstractRelatedDifference $difference)
    {
        parent::__construct($method, $type);

        $this->difference = $difference;
    }

    /**
     * @return AbstractRelatedDifference|DifferenceLeafInterface|DifferenceNodeInterface
     */
    public function difference(): AbstractRelatedDifference
    {
        return $this->difference;
    }

    /**
     * Whether this node has a difference message itself.
     *
     * @return bool
     */
    public function hasMessage(): bool
    {
        if ($this->difference instanceof DifferenceNodeInterface) {
            return $this->difference->hasMessage();
        }

        return true;
    }

    /**
     * Returns a string representation of difference on the node level itself.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        if ($this->difference instanceof DifferenceNodeInterface) {
            return $this->difference->getMessage();
        }

        return (string) $this->difference;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $difference = [];

        if ($this->hasMessage()) {
            $difference['relation'] = $this->getMessage();
        }

        if ($this->difference instanceof DifferenceNodeInterface) {
            $difference['related'] = $this->difference->toArray();
        }

        return $difference;
    }

}
