<?php

declare(strict_types=1);

namespace Plutuss\Filter;

use Illuminate\Database\Eloquent\Builder;
use ReflectionMethod;

abstract class QueryFilter implements QueryFilterInterface
{
    protected Builder $builder;

    protected ?array $data = null;

    protected string $delimiter = ',';

    /** @var list<string>|null Null preserves automatic discovery of public filters. */
    protected ?array $allowedFilters = null;

    /** @var array<string, mixed> Laravel validation rules for filter input. */
    protected array $rules = [];

    /**
     * @return array|string|null
     */
    public function filters(): array|string|null
    {
        $data = $this->getData();

        if ($data !== null) {
            return $data;
        }

        return request()->query();
    }

    protected function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }


    /**
     * @param Builder $builder
     * @return Builder
     */
    public function apply(Builder $builder): Builder
    {

        $this->builder = $builder;

        $filters = $this->filters();

        if ($this->rules !== []) {
            // Validate before modifying the query. Keep fields without rules available
            // for backwards compatibility; allowedFilters controls dispatch separately.
            validator($filters, $this->validationRulesFor($filters))->validate();
        }

        foreach ($filters as $name => $value) {
            $method = $this->methodExist((string) $name);

            if ($method !== '' && $value !== null && $value !== '' && $value !== []) {
                $this->{$method}($value);
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
     * @return string
     */
    private function methodExist(string $name): string
    {
        if ($this->isFilterMethod($name)) {
            return $name;
        }

        $camelName = str($name)
            ->camel()
            ->toString();

        if ($this->isFilterMethod($camelName)) {
            return $camelName;
        }

        return '';
    }

    private function isFilterMethod(string $name): bool
    {
        if (method_exists(self::class, $name) || str_starts_with($name, '__') || !method_exists($this, $name)) {
            return false;
        }

        $method = new ReflectionMethod($this, $name);

        return $method->isPublic()
            && !$method->isStatic()
            && ($this->allowedFilters === null || in_array($method->getName(), $this->allowedFilters, true));
    }

    private function validationRulesFor(array $filters): array
    {
        $rules = $this->rules;

        foreach ($this->rules as $field => $rule) {
            $root = explode('.', $field, 2)[0];
            $method = $this->methodExist($root);

            if ($method === '') {
                continue;
            }

            $matchingNames = [];

            foreach ($filters as $name => $value) {
                $candidate = $this->methodExist((string) $name);

                if ($candidate !== '' && strcasecmp($method, $candidate) === 0) {
                    $matchingNames[] = $name;
                }
            }

            if ($matchingNames !== []) {
                unset($rules[$field]);

                foreach ($matchingNames as $name) {
                    $rules[$name . substr($field, strlen($root))] = $rule;
                }
            }
        }

        return $rules;
    }
}
