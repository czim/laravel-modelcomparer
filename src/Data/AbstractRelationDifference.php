<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

abstract class AbstractRelationDifference implements DifferenceNodeInterface
{
    use ToArrayJsonable;

    /**
     * The relation's method name.
     *
     * @var string
     */
    protected $method;

    /**
     * The relation class FQN.
     *
     * @var string
     */
    protected $type;

    /**
     * Whether the relation is plural
     *
     * @var bool
     */
    protected $plural = false;

    /**
     * Whether the relation is of a morph type with variable related model classes.
     *
     * @var bool
     */
    protected $isVariableModelClass = false;

    /**
     * The model class FQN for the related model, if this is not a morphTo relation.
     *
     * @var string|null
     */
    protected $modelClass;


    public function __construct(string $method, string $type, bool $plural = false)
    {
        $this->method = $method;
        $this->type   = $type;
        $this->plural = $plural;

        $this->isVariableModelClass = $type === MorphTo::class;
    }

    /**
     * Sets the related model FQN.
     *
     * @param string|null $class
     * @return $this
     */
    public function setModelClass($class): AbstractRelationDifference
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * Returns related model class, if not a morphTo relation.
     *
     * @return string|null
     */
    public function modelClass(): ?string
    {
        if ($this->isVariableModelClass) {
            return null;
        }

        return $this->modelClass;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isPlural(): bool
    {
        return $this->plural;
    }

    public function hasVariableModelClass(): bool
    {
        return $this->isVariableModelClass;
    }

    /**
     * Whether this node has a difference message itself.
     *
     * @return bool
     */
    public function hasMessage(): bool
    {
        return false;
    }

    /**
     * Returns a string representation of difference on the node level itself.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return null;
    }

}
