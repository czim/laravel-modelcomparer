<?php
namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Class AttributeDifference
 *
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
     * Whether this change is considered a 'real' one.
     *
     * (f.i.: not 0 to 0.0, or 0 to false).
     *
     * @var bool
     */
    protected $isRealChange = false;

    /**
     * Whether this change should be ignored according to configuration settings.
     *
     * @var bool
     */
    protected $isIgnored = false;


    /**
     * @param mixed $before
     * @param mixed $after
     * @param bool  $beforeDoesNotExist
     * @param bool  $afterDoesNotExist
     */
    public function __construct($before = null, $after = null, $beforeDoesNotExist = false, $afterDoesNotExist = false)
    {
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
     * @param mixed $before
     * @return $this
     */
    public function setBefore($before)
    {
        $this->before = $before;

        return $this;
    }

    /**
     * @param mixed $after
     * @return $this
     */
    public function setAfter($after)
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Set whether this change is considered a 'real' change.
     *
     * @param bool $value
     * @return $this
     */
    public function setRealChange($value = true)
    {
        $this->isRealChange = (bool) $value;

        return $this;
    }

    /**
     * Returns whether this change is considered a 'real' change.
     *
     * @return bool
     */
    public function isRealChange()
    {
        return $this->isRealChange;
    }

    /**
     * Set whether this change is deliberately ignored.
     *
     * @param bool $value
     * @return $this
     */
    public function setIgnored($value = true)
    {
        $this->isIgnored = (bool) $value;

        return $this;
    }

    /**
     * Returns whether this change is marked deliberatly ignored.
     *
     * @return bool
     */
    public function isIgnored()
    {
        return $this->isIgnored;
    }

    /**
     * Renders difference as an array.
     *
     * @return array
     */
    public function toArray()
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
    public function __toString()
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
            return "New value {$after} (was not present before)";
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
    protected function normalizeToString($value, $enclose = '"')
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
