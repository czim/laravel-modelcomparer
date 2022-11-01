<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class AbstractRelationDifference implements DifferenceNodeInterface
{
    use ToArrayJsonable;

    /**
     * Whether the relation is of a morph type with variable related model classes.
     *
     * @var bool
     */
    protected bool $isVariableModelClass = false;

    /**
     * The model class FQN for the related model, if this is not a morphTo relation.
     *
     * @var class-string<Model>|null
     */
    protected ?string $modelClass = null;


    /**
     * @param string                        $method The relation method name
     * @param class-string<Relation<Model>> $type
     * @param bool                          $plural Whether the relation is plural
     */
    public function __construct(
        protected readonly string $method,
        protected readonly string $type,
        protected readonly bool $plural = false,
    ) {
        $this->isVariableModelClass = $type === MorphTo::class;
    }

    /**
     * Sets the related model FQN.
     *
     * @param class-string<Model>|null $class
     * @return $this
     */
    public function setModelClass(?string $class): static
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

    /**
     * @return class-string<Relation<Model>>
     */
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
