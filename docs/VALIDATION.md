# Validation and input data

[Back to README](../README.md) · [Filter reference](FILTERS.md) · [Controller attributes](CONTROLLER_ATTRIBUTES.md)

## Enable validation

Declare Laravel rules in the protected `$rules` array of your filter:

```php
protected array $rules = [
    'name' => ['sometimes', 'nullable', 'string', 'max:255'],
    'active' => ['sometimes', 'nullable', 'boolean'],
];
```

These declarations belong inside a class extending `QueryFilter`. The default
rules array is empty, so validation is opt-in. Without rules, the package passes
eligible values to your methods without checking their types.

All configured rules run before any filter method changes the builder. A validation
failure raises `Illuminate\Validation\ValidationException`. Laravel normally
renders that exception as a 422 response for requests expecting JSON. Other HTTP
requests follow the application's normal validation response behavior.

The package does not catch this exception, customize its response, or replace your
exception handler. In tests or non-HTTP code, handle the exception normally.

## Optional and empty values

| Rule | Purpose |
| --- | --- |
| `sometimes` | Validate a field only when it is supplied |
| `nullable` | Allow null without failing subsequent nonimplicit type rules |
| `required` | Require a nonempty value |
| `string` | Require a string rather than an array |
| `boolean` | Accept Laravel's boolean rule values |
| `array` | Require an array rather than a CSV string |

`sometimes` and `nullable` solve different problems. A missing value may be
optional while an explicitly supplied null still needs `nullable`.
Laravel request middleware can convert empty strings to null before validation.

After validation, the package skips `null`, `""`, and `[]`. Zero and false are
preserved. A `required` or `min:1` rule can reject an empty value before that skip
happens. Explicit `[]` passed to the model scope does not bypass validation.

## Validation does not cast input

URL parameters are usually strings. For example, `?active=0` supplies `"0"`, not
an integer zero or a PHP boolean. Laravel's boolean validation accepts `true`,
`false`, `0`, `1`, `"0"`, and `"1"`; textual `"true"` and `"false"` are not the
same values.

Use the original value when it is suitable for your query, or normalize it
deliberately after validation. Avoid casting the string `"false"` directly to
boolean: PHP treats a nonempty string as true.

## Arrays of user IDs

Here is a complete filter for a list of existing user IDs:

```php
namespace App\Filters;

use Plutuss\Filter\QueryFilter;

class UserFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['ids'];

    protected array $rules = [
        'ids' => ['sometimes', 'array', 'min:1', 'max:100'],
        'ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
    ];

    public function ids(array $value): void
    {
        $this->builder->whereIn('id', $value);
    }
}
```

Send an array using URL query syntax:

```text
/users?ids[]=1&ids[]=5&ids[]=12
```

Or supply explicit data:

```php
User::query()->filter(new UserFilter(), ['ids' => [1, 5, 12]])->get();
```

| Rule | Meaning in this example |
| --- | --- |
| `ids: array` | The field must be an array |
| `min:1`, `max:100` | Between 1 and 100 array elements |
| `ids.*: required` | Each element must be present and nonempty |
| `integer` | Each element must satisfy integer validation; numeric strings from a URL are accepted |
| `distinct` | Reject duplicate IDs |
| `exists:users,id` | Each ID must exist in the `users` table's `id` column |

The `exists` rule uses Laravel's configured validation/database services and may
perform database reads during validation. Remove it if nonexistent IDs should
simply produce no matching rows. For tenant-specific validation, configure a
scoped Laravel existence rule or validate through your Form Request.

The following input is rejected by these rules:

| Input | Reason |
| --- | --- |
| `['ids' => '1,5,12']` | A string is not an array |
| `['ids' => []]` | Fails `min:1` |
| `['ids' => [1, 1]]` | Fails `distinct` |
| `['ids' => [1, 'abc']]` | An element fails `integer` |
| `['ids' => [999999]]` when that ID is absent | Fails `exists` |

If the field must always be supplied, replace `sometimes` with `required`.
If an empty list should be allowed and ignored, remove `min:1`.

## Names and validation aliases

Rule keys can use the snake_case input name while a method uses camelCase:

```php
protected ?array $allowedFilters = ['userIds'];

protected array $rules = [
    'user_ids' => ['sometimes', 'array', 'min:1'],
    'user_ids.*' => ['integer'],
];

public function userIds(array $value): void
{
    $this->builder->whereIn('id', $value);
}
```

Both `?user_ids[]=1` and `?userIds[]=1` resolve to `userIds()`. Rules for the root
field and its nested items follow the supplied alias. If both spellings are
provided, both are validated and both method calls can run.

Alias handling rewrites rule keys. It does not rewrite field names embedded
inside rule values, such as `after:created_after` or `required_with:other_field`.
For cross-field rules, use consistent input names or normalize names in a Form
Request before validation.

The package keeps fields that have no rules available for method dispatch. A
rules array is not an allowlist: use `$allowedFilters` to limit callable methods.

## Form Requests

You can validate input using Laravel's normal Form Request instead of putting
all rules on the filter:

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Replace with your application's authorization policy.
    }

    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'nullable', 'boolean'],
            'ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
```

Pass validated data explicitly, using traditional injection:

```php
public function index(UserIndexRequest $request, UserFilter $filter)
{
    return User::query()->filter($filter, $request->validated())->paginate(20);
}
```

Or use the controller attribute:

```php
#[UseQueryFilter(UserFilter::class)]
public function index(UserIndexRequest $request, QueryFilterInterface $filter)
{
    return User::query()->filter($filter, $request->validated())->paginate(20);
}
```

Import the request, model, filter, attribute, and interface used by these action
snippets. If the filter also declares `$rules`, those rules run again on the
explicit validated array. Keep both layers intentionally, or leave the filter's
rules empty when the Form Request owns validation.

## JSON and POST requests

Automatic filter input comes from the URL query string. Request body fields are
not included automatically. Pass validated body data explicitly:

```php
$query = User::query()->filter($filter, $request->validated());
```

This also prevents unrelated request fields from being dispatched as filters.
An empty validated array means no filter values and does not fall back to the URL.
