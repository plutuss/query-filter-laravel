<?php

namespace Plutuss\Filter;

use Illuminate\Database\Eloquent\Builder;

interface QueryFilterInterface
{
    public function apply(Builder $builder): Builder;

    public function filters(): array|string|null;

    public function setData(array $data): static;
}
