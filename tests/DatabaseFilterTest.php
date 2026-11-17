<?php

declare(strict_types=1);

namespace Plutuss\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Plutuss\Filter\QueryFilter;
use Plutuss\Traits\HasQueryFilter;

final class DatabaseFilterTest extends TestCase
{
    private Capsule $database;
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->database = new Capsule();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->bootEloquent();
        $schema = $this->database->getConnection()->getSchemaBuilder();
        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active');
            $table->date('created_at');
        });
        $schema->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title');
        });
        $this->database->getConnection()->table('users')->insert([
            ['id' => 1, 'name' => 'Ada', 'active' => 0, 'created_at' => '2025-01-10'],
            ['id' => 2, 'name' => 'Grace', 'active' => 1, 'created_at' => '2025-02-10'],
            ['id' => 3, 'name' => 'Alan', 'active' => 1, 'created_at' => '2025-03-10'],
        ]);
        $this->database->getConnection()->table('posts')->insert([
            ['user_id' => 1, 'title' => 'Laravel'],
            ['user_id' => 2, 'title' => 'PHP'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->database->getDatabaseManager()->purge();
        Model::unsetConnectionResolver();
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        }
        parent::tearDown();
    }

    public function testCombinesFiltersAndPreservesExistingQueryConditions(): void
    {
        $names = DatabaseUser::query()->where('id', '>', 1)
            ->filter(new DatabaseUserFilter(), ['active' => '1', 'created_after' => '2025-03-01'])
            ->pluck('name')->all();
        self::assertSame(['Alan'], $names);
    }

    public function testFiltersRelatedRecords(): void
    {
        self::assertSame(['Ada'], DatabaseUser::query()
            ->filter(new DatabaseUserFilter(), ['post_title' => 'Laravel'])
            ->pluck('name')->all());
    }

    public function testAcceptsArrayFilters(): void
    {
        self::assertSame(['Ada', 'Alan'], DatabaseUser::query()
            ->filter(new DatabaseUserFilter(), ['ids' => [1, 3]])
            ->orderBy('id')->pluck('name')->all());
    }

    public function testRepeatedUseDoesNotLeakConditionsBetweenBuilders(): void
    {
        $filter = new DatabaseUserFilter();
        self::assertSame(['Ada'], DatabaseUser::query()->filter($filter, ['active' => false])->pluck('name')->all());
        self::assertSame(3, DatabaseUser::query()->filter($filter, [])->count());
        self::assertSame(2, DatabaseUser::query()->filter($filter)->count());
    }

    public function testNonMatchingFilterReturnsEmptyCollection(): void
    {
        self::assertTrue(DatabaseUser::query()->filter(new DatabaseUserFilter(), ['ids' => [99]])->get()->isEmpty());
    }
}

class DatabaseUser extends Model
{
    use HasQueryFilter;

    protected $table = 'users';
    public $timestamps = false;

    public function posts()
    {
        return $this->hasMany(DatabasePost::class, 'user_id');
    }
}

class DatabasePost extends Model
{
    protected $table = 'posts';
    public $timestamps = false;
}

class DatabaseUserFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['active', 'createdAfter', 'postTitle', 'ids'];

    protected array $rules = [
        'active' => ['sometimes', 'boolean'],
        'created_after' => ['sometimes', 'date_format:Y-m-d'],
        'post_title' => ['sometimes', 'string'],
        'ids' => ['sometimes', 'array'],
        'ids.*' => ['integer'],
    ];

    public function active($value): void { $this->builder->where('active', $value); }
    public function createdAfter(string $value): void { $this->builder->whereDate('created_at', '>=', $value); }
    public function postTitle(string $value): void
    {
        $this->builder->whereHas('posts', function ($query) use ($value) {
            $query->where('title', $value);
        });
    }
    public function ids(array $value): void { $this->builder->whereIn('id', $value); }
}
