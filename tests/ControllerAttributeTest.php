<?php

declare(strict_types=1);

namespace Plutuss\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use Plutuss\Attributes\UseQueryFilter;
use Plutuss\Filter\QueryFilter;
use Plutuss\Filter\QueryFilterInterface;
use Plutuss\Providers\QueryFilterServiceProvider;
use Plutuss\Traits\HasQueryFilter;

final class ControllerAttributeTest extends TestCase
{
    private Application $app;
    private Router $router;
    private Capsule $database;
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $validator = Container::getInstance()->make(ValidationFactory::class);
        $this->app = new Application(dirname(__DIR__));
        $this->app->instance('config', new Repository());
        $this->app->instance(ValidationFactory::class, $validator);
        $this->app->instance('validator', $validator);
        Facade::setFacadeApplication($this->app);
        $this->app->register(RoutingServiceProvider::class);
        $this->app->register(QueryFilterServiceProvider::class);
        $this->router = $this->app['router'];

        $this->previousResolver = Model::getConnectionResolver();
        $this->database = new Capsule();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->bootEloquent();
        $this->database->getConnection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active');
        });
        $this->database->getConnection()->table('users')->insert([
            ['id' => 1, 'name' => 'Ada', 'active' => 0],
            ['id' => 2, 'name' => 'Grace', 'active' => 1],
        ]);

        $this->router->get('/users', [AttributeController::class, 'index'])->name('users');
        $this->router->get('/names', [AttributeController::class, 'byName'])->name('names');
        $this->router->get('/manual', [AttributeController::class, 'manual'])->name('manual');
        $this->router->get('/concrete', [AttributeController::class, 'concrete'])->name('concrete');
        $this->router->get('/missing', [AttributeController::class, 'missing'])->name('missing');
        $this->router->get('/group/{id}', [AttributeController::class, 'group'])->name('group');
        $this->router->get('/invokable', InvokableAttributeController::class)->name('invokable');
        $this->router->get('/inherited', [InheritedAttributeController::class, 'index'])->name('inherited');
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

    private function dispatch(string $uri): array
    {
        $request = Request::create($uri);
        $this->app->instance('request', $request);

        return json_decode($this->router->dispatch($request)->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testAttributeResolvesFilterAndControllerBuildsQuery(): void
    {
        self::assertSame(['Ada'], $this->dispatch('/users?active=0'));
    }

    public function testAttributeDoesNotApplyOrValidateFiltersAutomatically(): void
    {
        self::assertSame(['Ada', 'Grace'], $this->dispatch('/manual?active[]=invalid'));
    }

    public function testFilterSelectionAndRequestDataDoNotLeakAcrossActions(): void
    {
        self::assertSame(['Ada'], $this->dispatch('/users?active=0'));
        self::assertSame(['Grace'], $this->dispatch('/names?name=Grace'));
        self::assertSame(['Ada', 'Grace'], $this->dispatch('/users'));
    }

    public function testConcreteFilterInjectionStillWorksWithoutAttribute(): void
    {
        self::assertSame(['Ada'], $this->dispatch('/concrete?active=0'));
    }

    public function testRouteParametersAndRequestInjectionStillWork(): void
    {
        self::assertSame(['id' => '17', 'path' => 'group/17', 'names' => ['Grace']], $this->dispatch('/group/17?active=1'));
    }

    public function testInvokableAndInheritedActionsSupportAttributes(): void
    {
        self::assertSame(['Ada'], $this->dispatch('/invokable?active=0'));
        self::assertSame(['Grace'], $this->dispatch('/inherited?active=1'));
    }

    public function testCompiledRoutesSupportAttributes(): void
    {
        $this->router->setCompiledRoutes($this->router->getRoutes()->compile());
        self::assertSame(['Ada'], $this->dispatch('/users?active=0'));
    }

    public function testValidationStillRunsWhenControllerAppliesFilter(): void
    {
        $this->expectException(ValidationException::class);
        $this->dispatch('/users?active[]=invalid');
    }

    public function testMissingAttributeExplainsHowToResolveInterface(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('UseQueryFilter');
        $this->dispatch('/missing');
    }

    public function testResolutionOutsideHttpRequestExplainsMissingAttribute(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('UseQueryFilter');
        $this->app->make(QueryFilterInterface::class);
    }

    public function testExistingApplicationBindingIsPreserved(): void
    {
        $filter = new AttributeActiveFilter();
        $this->app->instance(QueryFilterInterface::class, $filter);
        (new QueryFilterServiceProvider($this->app))->register();
        self::assertSame($filter, $this->app->make(QueryFilterInterface::class));
    }

    public function testInvalidFilterClassIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UseQueryFilter(\stdClass::class);
    }
}

class AttributeUser extends Model
{
    use HasQueryFilter;
    protected $table = 'users';
    public $timestamps = false;
}

class AttributeActiveFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['active'];
    protected array $rules = ['active' => ['sometimes', 'boolean']];
    public function active($value): void { $this->builder->where('active', $value); }
}

class AttributeFilterDependency
{
    public function normalize(string $name): string { return trim($name); }
}

class AttributeNameFilter extends QueryFilter
{
    private AttributeFilterDependency $dependency;
    public function __construct(AttributeFilterDependency $dependency) { $this->dependency = $dependency; }
    public function name(string $value): void { $this->builder->where('name', $this->dependency->normalize($value)); }
}

class AttributeController
{
    #[UseQueryFilter(AttributeActiveFilter::class)]
    public function index(QueryFilterInterface $filter): array
    {
        return AttributeUser::query()->where('id', '>', 0)->filter($filter)->orderBy('id')->pluck('name')->all();
    }

    #[UseQueryFilter(AttributeNameFilter::class)]
    public function byName(QueryFilterInterface $filter): array
    {
        return AttributeUser::query()->filter($filter)->pluck('name')->all();
    }

    #[UseQueryFilter(AttributeActiveFilter::class)]
    public function manual(QueryFilterInterface $filter): array
    {
        return AttributeUser::query()->orderBy('id')->pluck('name')->all();
    }

    public function concrete(AttributeActiveFilter $filter): array
    {
        return AttributeUser::query()->filter($filter)->pluck('name')->all();
    }

    public function missing(QueryFilterInterface $filter): array { return []; }

    #[UseQueryFilter(AttributeActiveFilter::class)]
    public function group(Request $request, QueryFilterInterface $filter, string $id): array
    {
        return ['id' => $id, 'path' => $request->path(), 'names' => AttributeUser::query()->filter($filter)->pluck('name')->all()];
    }
}

class InvokableAttributeController extends AttributeController
{
    #[UseQueryFilter(AttributeActiveFilter::class)]
    public function __invoke(QueryFilterInterface $filter): array { return $this->index($filter); }
}

class InheritedAttributeController extends AttributeController {}
