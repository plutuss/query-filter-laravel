# Query Filter Laravel

Laravel Eloquent query filtering with request validation, filter allowlists,
array filters, relationship queries, and Livewire support.

Move filtering logic into reusable PHP classes. Apply HTTP query parameters or
explicit input to Eloquent queries, validate values before changing the query,
and control which filter methods callers can access.

- Filter by user IDs, status, dates, and related records.
- Validate scalar values and arrays with Laravel validation rules.
- Generate filter classes with `php artisan make:query-filter`.
- Select a filter for a controller action with a PHP method attribute.
- Read Livewire query string properties, including zero and false values.
- Tested with PHP 8.0–8.5 and Laravel 9–13 using PHPUnit and SQLite.

[Packagist](https://packagist.org/packages/plutuss/query-filter-laravel) ·
[Releases](https://github.com/plutuss/query-filter-laravel/releases) ·
[Report an issue](https://github.com/plutuss/query-filter-laravel/issues) ·
[MIT license](LICENSE)

## Installation

```shell
composer require plutuss/query-filter-laravel
php artisan make:query-filter UserFilter
```

The command creates a filter under your application namespace in `Filters`.
Nested names such as `Admin/UserFilter` are supported. Existing files are preserved.

See [changes in 1.4.0](CHANGELOG.md) and the [upgrade guide](docs/UPGRADING.md).

## Define a filter

```php
namespace App\Filters;

use Plutuss\Filter\QueryFilter;

class UserFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['name', 'active', 'createdAfter', 'ids', 'postTitle'];

    protected array $rules = [
        'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        'active' => ['sometimes', 'nullable', 'boolean'],
        'created_after' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        'ids' => ['sometimes', 'array'],
        'ids.*' => ['integer'],
        'post_title' => ['sometimes', 'nullable', 'string'],
    ];

    public function name(string $value): void
    {
        $this->builder->where('name', $value);
    }

    public function active($value): void
    {
        $this->builder->where('active', $value);
    }

    public function createdAfter(string $value): void
    {
        $this->builder->whereDate('created_at', '>=', $value);
    }

    public function ids(array $value): void
    {
        $this->builder->whereIn('id', $value);
    }

    public function postTitle(string $value): void
    {
        $this->builder->whereHas('posts', function ($query) use ($value) {
            $query->where('title', $value);
        });
    }
}
```

`allowedFilters` contains method names, including camelCase names. A query parameter
such as `created_after` resolves to `createdAfter`. Validation rules also apply to
that method's aliases, including nested rules such as `user_ids.*`.

Set `allowedFilters` to `[]` to disable all filters. Its default is `null`, which
preserves automatic discovery of public instance methods. Base class methods,
magic methods, static methods, and nonpublic methods cannot be invoked as filters.
Use an explicit list to keep public helper methods private to your application flow.

Validation is optional and uses Laravel rules. All rules run before the query is
modified. Invalid input throws Laravel's `ValidationException`, which Laravel
normally renders as a 422 response for JSON requests. Validation does not cast
values: query parameters such as `active=0` remain strings. An existing Form Request
can validate input instead; pass its validated data explicitly to the model scope.

## Apply filters

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Plutuss\Traits\HasQueryFilter;

class User extends Model
{
    use HasQueryFilter;
}
```

```php
// Uses HTTP query parameters, for example ?active=0&created_after=2025-01-01.
$users = User::query()->filter(new UserFilter())->get();

// Explicit input replaces query parameters.
$users = User::query()->filter(new UserFilter(), ['ids' => [1, 3]])->get();

// A Form Request can supply validated input.
$users = User::query()->filter(new UserFilter(), $request->validated())->get();

// An empty array applies no filters. Null uses HTTP query parameters.
$users = User::query()->filter(new UserFilter(), [])->get();
```

Values `0`, `"0"`, and `false` reach filter methods. Values `null`, `""`, and `[]`
are skipped after validation; use `nullable` if a rule should accept null.
Unknown parameters are ignored unless your validation rules reject them.
Avoid truthiness checks such as `when($value)` inside filters that must accept zero.

## Controller method attributes

Use `#[UseQueryFilter]` to select which filter Laravel injects into a controller
action. You still build the Eloquent query and apply the filter yourself:

```php
namespace App\Http\Controllers;

use App\Filters\UserFilter;
use App\Models\User;
use Plutuss\Attributes\UseQueryFilter;
use Plutuss\Filter\QueryFilterInterface;

class UserController extends Controller
{
    #[UseQueryFilter(UserFilter::class)]
    public function index(QueryFilterInterface $filter)
    {
        return User::query()
            ->where('is_public', true)
            ->filter($filter)
            ->paginate(20);
    }
}
```

The model still needs `HasQueryFilter`. The package service provider registers
attribute resolution automatically through Laravel package discovery; no extra
middleware or base controller is required. Constructor dependencies on the filter
are resolved through Laravel's container.

The attribute only selects the filter class. It does not execute queries, apply
filters, or validate request data until you call `filter()` or `apply()`. You can
pass explicit data with `->filter($filter, $data)` as usual.

Each action can select a different filter. Named, invokable, and inherited actions
work with normal and cached controller routes. The argument must be typed as
`QueryFilterInterface` to use attribute selection. Without an attribute, inject
the concrete filter class as before. An existing application binding for
`QueryFilterInterface` takes precedence over the package's default resolver.

Attributes are read from the current Laravel controller route. Direct PHP method
calls and closure routes do not select an action filter through this attribute.

## Validate an array of user IDs

```php
protected ?array $allowedFilters = ['ids'];

protected array $rules = [
    'ids' => ['sometimes', 'array', 'min:1', 'max:100'],
    'ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
];

public function ids(array $value): void
{
    $this->builder->whereIn('id', $value);
}
```

Send `?ids[]=1&ids[]=5&ids[]=12` or pass `['ids' => [1, 5, 12]]` explicitly.
The array must contain 1–100 unique integer IDs belonging to existing users.
Numeric strings from the URL pass the `integer` rule without being cast to integers.
Remove `exists:users,id` if nonexistent IDs should simply produce no matching rows.
The `exists` rule uses Laravel's configured database connection.
Comma-separated input such as `?ids=1,5,12` is a string and fails the array rule.

Without `min:1`, an empty array passes these array rules and the filter method is
skipped. Include `required` instead of `sometimes` if the ID list must always be supplied.

## Livewire

```php
use Plutuss\Traits\Livewire\WithQueryParameters;

class Users extends \Livewire\Component
{
    use WithQueryParameters;

    public $active = '';
    protected $queryString = ['active' => ['except' => '']];

    public function render()
    {
        return view('livewire.users', [
            'users' => \App\Models\User::query()
                ->filter(new \App\Filters\UserFilter(), $this->getQueryParams())
                ->get(),
        ]);
    }
}
```

The trait reads properties listed in `$queryString`, supporting both simple names
and names with options. It preserves zero and false and omits null, empty strings,
and empty arrays. Option aliases affect the URL; filter keys use property names.
The trait does not read Livewire `#[Url]` attributes.

## Tests

PHP with SQLite (`pdo_sqlite`) and mbstring is required for the test suite.

```shell
composer install
composer test
```

Tests cover request input, explicit data, reserved methods, aliases, validation,
Livewire parameters, generated files, and actual Eloquent queries on SQLite.
GitHub Actions checks these combinations:

| Laravel | PHP |
| --- | --- |
| 9 | 8.0 |
| 10 | 8.1 |
| 11 | 8.2 |
| 12 | 8.3, 8.4, 8.5 |
| 13 | 8.3, 8.4, 8.5 |

The Composer constraint `^8.0` includes PHP 8.4 and 8.5. Laravel 13 requires
PHP 8.3 or newer; see the [Laravel release notes](https://laravel.com/framework/docs/13.x/releases).
Older framework releases are
included only for compatibility checks; the CI dependency step permits their
known advisories without changing consumer Composer settings.

## Releases

Releases use annotated Git tags such as `1.4.0`; Composer reads the package version
from the tag. Each release must have a matching section in [CHANGELOG.md](CHANGELOG.md).
Pushing a version tag runs the full test matrix, then publishes a GitHub Release
using that changelog section. A failed test prevents publication.
