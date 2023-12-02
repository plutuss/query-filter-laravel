## Installed packages

Laravel:
- [GitHub](https://github.com/plutuss/query-filter-laravel).

```shell
 composer require plutuss/query-filter-laravel
```

```shell
 php artisan make:query-filter  NameFilter
```


```php
<?php

declare(strict_types=1);


namespace App\Filters;


use Plutuss\Filter\QueryFilter;

class UserFilter extends QueryFilter
{
    public function name($value)
    {
        return $this->builder
            ->when($value, function ($query) use ($value) {
                return $query->where('name', 'like', '%' . $value . '%');
            });
    }
}
```

- App\Models  
```shell
Plutuss\Traits\HasQueryFilter
```
```php
<?php

namespace App\Models;

use Plutuss\Traits\HasQueryFilter;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasQueryFilter;
```

- Controller
```php
<?php

namespace App\Http\Controllers;

use App\Filters\UserFilter;
use App\Models\User;

class UserController extends Controller
{
    public function index(UserFilter $userFilter)
    {
        $users = User::query()->filter($userFilter)->get();
       return $users
    }
}
```
