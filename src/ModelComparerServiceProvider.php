<?php
namespace Czim\ModelComparer;

use Czim\ModelComparer\Comparer\Comparer;
use Czim\ModelComparer\Contracts\ComparerInterface;
use Czim\ModelComparer\Contracts\DifferencePresenterInterface;
use Czim\ModelComparer\Presenters\ArrayPresenter;
use Illuminate\Support\ServiceProvider;

class ModelComparerServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->bind(ComparerInterface::class, Comparer::class);
        $this->app->bind(DifferencePresenterInterface::class, ArrayPresenter::class);
    }

}
