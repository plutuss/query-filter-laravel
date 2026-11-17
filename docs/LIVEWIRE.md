# Livewire query parameters

[Back to README](../README.md) · [Filter reference](FILTERS.md) · [Validation](VALIDATION.md)

## Purpose

`WithQueryParameters` extracts component properties listed in `$queryString` into
an array. Pass that array as the second argument to the model's `filter()` scope.
It is a property extraction helper; it does not configure Livewire routing,
validate component state, or execute a database query by itself.

Install and configure Livewire in the host application. This package does not
install Livewire as a Composer dependency.

## Complete component example

```php
namespace App\Livewire;

use App\Filters\UserFilter;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Plutuss\Traits\Livewire\WithQueryParameters;

class Users extends Component
{
    use WithPagination;
    use WithQueryParameters;

    public $name = '';
    public $active = '';

    protected $queryString = [
        'name' => ['except' => ''],
        'active' => ['except' => ''],
    ];

    public function updatedName(): void
    {
        $this->resetPage();
    }

    public function updatedActive(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.users', [
            'users' => User::query()
                ->filter(new UserFilter(), $this->getQueryParams())
                ->orderBy('id')
                ->paginate(20),
        ]);
    }
}
```

The model must use `HasQueryFilter`, and `UserFilter` should define allowed
`name()` and `active()` methods with suitable rules. Adjust the component namespace
and view location to match your application's Livewire setup.

Example view:

```blade
<div>
    <input type="text" wire:model.live.debounce.300ms="name" placeholder="Name">

    <select wire:model.live="active">
        <option value="">All statuses</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
    </select>

    @foreach ($users as $user)
        <div wire:key="user-{{ $user->id }}">{{ $user->name }}</div>
    @endforeach

    {{ $users->links() }}
</div>
```

The view uses Livewire 3-style `.live` bindings. Use the binding syntax supported
by your installed Livewire version. Pagination is handled by Livewire's
`WithPagination` trait, not by this package.

## Supported query string declarations

Both forms are accepted:

```php
protected $queryString = ['name', 'active'];
```

```php
protected $queryString = [
    'name' => ['except' => '', 'as' => 'search'],
    'active' => ['except' => ''],
];
```

The trait reads the declared component property name. In the second example, the
returned filter key is `name`, even if Livewire exposes that property as `search`
in the URL. Livewire manages options such as `except` and `as`; the package does
not implement those options itself.

Only the explicitly listed properties are extracted. Each listed property must
exist on the component. The trait does not inspect `#[Url]` attributes or properties
added dynamically through other Livewire mechanisms.

## Values and resetting filters

`0`, `"0"`, and `false` are included. Null, empty strings, and empty arrays are
omitted. For example, an inactive status of `"0"` is preserved rather than treated
as an empty selection.

Always pass the extracted array explicitly. When every component field is empty,
`getQueryParams()` returns `[]`, and `filter($filter, [])` prevents old HTTP query
parameters from being read as fallback input.

If you use only `#[Url]` properties, or need custom normalization, build the input
array yourself rather than relying on this trait:

```php
$query = User::query()->filter(new UserFilter(), [
    'name' => $this->name,
    'active' => $this->active,
]);
```

Filter validation rules run on that explicit array. Declare `nullable` rules when
your component can submit null, and reset pagination when filter values change.
