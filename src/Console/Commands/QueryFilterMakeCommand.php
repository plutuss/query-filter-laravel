<?php

namespace Plutuss\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class QueryFilterMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:query-filter {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new query filter class';


    /**
     * @var string
     */
    protected $type = 'QueryFilter';


    protected function getStub()
    {
        return __DIR__ . '/../../stubs/QueryFilter.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return 'App\Filters';
    }
}
