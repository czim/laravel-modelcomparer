<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyFactoryInterface;
use Czim\ModelComparer\Contracts\CompareStrategyInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;

class CompareStrategyFactory implements CompareStrategyFactoryInterface
{
    /**
     * @param mixed $valueBefore
     * @param mixed $valueAfter
     * @return CompareStrategyInterface
     */
    public function make($valueBefore, $valueAfter): CompareStrategyInterface
    {
        return app($this->determineStrategyClass($valueBefore, $valueAfter));
    }

    protected function determineStrategyClass($valueBefore, $valueAfter): string
    {
        if (
            is_a($valueBefore, AttachmentInterface::class)
            || is_a($valueAfter, AttachmentInterface::class)
        ) {
            return PaperclipAttachmentStrategy::class;
        }

        return SimpleStrategy::class;
    }
}
