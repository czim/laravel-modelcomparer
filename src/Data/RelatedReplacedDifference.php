<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Difference rapport on a single related model for an Eloquent relation,
 * that replaces a different previously connected model.
 */
class RelatedReplacedDifference extends RelatedChangedDifference
{
    /**
     * The related model's key (before).
     *
     * @var mixed
     */
    protected mixed $keyBefore;

    /**
     * The related model class (before).
     *
     * Only set if the relation allows variable model classes.
     *
     * @var class-string<Model>|null
     */
    protected ?string $classBefore;


    /**
     * {@inheritDoc}
     * @param mixed                    $keyBefore
     * @param class-string<Model>|null $classBefore
     */
    public function __construct(
        mixed $key,
        ?string $class,
        ModelDifference $difference,
        mixed $keyBefore,
        ?string $classBefore = null
    ) {
        parent::__construct($key, $class, $difference);

        $this->keyBefore   = $keyBefore;
        $this->classBefore = $classBefore;
    }


    /**
     * Returns related model key in the before situation.
     *
     * @return mixed
     */
    public function getKeyBefore(): mixed
    {
        return $this->keyBefore;
    }

    /**
     * Returns related model class for the before situation, if not a morphTo relation.
     *
     * @return class-string<Model>|null
     */
    public function getClassBefore(): ?string
    {
        return $this->classBefore;
    }

    /**
     * Returns model reference for the before situation.
     *
     * Can be just the key, or class:key, depending on whether the model class is set.
     *
     * @return mixed
     */
    public function getModelReferenceBefore(): mixed
    {
        if ($this->classBefore) {
            return $this->classBefore . ':' . $this->keyBefore;
        }

        return $this->keyBefore;
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
        return 'Replaced '
            . ($this->classBefore ? $this->classBefore . ' ' : null)
            . '#' . $this->keyBefore
            . ' with '
            . ($this->class ? $this->class . ' ' : null)
            . '#' . $this->key;
    }
}
