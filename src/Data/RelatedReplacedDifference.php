<?php
namespace Czim\ModelComparer\Data;

/**
 * Difference rapport on a single related model for an Eloquent relation,
 * that replaces a different previously connected model.
 */
class RelatedReplacedDifference extends RelatedChangedDifference
{

    /**
     * The related model's key (before).
     *
     * @var mixed|false
     */
    protected $keyBefore;

    /**
     * The related model class (before).
     *
     * Only set if the relation allows variable model classes.
     *
     * @var string|null
     */
    protected $classBefore;


    /**
     * {@inheritdoc}
     * @param mixed       $keyBefore
     * @param string|null $classBefore
     */
    public function __construct(
        $key,
        ?string $class,
        ModelDifference $difference,
        $keyBefore,
        ?string $classBefore = null
    ) {
        parent::__construct($key, $class, $difference);

        $this->keyBefore   = $keyBefore;
        $this->classBefore = $classBefore;
    }

    /**
     * Returns related model key for the before situation.
     *
     * @return mixed
     */
    public function getKeyBefore()
    {
        return $this->keyBefore;
    }

    /**
     * Returns related model class for the before situation, if not a morphTo relation.
     *
     * @return string|null
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
     * @return mixed|string
     */
    public function getModelReferenceBefore()
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

    /**
     * Returns a string representation of difference on the node level itself.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return 'Replaced '
            . ($this->classBefore ? $this->classBefore . ' ' : null) . '#' . $this->keyBefore
            . ' with '
            . ($this->class ? $this->class . ' ' : null) . '#' . $this->key;
    }

}
