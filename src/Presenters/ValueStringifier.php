<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Presenters;

use Czim\ModelComparer\Contracts\ValueStringifierInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;
use Illuminate\Contracts\Support\Arrayable;
use Throwable;

class ValueStringifier implements ValueStringifierInterface
{
    public function make(mixed $value, ?string $wrap = '"'): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value === false) {
            return 'FALSE';
        }

        if ($value === true) {
            return 'TRUE';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof AttachmentInterface) {
            $value = $value->url();
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        try {
            $value = (string) $value;
        } catch (Throwable $e) {
            $value = '<error>' . $e->getMessage() . '</error>';
        }

        if ($wrap === null) {
            return $value;
        }

        return $wrap . $value . $wrap;
    }
}
