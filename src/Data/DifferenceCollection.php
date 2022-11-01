<?php

declare(strict_types=1);

namespace Czim\ModelComparer\Data;

use Illuminate\Support\Collection;

/**
 * @template TDifference of \Czim\ModelComparer\Contracts\DifferenceLeafInterface|\Czim\ModelComparer\Contracts\DifferenceNodeInterface
 *
 * @extends Collection<int, TDifference>
 */
class DifferenceCollection extends Collection
{
}
