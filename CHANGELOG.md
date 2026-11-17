# Changelog

## Unreleased

- Add detailed guides for filter behavior, controller attributes, validation,
  Livewire integration, and troubleshooting, with a documentation index in the README.

## 1.4.0 — 2026-11-17

- Add `#[UseQueryFilter(FilterClass::class)]` on controller methods for selecting
  the injected `QueryFilterInterface`; query construction and application remain manual.
- Add Laravel 13 to development dependencies and the compatibility matrix.
- Test Laravel 12 and 13 on PHP 8.4 and 8.5, retaining coverage for older versions.
- Simplify the PHP constraint to `^8.0`, which already includes PHP 8.4 and 8.5.
- Expand English package metadata, search keywords, and promotional documentation.

### Validation

- All 35 PHPUnit tests pass across nine PHP/Laravel combinations, including
  PHP 8.4 and 8.5 with Laravel 12 and 13.
- Controller attribute tests cover manual query application, concrete filter
  injection, route parameters, invokable and inherited actions, cached routes,
  dependency injection, and isolation between requests.

### Upgrade notes

Existing model scopes and concrete filter injection continue to work unchanged.
Controller attributes are optional and only select the filter class; queries and
filter application remain under the controller's control. See the
[upgrade guide](https://github.com/plutuss/query-filter-laravel/blob/1.4.0/docs/UPGRADING.md).

## 1.3.0 — 2026-11-17

### Added

- Optional `allowedFilters` list for controlling which public methods are exposed.
- Optional Laravel validation rules, applied before modifying queries, with support
  for method aliases and nested array rules.
- PHPUnit coverage for filters, validation, Livewire parameters, command generation,
  and Eloquent queries against SQLite.
- GitHub Actions compatibility matrix for PHP 8.0–8.3 and Laravel 9–12.
- Examples for array ID validation, relationships, dates, and Livewire, plus an upgrade guide.

### Fixed

- Direct filter application no longer returns null from an array-typed accessor.
- Explicit empty data no longer falls back to HTTP query parameters.
- Zero and false values are preserved during filtering and Livewire extraction.
- Query parameters cannot invoke reserved, nonpublic, static, or magic methods.
- The generator respects custom application namespaces and creates a usable example.

### Upgrade notes

An explicit empty array now means no filters. Use omitted data or `null` to read
HTTP query parameters. Zero and false now reach filter methods. See
[the upgrade guide](https://github.com/plutuss/query-filter-laravel/blob/1.3.0/docs/UPGRADING.md) for details.
