<?php

namespace Czim\ModelComparer\Contracts;

use Czim\ModelComparer\Data\ModelDifference;

interface DifferencePresenterInterface
{
    /**
     * Returns a presentation for a given model difference object tree.
     *
     * @param ModelDifference $difference
     * @return mixed
     */
    public function present(ModelDifference $difference): mixed;
}
