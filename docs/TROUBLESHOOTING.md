# Troubleshooting

[Back to README](../README.md) · [Filter reference](FILTERS.md) · [Controller attributes](CONTROLLER_ATTRIBUTES.md)

## The `filter()` scope is missing

Add `Plutuss\Traits\HasQueryFilter` to the Eloquent model being queried. The scope
is not installed globally on every model. Alternatively, use
`$filter->setData($data)->apply($builder)` directly without the trait.

## The Artisan command or attribute binding is missing

The package declares its service provider for Laravel package discovery. If your
application disables discovery, register
`Plutuss\Providers\QueryFilterServiceProvider::class` manually in the application's
provider configuration. Modern Laravel applications normally list providers in
`bootstrap/providers.php`; older applications use the providers configuration in
`config/app.php`.

After installation, these commands can refresh normal Laravel discovery and caches:

```shell
php artisan package:discover
php artisan optimize:clear
```

The controller attribute requires version 1.4.0 or later. Check the installed
package with `composer show plutuss/query-filter-laravel`.

## Laravel cannot resolve `QueryFilterInterface`

For attribute selection, put the attribute on the routed action:

```php
#[UseQueryFilter(UserFilter::class)]
public function index(QueryFilterInterface $filter)
{
    return User::query()->filter($filter)->get();
}
```

Import `Plutuss\Attributes\UseQueryFilter` and
`Plutuss\Filter\QueryFilterInterface`, along with your application's model and
filter. Without an attribute, inject the concrete `UserFilter` class instead.
Selection also requires a current HTTP controller route: constructing a controller
or calling its method directly does not perform Laravel action injection.

## The attribute selects a different filter than expected

Check whether the application has already bound `QueryFilterInterface`. Existing
bindings take precedence over the package's default resolver. Also check the
action argument: a concrete filter type is resolved independently of the attribute.

## The filter is injected but records are not filtered

The attribute only selects a class. Call `->filter($filter)` on the model query,
or call `$filter->apply($builder)` directly. The action decides whether and when
to apply filtering. Importing the attribute or annotating a method does not alter
queries automatically.

## `active=0` is ignored

The package passes `"0"` to the method. Check for a truthiness guard inside your
own filter, such as `when($value)` or `if ($value)`. Use a direct `where()` call
when zero and false are valid values.

## An array is received where a string was expected

A client can send `?name[]=Ada` instead of `?name=Ada`. Add a `string` validation
rule for scalar fields, and an `array` rule for list fields. A PHP parameter type
alone does not convert incorrect request data into a Laravel validation error.

## `ids=1,2,3` fails validation

That input is a string. For an array rule, send `?ids[]=1&ids[]=2&ids[]=3`, or pass
`['ids' => [1, 2, 3]]` as explicit data. If you deliberately accept CSV strings,
validate the string and split it in the method as shown in the
[filter reference](FILTERS.md#comma-separated-values).

## Empty input raises a validation error

Validation runs before empty values are skipped. `required`, `min:1`, and rules
without appropriate `nullable` handling can reject values you expected to ignore.
Use `sometimes` for optional fields and `nullable` for fields that may be null.

## A snake_case parameter does not call a method

Check the method's visibility, the allowlist's exact declared method name, and
whether the method is static. `created_after` can resolve to `createdAfter()`,
but a `createdAfter` parameter is not converted back to a declared
`created_after()` method. Base class methods cannot be enabled through the allowlist.

## A JSON or POST field is not used

Automatic input is limited to URL query parameters. Pass the body data explicitly,
preferably after a Form Request validates it:

```php
$query = User::query()->filter($filter, $request->validated());
```

## Explicit data behaves differently from `setData()`

The model scope sets its own data on each call. Pass your array as its second
argument. Use `setData()` when applying directly:

```php
User::query()->filter($filter, ['active' => 0])->get();
$filter->setData(['active' => 0])->apply(User::query())->get();
```

An explicit empty array does not fall back to the HTTP query string. Omitting the
scope's data argument, or passing null, reads that query string.

## More than one alias was supplied

If both `first_name` and `firstName` resolve to the same method, both calls can add
conditions. Send one spelling per filter. Validation rules follow both aliases,
but that does not deduplicate the method calls.

## Livewire filters do not reset or are not extracted

Pass `getQueryParams()` explicitly to the scope. Declare each extracted property
in `$queryString`, and ensure it exists on the component. `#[Url]` attributes are
not inspected by this helper. Reset the paginator when filter properties change.

See the [Livewire guide](LIVEWIRE.md) for a complete example and version-specific
binding notes.

## Reporting an issue

Include your PHP, Laravel, and package versions, the filter class, the model trait,
the controller action or component, and a minimal example of the input and expected
query behavior. Report issues at
[GitHub Issues](https://github.com/plutuss/query-filter-laravel/issues).
