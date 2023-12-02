<?php

namespace Plutuss\Traits;

use Illuminate\Database\Eloquent\Builder;
use Plutuss\Filter\QueryFilter;

trait HasQueryFilter
{
    /**
     * @param Builder $builder
     * @param QueryFilter $filter
     * @return Builder
     */
    public function scopeFilter(Builder $builder, QueryFilter $filter): Builder
    {
        return $filter->apply($builder);
    }
}
