<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Comparer\Strategies;

use Czim\ModelComparer\Contracts\CompareStrategyFactoryInterface;
use Czim\ModelComparer\Contracts\CompareStrategyInterface;
use Czim\Paperclip\Contracts\AttachmentInterface;

class CompareStrategyFactory implements CompareStrategyFactoryInterface
{
    public function make(mixed $valueBefore, mixed $valueAfter): CompareStrategyInterface
    {
        return app($this->determineStrategyClass($valueBefore, $valueAfter));
    }

    /**
     * @param mixed $valueBefore
     * @param mixed $valueAfter
     * @return class-string<CompareStrategyInterface>
     */
    protected function determineStrategyClass(mixed $valueBefore, mixed $valueAfter): string
    {
        if (
            $valueBefore instanceof AttachmentInterface
            || $valueAfter instanceof AttachmentInterface
        ) {
            return PaperclipAttachmentStrategy::class;
        }

        return SimpleStrategy::class;
    }
}
