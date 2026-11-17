<?php

declare(strict_types=1);


namespace Plutuss\Providers;


use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Plutuss\Attributes\UseQueryFilter;
use Plutuss\Console\Commands\QueryFilterMakeCommand;
use Plutuss\Filter\QueryFilterInterface;

class QueryFilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(QueryFilterInterface::class, function ($app) {
            $route = $app->bound('request') ? $app['request']->route() : null;
            $attribute = $route instanceof Route ? UseQueryFilter::fromRoute($route) : null;

            if ($attribute === null) {
                throw new BindingResolutionException(
                    'Add #[UseQueryFilter(YourFilter::class)] to the controller action '
                    . 'to inject QueryFilterInterface, or inject a concrete filter class.'
                );
            }

            return $app->make($attribute->filter);
        });

        if ($this->app->runningInConsole()) {
            $this->commands(QueryFilterMakeCommand::class);
        }
    }

}
