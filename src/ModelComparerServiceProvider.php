<?php

declare(strict_types=1);

namespace Czim\ModelComparer;

use Czim\ModelComparer\Comparer\ComparableDataTreeFactory;
use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Comparer\Strategies\CompareStrategyFactory;
use Czim\ModelComparer\Contracts\ComparableDataTreeFactoryInterface;
use Czim\ModelComparer\Contracts\ComparerInterface;
use Czim\ModelComparer\Contracts\CompareStrategyFactoryInterface;
use Czim\ModelComparer\Contracts\DifferencePresenterInterface;
use Czim\ModelComparer\Contracts\ValueStringifierInterface;
use Czim\ModelComparer\Presenters\ArrayPresenter;
use Czim\ModelComparer\Presenters\ValueStringifier;
use Illuminate\Support\ServiceProvider;

class ModelComparerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ComparerInterface::class, Comparer::class);
        $this->app->bind(DifferencePresenterInterface::class, ArrayPresenter::class);
        $this->app->bind(ComparableDataTreeFactoryInterface::class, ComparableDataTreeFactory::class);

        $this->app->singleton(CompareStrategyFactoryInterface::class, CompareStrategyFactory::class);
        $this->app->singleton(ValueStringifierInterface::class, ValueStringifier::class);
    }
}
