<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceNodeInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Database\Eloquent\Model;

/**
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
    protected mixed $key;

    /**
     * The related model class (after).
     *
     * Only set if the relation allows variable model classes.
     *
     * @var class-string<Model>|null
     */
    protected ?string $class;

    /**
     * The difference tree for the related model.
     *
     * @var ModelDifference|null
     */
    protected ?ModelDifference $difference;


    /**
     * @param mixed|false              $key        key for the newly related model
     * @param class-string<Model>|null $class
     * @param ModelDifference|null     $difference difference if model is newly created
     */
    public function __construct(mixed $key, ?string $class = null, ?ModelDifference $difference = null)
    {
        $this->key        = $key;
        $this->class      = $class;
        $this->difference = $difference;
    }

    public function getKey(): mixed
    {
        return $this->key;
    }

    /**
     * Returns related model class, if not a morphTo relation.
     *
     * @return class-string<Model>|null
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
     * @return mixed
     */
    public function getModelReference(): mixed
    {
        if ($this->class) {
            return $this->class . ':' . $this->key;
        }

        return $this->key;
    }

    /**
     * Returns difference for the added model.
     *
     * @return ModelDifference|bool
     */
    public function difference(): ModelDifference|bool
    {
        return $this->difference ?: false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $difference = [
            'message' => $this->getMessage(),
        ];

        if ($this->difference) {
            $difference['related'] = $this->difference->toArray();
        }

        return $difference;
    }

    /**
     * Whether this node has a difference message itself.
     *
     * @return bool
     */
    public function hasMessage(): bool
    {
        return true;
    }

    public function getMessage(): ?string
    {
        return 'Newly connected to '
            . ($this->difference ? 'newly created ' : null)
            . ($this->class ? $this->class . ' ' : null)
            . '#' . $this->key;
    }
}
