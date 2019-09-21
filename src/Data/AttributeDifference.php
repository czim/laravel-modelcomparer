<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Describes the before/after difference of model- or pivot table attributes
 * that show any sort of difference.
 */
class AttributeDifference implements DifferenceLeafInterface
{
    use ToArrayJsonable;

    /**
     * State of the attribute before.
     *
     * @var mixed
     */
    protected $before;

    /**
     * State of the attribute after.
     *
     * @var mixed
     */
    protected $after;

    /**
     * Whether the value did not exist for a key in the before array.
     *
     * @var bool
     */
    protected $beforeDoesNotExist = false;

    /**
     * Whether the value did not exist for a key in the after array.
     *
     * @var bool
     */
    protected $afterDoesNotExist = false;


    /**
     * @param mixed $before
     * @param mixed $after
     * @param bool  $beforeDoesNotExist
     * @param bool  $afterDoesNotExist
     */
    public function __construct(
        $before = null,
        $after = null,
        bool $beforeDoesNotExist = false,
        bool $afterDoesNotExist = false
    ) {
        $this->before = $before;
        $this->after  = $after;

        if ($beforeDoesNotExist) {
            $this->beforeDoesNotExist = true;
            $this->before             = null;
        }

        if ($afterDoesNotExist) {
            $this->afterDoesNotExist = true;
            $this->after             = null;
        }
    }


    /**
     * @return mixed
     */
    public function before()
    {
        return $this->before;
    }

    /**
     * @return mixed
     */
    public function after()
    {
        return $this->after;
    }

    /**
     * Returns whether the before value / key was not present.
     *
     * @return bool
     */
    public function didNotExistBefore(): bool
    {
        return $this->beforeDoesNotExist;
    }

    /**
     * Returns whether the before value / key was not present.
     *
     * @return bool
     */
    public function didNotExistAfter(): bool
    {
        return $this->afterDoesNotExist;
    }

    /**
     * @param mixed $before
     * @return $this
     */
    public function setBefore($before): AttributeDifference
    {
        $this->before = $before;

        return $this;
    }

    /**
     * @param mixed $after
     * @return $this
     */
    public function setAfter($after): AttributeDifference
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Renders difference as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $difference = [];

        if ( ! $this->beforeDoesNotExist) {
            $difference['before'] = $this->normalizeToString($this->before, false);
        }

        if ( ! $this->afterDoesNotExist) {
            $difference['after'] = $this->normalizeToString($this->after, false);
        }

        return $difference;
    }

    /**
     * Renders difference as a string value.
     *
     * @return string
     */
    public function __toString(): string
    {
        $before = null;
        $after  = null;

        if ( ! $this->beforeDoesNotExist) {
            $before = $this->normalizeToString($this->before);
        }

        if ( ! $this->afterDoesNotExist) {
            $after = $this->normalizeToString($this->after);
        }

        if ($this->beforeDoesNotExist) {
            return "New value {$after}";
        }

        if ($this->afterDoesNotExist) {
            return "No longer present (was {$before})";
        }

        return $before . ' changed to ' . $after;
    }

    /**
     * Attempts to make strings out of a mixed value.
     *
     * @param mixed       $value
     * @param string|null $enclose  enclosing symbol, if string values should be enclosed
     * @return string
     */
    protected function normalizeToString($value, ?string $enclose = '"'): string
    {
        if (null === $value) {
            return 'NULL';
        }

        if (false === $value) {
            return 'FALSE';
        }

        if (true === $value) {
            return 'TRUE';
        }

        if (is_numeric($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        $value = (string) $value;

        if ( ! $enclose) {
            return $value;
        }

        return $enclose . $value . $enclose;
    }

}
