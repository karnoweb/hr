<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\CalculationType;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\SalaryItemType;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Services\SalaryService;
use Karnoweb\Hr\Tests\TestCase;

class SalaryTest extends TestCase
{
    public function test_assign_creates_current_salary_with_current_key(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $salary = Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
        ]);

        $this->assertTrue($salary->is_current);
        $this->assertSame($employee->id, $salary->current_key);
    }

    public function test_change_salary_closes_previous_and_opens_new_current(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $first = Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'effective_date' => '2026-01-01',
        ]);

        $second = Hr::salaries()->changeSalary($employee, [
            'base_salary' => 55_000_000,
            'effective_date' => '2026-04-01',
        ]);

        $first->refresh();

        $this->assertFalse($first->is_current);
        $this->assertNull($first->current_key);
        $this->assertSame('2026-03-31', $first->end_date->toDateString());

        $this->assertTrue($second->is_current);
        $this->assertSame($employee->id, $second->current_key);
    }

    public function test_historical_salaries_remain_queryable_after_change(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'effective_date' => '2026-01-01',
        ]);

        Hr::salaries()->changeSalary($employee, [
            'base_salary' => 55_000_000,
            'effective_date' => '2026-04-01',
        ]);

        $history = $employee->fresh()->salaries()->orderBy('effective_date')->get();

        $this->assertCount(2, $history);
        $this->assertSame('50000000.00', $history[0]->base_salary);
        $this->assertFalse($history[0]->is_current);
        $this->assertSame('2026-03-31', $history[0]->end_date->toDateString());
        $this->assertTrue($history[1]->is_current);
    }

    public function test_change_salary_can_link_hr_document(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'effective_date' => '2026-01-01',
        ]);

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::SalaryChange,
            'effective_date' => '2026-04-01',
            'status' => DocumentStatus::Approved,
        ]);

        $salary = Hr::salaries()->changeSalary($employee, [
            'base_salary' => 55_000_000,
            'effective_date' => '2026-04-01',
            'hr_document_id' => $document->id,
        ]);

        $this->assertSame($document->id, $salary->hr_document_id);
    }

    public function test_assign_rejects_second_current_salary(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Hr::salaries()->assign($employee, ['base_salary' => 50_000_000]);

        $this->expectException(DuplicateActiveRecordException::class);
        Hr::salaries()->assign($employee, ['base_salary' => 60_000_000]);
    }

    public function test_sequential_raises_keep_single_current_salary(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'effective_date' => '2026-01-01',
        ]);

        Hr::salaries()->changeSalary($employee, [
            'base_salary' => 52_000_000,
            'effective_date' => '2026-02-01',
        ]);

        Hr::salaries()->changeSalary($employee, [
            'base_salary' => 54_000_000,
            'effective_date' => '2026-03-01',
        ]);

        $this->assertSame(1, EmployeeSalary::query()->where('employee_id', $employee->id)->where('is_current', true)->count());
        $this->assertSame(3, EmployeeSalary::query()->where('employee_id', $employee->id)->count());
    }

    public function test_current_key_unique_constraint_at_database_level(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'base_salary' => 50_000_000,
            'effective_date' => '2026-01-01',
            'is_current' => true,
            'current_key' => $employee->id,
        ]);

        $this->expectException(QueryException::class);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'base_salary' => 55_000_000,
            'effective_date' => '2026-04-01',
            'is_current' => true,
            'current_key' => $employee->id,
        ]);
    }

    public function test_percentage_of_must_reference_existing_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalaryItem::query()->create([
            'code' => 'BAD_PCT',
            'name' => 'Bad',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Percentage,
            'default_value' => 10,
            'percentage_of' => 'MISSING',
        ]);
    }

    public function test_salary_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(SalaryService::class),
            Hr::salaries()
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
