<?php

namespace Plutuss\Traits;

use Illuminate\Database\Eloquent\Builder;
use Plutuss\Filter\QueryFilterInterface;

trait HasQueryFilter
{
    /**
     * @param Builder $builder
     * @param QueryFilterInterface $filter
     * @param ?array $data
     * @return Builder
     */
    public function scopeFilter(Builder $builder, QueryFilterInterface $filter, ?array $data = []): Builder
    {
        return $filter
            ->setData($data)
            ->apply($builder);
    }
}
