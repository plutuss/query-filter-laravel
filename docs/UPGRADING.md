# Upgrading to 1.3.0

```shell
composer require plutuss/query-filter-laravel:^1.3
```

Existing public instance filter methods continue to work without extra configuration.
PHP 8.0 or newer is required. CI covers Laravel 9–13 with their corresponding PHP versions.

## Explicit empty data

`User::query()->filter($filter, [])` now applies no filters, even when the URL has
query parameters. Omit the second argument or pass `null` to use HTTP query data.
A direct `$filter->apply($builder)` also uses the request when no data has been set.

## Zero and false

Values `0`, `"0"`, and `false` now reach filter methods. Remove truthiness checks
inside filters that would discard these values. Null, empty strings, and empty
arrays remain skipped after validation.

## Allowed methods

Base class methods, magic methods, static methods, and nonpublic methods are no
longer callable through query parameters. Move any intentional protected or static
filters into public instance methods.

Public helper methods in a filter class remain discoverable by default. Protect
them by declaring an explicit list of method names:

```php
protected ?array $allowedFilters = ['name', 'active', 'createdAfter'];
```

The list uses declared method names. Both `created_after` and `createdAfter` can
select the `createdAfter` method. An empty list disables all filters.

## Validation

Validation is opt-in through the protected `$rules` array. Without rules, input
passes through as before. Rules use Laravel's validator and run before any filter
method modifies the query. Invalid input raises `ValidationException`.

Rules follow snake_case/camelCase aliases, including rules for nested array items.
They do not cast input. Use rules such as `string`, `array`, `boolean`, and
`date_format:Y-m-d` to reject unexpected input types.

If an existing filter already declares properties named `$rules` or `$allowedFilters`,
use the same property types as the base class: `array` and `?array`, respectively.

## Livewire and generated classes

`WithQueryParameters` accepts both simple `$queryString` entries and entries with
options. It preserves zero and false. It does not inspect `#[Url]` attributes.
Newly generated filters use the application's root namespace and include an
allowlist and validation example. Existing filter files are never overwritten.
