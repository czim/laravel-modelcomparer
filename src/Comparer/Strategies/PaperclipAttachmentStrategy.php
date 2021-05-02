<?php

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;

class PaperclipAttachmentStrategy implements CompareStrategyInterface
{
    /**
     * Returns whether two values are equal.
     *
     * @param AttachmentInterface $before
     * @param AttachmentInterface $after
     * @param bool  $strict     whether to only consider strict sameness
     * @return bool
     */
    public function equal($before, $after, bool $strict = false): bool
    {
        if (! $before instanceof AttachmentInterface || ! $after instanceof AttachmentInterface) {
            return false;
        }

        return $before->url() === $after->url()
            && $before->size() === $after->size();
    }
}
