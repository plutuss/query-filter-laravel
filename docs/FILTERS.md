# Filter reference

[Back to README](../README.md) · [Validation](VALIDATION.md) · [Controller attributes](CONTROLLER_ATTRIBUTES.md)

## Generate a filter

```shell
php artisan make:query-filter UserFilter
php artisan make:query-filter Admin/UserFilter
```

The first command normally creates `app/Filters/UserFilter.php`. The second creates
`app/Filters/Admin/UserFilter.php`. The application's configured namespace and app
path are respected. Existing files are preserved.

The generated class includes an allowlist, validation rules, and a sample `name`
method. Adjust those to match your model's columns before using it.

## Define methods

Extend `Plutuss\Filter\QueryFilter` and modify its protected `$builder` property:

```php
namespace App\Filters;

use Plutuss\Filter\QueryFilter;

class UserFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['name', 'active'];

    protected array $rules = [
        'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        'active' => ['sometimes', 'nullable', 'boolean'],
    ];

    public function name(string $value): void
    {
        $this->builder->where('name', $value);
    }

    public function active($value): void
    {
        $this->builder->where('active', $value);
    }
}
```

Methods receive one input value. Their return values are ignored; change
`$this->builder` directly. `void` methods are convenient for this purpose.
Methods requiring additional arguments are not supported by input dispatch.

## Add the model scope

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Plutuss\Traits\HasQueryFilter;

class User extends Model
{
    use HasQueryFilter;
}
```

The `filter()` scope accepts a `QueryFilterInterface` implementation and optional
explicit data. It returns the Eloquent builder, so normal chaining continues:

```php
use App\Filters\UserFilter;
use App\Models\User;

$users = User::query()
    ->where('is_public', true)
    ->filter(new UserFilter())
    ->orderBy('id')
    ->paginate(20);
```

Existing conditions and model global scopes remain on the query. The filter itself
does not call `get()`, `first()`, or `paginate()` unless your own method does so.

## Input sources and precedence

| Call | Input used |
| --- | --- |
| `User::query()->filter($filter)` | Current HTTP query parameters |
| `User::query()->filter($filter, null)` | Current HTTP query parameters |
| `User::query()->filter($filter, ['active' => 0])` | Only the explicit array |
| `User::query()->filter($filter, [])` | No input values; no request fallback |
| `$filter->apply($builder)` on a fresh filter | Current HTTP query parameters |
| `$filter->setData($data)->apply($builder)` | The array supplied by `setData()` |

Explicit arrays replace the query string; they are not merged with it. The scope
sets the filter's data on every call, so pass explicit data as the scope's second
argument rather than setting it on the filter before calling the scope.

Only URL query parameters are read automatically. JSON and POST bodies require
explicit input, for example `->filter($filter, $request->validated())`.

Validation runs even when the input array is empty. A `required` rule can therefore
reject `[]`; see [validation](VALIDATION.md).

## Names and aliases

The package first looks for a method with the supplied parameter name. If none is
eligible, it converts the input name to camelCase and tries again.

| Parameter | Recommended method |
| --- | --- |
| `name` | `name()` |
| `first_name` | `firstName()` |
| `created_after` | `createdAfter()` |
| `post_title` | `postTitle()` |

A camelCase method accepts its exact name and the snake_case alias shown above.
A method declared as `first_name()` accepts that exact parameter, but the package
does not convert a camelCase parameter back to snake_case to find it.

Matching methods run in input order. Sending both aliases for the same method
causes both values to be validated and both calls to run. Send one spelling per
filter to avoid duplicate or conflicting constraints.

## Allowed methods

```php
protected ?array $allowedFilters = ['name', 'active', 'createdAfter'];
```

Use declared method names in this list, including camelCase and exact casing.
An empty array disables method dispatch. The default `null` keeps automatic
discovery of eligible public instance methods, including inherited methods.

Base `QueryFilter` methods, magic methods, static methods, and nonpublic methods
are excluded from input dispatch. The allowlist cannot enable them. Public
application helper methods remain discoverable when no allowlist is set, so use
an explicit list for classes with public helpers.

The allowlist controls method calls, not which validation rules run. Validation
rules you declare still apply even if the corresponding method is not allowed.

## Empty values, zero, and false

| Value after validation | Method called? |
| --- | --- |
| `0`, `"0"`, `false` | Yes |
| `null`, `""`, `[]` | No |
| Nonempty scalar or array | Yes, if the method is allowed |
| Unknown parameter | No matching method is called |

The package does not trim, cast, split, or otherwise normalize input by default.
Laravel middleware can change the request before the filter reads it.
Do not use `when($value)` inside a filter that must handle zero or false: that
truthiness check would skip the value after the package has passed it through.

## Date ranges

Add methods to your filter and their names to its allowlist:

```php
protected array $rules = [
    'created_after' => ['sometimes', 'date_format:Y-m-d'],
    'created_before' => ['sometimes', 'date_format:Y-m-d'],
];

public function createdAfter(string $value): void
{
    $this->builder->whereDate('created_at', '>=', $value);
}

public function createdBefore(string $value): void
{
    $this->builder->whereDate('created_at', '<=', $value);
}
```

Example: `?created_after=2026-01-01&created_before=2026-12-31`.
These rules check date format, not range ordering. Add application validation if
the end date must be on or after the start date. `whereDate` compares dates rather
than a precise timestamp range; choose boundaries appropriate to your application's timezone.

## Related records

Use normal Eloquent relationship constraints inside a filter method:

```php
public function postTitle(string $value): void
{
    $this->builder->whereHas('posts', function ($query) use ($value) {
        $query->where('title', $value);
    });
}
```

This assumes the model defines a `posts()` relationship. Add `postTitle` to the
allowlist and a `post_title` string rule if validation is enabled. Input such as
`?post_title=Laravel` then selects matching parent records.

## Comma-separated values

Array query syntax is the default recommendation for ID lists; see
[array validation](VALIDATION.md#arrays-of-user-ids). If an endpoint deliberately
accepts a comma-separated string, parse it in its filter method:

```php
protected array $rules = [
    'ids' => ['sometimes', 'string', 'regex:/^[0-9]+(,[0-9]+)*$/'],
];

public function ids(string $value): void
{
    $this->builder->whereIn('id', $this->paramToArray($value));
}
```

The protected `paramToArray()` helper splits on `$delimiter`, which defaults to
`,` and can be overridden on a subclass. It does not trim, validate, or convert
elements. This CSV example is an alternative to the array rule, not a way to make
the array rule accept a string automatically.

## Direct application and non-HTTP code

You can apply a filter without adding the model trait:

```php
$filter = new \App\Filters\UserFilter();
$query = $filter->setData(['active' => 0])->apply(\App\Models\User::query());
$users = $query->get();
```

Supply explicit data in jobs, commands, or other code without an HTTP request.
Direct application mutates and returns the supplied builder. Reusing an already
filtered builder does not remove earlier constraints. Reusing a filter with a new
builder also retains explicitly set data until `setData()` changes it; use fresh
instances when separate operations need separate input.

## Public API summary

| API | Purpose |
| --- | --- |
| `HasQueryFilter::scopeFilter($builder, $filter, $data = null)` | Apply a filter through an Eloquent model scope |
| `QueryFilter::setData(array $data): static` | Set explicit input and return the filter |
| `QueryFilter::filters()` | Return explicit input, or current query parameters when unset |
| `QueryFilter::apply(Builder $builder): Builder` | Validate input, invoke eligible methods, and return the builder |
| Protected `$allowedFilters` | Optional allowlist of declared method names |
| Protected `$rules` | Laravel validation rules |
| Protected `$builder` | The Eloquent builder currently being filtered |
| Protected `$delimiter` and `paramToArray()` | Optional string-splitting helper |

`QueryFilterInterface` exposes `setData()`, `filters()`, and `apply()`. Custom
implementations are accepted by the model scope and controller attribute; the
base class's dispatch and validation behavior applies when you extend `QueryFilter`.
