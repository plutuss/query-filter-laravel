<?php

namespace Plutuss\Filter;

use Illuminate\Database\Eloquent\Builder;

interface QueryFilterInterface
{
    public function apply(Builder $builder, $data = []): Builder;

    public function filters(): array|string|null;
}
