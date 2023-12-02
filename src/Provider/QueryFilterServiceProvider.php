<?php

declare(strict_types=1);


namespace Plutuss\Provider;


use Illuminate\Support\ServiceProvider;
use Plutuss\Console\Commands\QueryFilterMakeCommand;

class QueryFilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(QueryFilterMakeCommand::class);
        }
    }

}
