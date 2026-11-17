<?php

declare(strict_types=1);

namespace Plutuss\Attributes;

use Attribute;
use Illuminate\Routing\Route;
use InvalidArgumentException;
use Plutuss\Filter\QueryFilterInterface;
use ReflectionMethod;

#[Attribute(Attribute::TARGET_METHOD)]
final class UseQueryFilter
{
    /** @var class-string<QueryFilterInterface> */
    public string $filter;

    /** @param class-string<QueryFilterInterface> $filter */
    public function __construct(string $filter)
    {
        if (!class_exists($filter) || !is_a($filter, QueryFilterInterface::class, true)) {
            throw new InvalidArgumentException('The filter must be a class implementing QueryFilterInterface.');
        }

        $this->filter = $filter;
    }

    public static function fromRoute(Route $route): ?self
    {
        $action = $route->getAction('uses');

        if (!is_string($action) || !str_contains($action, '@')) {
            return null;
        }

        [$controller, $method] = explode('@', $action, 2);

        if (!method_exists($controller, $method)) {
            return null;
        }

        $attributes = (new ReflectionMethod($controller, $method))->getAttributes(self::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
