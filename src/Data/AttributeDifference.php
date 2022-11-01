<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Czim\ModelComparer\Contracts\DifferenceLeafInterface;
use Czim\ModelComparer\Contracts\ValueStringifierInterface;
use Czim\ModelComparer\Traits\ToArrayJsonable;

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
    protected mixed $before;

    /**
     * State of the attribute after.
     *
     * @var mixed
     */
    protected mixed $after;

    /**
     * Whether the value did not exist for a key in the before array.
     *
     * @var bool
     */
    protected bool $beforeDoesNotExist = false;

    /**
     * Whether the value did not exist for a key in the after array.
     *
     * @var bool
     */
    protected bool $afterDoesNotExist = false;


    public function __construct(
        mixed $before = null,
        mixed $after = null,
        bool $beforeDoesNotExist = false,
        bool $afterDoesNotExist = false,
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


    public function before(): mixed
    {
        return $this->before;
    }

    public function after(): mixed
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
    public function setBefore(mixed $before): static
    {
        $this->before = $before;

        return $this;
    }

    /**
     * @param mixed $after
     * @return $this
     */
    public function setAfter(mixed $after): static
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Renders difference as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $difference = [];

        if (! $this->beforeDoesNotExist) {
            $difference['before'] = $this->normalizeToString($this->before, null);
        }

        if (! $this->afterDoesNotExist) {
            $difference['after'] = $this->normalizeToString($this->after, null);
        }

        return $difference;
    }

    public function __toString(): string
    {
        $before = null;
        $after  = null;

        if (! $this->beforeDoesNotExist) {
            $before = $this->normalizeToString($this->before);
        }

        if (! $this->afterDoesNotExist) {
            $after = $this->normalizeToString($this->after);
        }

        if ($this->beforeDoesNotExist) {
            return "New value {$after}";
        }

        if ($this->afterDoesNotExist) {
            return "No longer present (was {$before})";
        }

        return "{$before} changed to {$after}";
    }

    /**
     * Attempts to make strings out of a mixed value.
     *
     * @param mixed       $value
     * @param string|null $enclose enclosing symbol, if string values should be enclosed
     * @return string
     */
    protected function normalizeToString(mixed $value, ?string $enclose = '"'): string
    {
        return app(ValueStringifierInterface::class)->make($value, $enclose);
    }
}
