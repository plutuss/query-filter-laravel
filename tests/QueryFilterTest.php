<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Connection;
use Plutuss\Tests\TestCase;
use Plutuss\Filter\QueryFilter;
use Plutuss\Traits\HasQueryFilter;
use Plutuss\Traits\Livewire\WithQueryParameters;

final class QueryFilterTest extends TestCase
{
    private function builder(): Builder
    {
        return new Builder(new BaseBuilder(new Connection(null)));
    }

    public function testDirectApplyUsesRequestWhenDataIsUnset(): void
    {
        $builder = $this->builder();
        self::assertSame($builder, (new ExampleFilter())->apply($builder));
        self::assertSame(['1'], $builder->getBindings());
    }

    public function testExplicitEmptyDataDoesNotUseRequest(): void
    {
        $builder = $this->builder();
        (new ExampleFilter())->setData([])->apply($builder);
        self::assertSame([], $builder->getBindings());
    }

    public function testZeroAndFalseArePassedToFilters(): void
    {
        foreach ([0, '0', false] as $value) {
            $builder = $this->builder();
            (new ExampleFilter())->setData(['active' => $value])->apply($builder);
            self::assertSame([$value], $builder->getBindings());
        }
    }

    public function testEmptyValuesAndUnknownFieldsAreIgnored(): void
    {
        foreach ([null, '', []] as $value) {
            $builder = $this->builder();
            (new ExampleFilter())->setData(['active' => $value, 'unknown' => 'x'])->apply($builder);
            self::assertSame([], $builder->getBindings());
        }
    }

    public function testSnakeCaseResolvesToPublicFilter(): void
    {
        $builder = $this->builder();
        (new ExampleFilter())->setData(['first_name' => 'Ada'])->apply($builder);
        self::assertSame(['Ada'], $builder->getBindings());
        self::assertSame('first_name', $builder->getQuery()->wheres[0]['column']);
    }

    public function testRequestCannotInvokeInternalOrNonPublicMethods(): void
    {
        $builder = $this->builder();
        (new ExampleFilter())->setData([
            'apply' => 'x', 'ApPlY' => 'x', 'set_data' => 'x',
            'filters' => 'x', 'get_data' => 'x', 'param_to_array' => 'x',
            'is_filter_method' => 'x', 'hidden' => 'x', 'staticFilter' => 'x',
            '__construct' => 'x', 'active' => '0',
        ])->apply($builder);
        self::assertSame(['0'], $builder->getBindings());
    }

    public function testScopeHandlesDefaultNullAndExplicitData(): void
    {
        $model = new FilterModel();
        $filter = new ExampleFilter();
        $builder = $this->builder();
        $model->scopeFilter($builder, $filter, ['active' => 0]);
        self::assertSame([0], $builder->getBindings());
        $builder = $this->builder();
        $model->scopeFilter($builder, $filter);
        self::assertSame(['1'], $builder->getBindings());
        $builder = $this->builder();
        $model->scopeFilter($builder, $filter, null);
        self::assertSame(['1'], $builder->getBindings());
        $builder = $this->builder();
        $model->scopeFilter($builder, $filter, []);
        self::assertSame([], $builder->getBindings());
    }

    public function testLivewireParametersSupportOptionsAndFalsyValues(): void
    {
        $component = new class {
            use WithQueryParameters;
            public $queryString = ['active', 'enabled' => ['except' => ''], 'search', 'tags'];
            public $active = '0';
            public $enabled = false;
            public $search = '';
            public $tags = [];
            public function parameters(): array { return $this->getQueryParams(); }
        };
        self::assertSame(['active' => '0', 'enabled' => false], $component->parameters());
    }
}

class ExampleFilter extends QueryFilter
{
    public function active($value): void { $this->builder->where('active', $value); }
    public function firstName($value): void { $this->builder->where('first_name', $value); }
    protected function hidden($value): void { throw new LogicException('Must not be called'); }
    public static function staticFilter($value): void { throw new LogicException('Must not be called'); }
}

class FilterModel extends Model
{
    use HasQueryFilter;
}
