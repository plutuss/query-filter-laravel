<?php

namespace Plutuss\Traits;

use Illuminate\Database\Eloquent\Builder;
use Plutuss\Filter\QueryFilterInterface;

trait HasQueryFilter
{
    /**
     * @param Builder $builder
     * @param QueryFilterInterface $filter
     * @return Builder
     */
    public function scopeFilter(Builder $builder, QueryFilterInterface $filter): Builder
    {
        return $filter->apply($builder);
    }
}
