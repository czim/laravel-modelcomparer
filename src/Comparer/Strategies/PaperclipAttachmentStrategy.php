<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;

class PaperclipAttachmentStrategy implements CompareStrategyInterface
{
    /**
     * Returns whether two values are equal.
     *
     * @param AttachmentInterface|null $before
     * @param AttachmentInterface|null $after
     * @param bool                     $strict Whether to only consider strict sameness
     * @return bool
     */
    public function equal(mixed $before, mixed $after, bool $strict = false): bool
    {
        if (
            ! $before instanceof AttachmentInterface
            || ! $after instanceof AttachmentInterface
        ) {
            return false;
        }

        return $before->url() === $after->url()
            && $before->size() === $after->size();
    }
}
