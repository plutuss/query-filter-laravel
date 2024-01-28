<?php

declare(strict_types=1);

namespace Plutuss\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery\Exception;

abstract class QueryFilter implements QueryFilterInterface
{

    public function __construct(
        public Request    $request,
        protected Builder $builder,
        protected string  $delimiter = ','
    )
    {
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
            if ($this->checkMethodAndValue($name, $value)) {
                call_user_func_array([$this, $this->methodExist($name)], array_filter([$value]));
            }
        }

        return $this->builder;
    }


    /**
     * @param string $param
     * @return array|bool
     */
    protected function paramToArray(string $param): array|bool
    {
        return explode($this->delimiter, $param);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    private function checkMethodAndValue(string $name, mixed $value): bool
    {
        return method_exists($this, $this->methodExist($name)) && !empty($value);
    }


    /**
     * @param string $name
     * @return mixed
     */
    private function methodExist(string $name): mixed
    {
        if (method_exists($this, $name)) {
            return $name;
        }
        $camelName = str()->camel($name);
        if (method_exists($this, $camelName)) {
            return $camelName;
        }
        return '';
    }
}
