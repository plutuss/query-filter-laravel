<?php

declare(strict_types=1);

namespace Plutuss\Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Validation\ValidationException;
use Plutuss\Filter\QueryFilter;

final class FilterValidationTest extends TestCase
{
    private function builder(): Builder
    {
        return new Builder(new BaseBuilder(new Connection(null)));
    }

    public function testAllowlistExcludesOtherPublicMethodsAndAcceptsSnakeCase(): void
    {
        $filter = new RestrictedFilter();
        $builder = $this->builder();
        $filter->setData(['helper' => 'x', 'first_name' => 'Ada', 'apply' => 'x'])->apply($builder);
        self::assertSame(['Ada'], $builder->getBindings());
    }

    public function testEmptyAllowlistDisablesAllFilters(): void
    {
        $filter = new class extends RestrictedFilter {
            protected ?array $allowedFilters = [];
        };
        $builder = $this->builder();
        $filter->setData(['firstName' => 'Ada'])->apply($builder);
        self::assertSame([], $builder->getBindings());
    }

    public function testInvalidInputIsRejectedBeforeQueryIsModified(): void
    {
        $builder = $this->builder();
        try {
            (new RestrictedFilter())->setData(['firstName' => 'Ada', 'first_name' => ['Ada']])->apply($builder);
            self::fail('Array input must fail string validation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('first_name', $exception->errors());
            self::assertSame([], $builder->getQuery()->wheres);
        }
    }

    public function testValidationPreservesFieldsWithoutRules(): void
    {
        $filter = new class extends RestrictedFilter {
            protected array $rules = ['unrelated' => ['sometimes', 'string']];
        };
        $builder = $this->builder();
        $filter->setData(['first_name' => 'Ada'])->apply($builder);
        self::assertSame(['Ada'], $builder->getBindings());
    }

    public function testCamelCaseCannotBypassSnakeCaseValidationRules(): void
    {
        $this->expectException(ValidationException::class);
        (new RestrictedFilter())->setData(['firstName' => ['Ada']])->apply($this->builder());
    }

    public function testBothAliasesAreValidatedWhenPresentTogether(): void
    {
        foreach ([
            ['first_name' => ['Ada'], 'firstName' => 'Grace'],
            ['first_name' => 'Ada', 'firstName' => ['Grace']],
        ] as $data) {
            try {
                (new RestrictedFilter())->setData($data)->apply($this->builder());
                self::fail('Both aliases must be validated.');
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
        }
    }

    public function testNestedArrayRulesAreAppliedToAliases(): void
    {
        $filter = new class extends QueryFilter {
            protected array $rules = ['user_ids' => ['array'], 'user_ids.*' => ['integer']];
            public function userIds(array $value): void { $this->builder->whereIn('id', $value); }
        };
        $this->expectException(ValidationException::class);
        $filter->setData(['userIds' => ['invalid']])->apply($this->builder());
    }

    public function testRequiredRulesAreEnforcedForMissingValues(): void
    {
        $filter = new class extends RestrictedFilter {
            protected array $rules = ['first_name' => ['required', 'string']];
        };
        $this->expectException(ValidationException::class);
        $filter->setData([])->apply($this->builder());
    }

    public function testInheritedFiltersAreAvailable(): void
    {
        $filter = new class extends RestrictedFilter {};
        $builder = $this->builder();
        $filter->setData(['first_name' => 'Ada'])->apply($builder);
        self::assertSame(['Ada'], $builder->getBindings());
    }
}

class RestrictedFilter extends QueryFilter
{
    protected ?array $allowedFilters = ['firstName'];

    protected array $rules = ['first_name' => ['sometimes', 'string']];

    public function firstName(string $value): void
    {
        $this->builder->where('first_name', $value);
    }

    public function helper($value): void
    {
        throw new \LogicException('Helper must not be exposed as a filter.');
    }
}
