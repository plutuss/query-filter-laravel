# Controller method attributes

[Back to README](../README.md) · [Filter reference](FILTERS.md) · [Validation](VALIDATION.md)

## What the attribute does

`#[UseQueryFilter(UserFilter::class)]` tells the package which implementation of
`QueryFilterInterface` to resolve for the current controller action. The filter
class must implement that interface; extending the package's `QueryFilter` is the
usual way to do so.

You still choose the model, build the query, apply the filter, and execute the
query. An attribute by itself does not filter records or run validation.

This feature is available from version 1.4.0 and works alongside traditional
concrete filter injection.

## Complete example

The model needs the same trait used by traditional filtering:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Plutuss\Traits\HasQueryFilter;

class User extends Model
{
    use HasQueryFilter;
}
```

Create a filter:

```php
namespace App\Filters;

use Plutuss\Filter\QueryFilter;

class UserFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['active'];

    protected array $rules = [
        'active' => ['sometimes', 'nullable', 'boolean'],
    ];

    public function active($value): void
    {
        $this->builder->where('active', $value);
    }
}
```

Select that filter on the controller action:

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
            ->orderBy('id')
            ->paginate(20);
    }
}
```

Register a normal Laravel controller route:

```php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
```

A request to `/users?active=0` applies the `active` method when the controller calls
`filter()`. Existing query conditions and model global scopes remain in effect.
The example assumes the `users` table has `active` and `is_public` columns.

## Traditional injection still works

You can keep an existing action unchanged:

```php
public function index(UserFilter $filter)
{
    return User::query()->filter($filter)->paginate(20);
}
```

| Style | Action argument | Filter selection |
| --- | --- | --- |
| Traditional | `UserFilter $filter` | The argument's concrete class |
| Attribute | `QueryFilterInterface $filter` | `#[UseQueryFilter(UserFilter::class)]` on the action |

If you annotate an action but continue to type its argument as a concrete class,
Laravel resolves that concrete class normally. Attribute selection is used when
the package resolves `QueryFilterInterface`.

## Query construction stays manual

You can add constraints, eager loading, ordering, pagination, or explicit input:

```php
#[UseQueryFilter(UserFilter::class)]
public function index(QueryFilterInterface $filter)
{
    return User::query()
        ->where('is_public', true)
        ->with('posts')
        ->filter($filter, ['active' => 1])
        ->orderByDesc('id')
        ->paginate(20);
}
```

If the action does not call `filter()` or `apply()`, no filtering or filter
validation takes place. Attributes are not an authorization mechanism: keep tenant
and access constraints in your application's query scopes, policies, or middleware.

## Request injection and route parameters

Normal Laravel arguments remain available:

```php
use Illuminate\Http\Request;

#[UseQueryFilter(UserFilter::class)]
public function index(Request $request, QueryFilterInterface $filter, string $team)
{
    return User::query()
        ->where('team_id', $team)
        ->filter($filter)
        ->paginate(20);
}
```

This action can be registered at `/teams/{team}/users`. Apply your application's
normal authorization before querying another team's records.

## Invokable and inherited actions

For an invokable controller, put the attribute on `__invoke()`:

```php
class UserIndexController
{
    #[UseQueryFilter(UserFilter::class)]
    public function __invoke(QueryFilterInterface $filter)
    {
        return User::query()->filter($filter)->get();
    }
}
```

Use the same imports as in the complete controller example. Register it with
`Route::get('/users', UserIndexController::class)`.

An inherited action retains its method attribute. An overridden method needs its
own attribute. Both ordinary and cached controller routes are supported; no extra
route middleware or custom base controller is required.

## Dependencies and custom bindings

The selected filter is resolved through Laravel's container, so constructor
dependencies work normally. Bind interfaces used by a filter's constructor in your
application service provider as usual.

The package registers its default `QueryFilterInterface` binding only if none
exists. An application binding for that interface takes precedence and may select
a different filter. Remove or adjust that custom binding if you want the package
to select filters from action attributes.

## Limits and setup errors

- Apply one `UseQueryFilter` attribute to a controller method. It is not a class
  attribute, a parameter attribute, or a repeatable attribute.
- Inject a required `QueryFilterInterface $filter` argument when using attribute
  selection. Optional or nullable defaults are subject to Laravel's normal
  dependency resolution behavior.
- The selected class must implement `QueryFilterInterface`; invalid classes raise
  `InvalidArgumentException` when the attribute is read.
- Without an attribute, resolving the interface through the package binding raises
  a `BindingResolutionException` explaining how to configure the action.
- Selection uses the current HTTP controller route. Direct PHP calls, console
  commands, and closure routes do not acquire an action attribute automatically.

For setup problems, see [troubleshooting](TROUBLESHOOTING.md).
