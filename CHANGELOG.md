# Changelog

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
