<?php

declare(strict_types=1);

namespace Plutuss\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;


abstract class QueryFilter implements QueryFilterInterface
{
    public Request $request;

    protected Builder $builder;
    protected string $delimiter = ',';

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return array|string|null
     */
    public function filters(): array|string|null
    {
        return $this->request->query();
    }

    /**
     * @param Builder $builder
     * @return Builder
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $name => $value) {
            if (method_exists($this, $name) && !empty($value)) {
                call_user_func_array([$this, $name], array_filter([$value]));
            }
        }

        return $this->builder;
    }

    /**
     * @param $param
     * @return false|string[]
     */
    protected function paramToArray($param): array|bool
    {
        return explode($this->delimiter, $param);
    }
}
