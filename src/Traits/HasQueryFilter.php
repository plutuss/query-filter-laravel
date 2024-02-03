<?php

namespace Plutuss\Traits;

use Illuminate\Database\Eloquent\Builder;
use Plutuss\Filter\QueryFilterInterface;

trait HasQueryFilter
{
    /**
     * @param Builder $builder
     * @param QueryFilterInterface $filter
     * @param $data
     * @return Builder
     */
    public function scopeFilter(Builder $builder, QueryFilterInterface $filter, $data = []): Builder
    {
        return $filter->apply($builder, $data);
    }
}
