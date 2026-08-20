<?php

namespace Karnoweb\Hr\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\EmployeeSalaryItem;
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\QueryExceptionClassifier;

/**
 * Employee salary lifecycle: assign and change current salary (HR-070).
 *
 * Contract-type-aware salary rules (HR-077): **DEFERRED — NEEDS VERIFICATION**.
 * No automatic PartTime/Internship eligibility rules until business policy is confirmed.
 *
 * Enforces exactly one current salary per employee via the `current_key` DB
 * invariant (see docs/concepts/architecture.md).
 */
class SalaryService
{
    public function __construct(
        protected SalaryCalculator $calculator,
    ) {}

    /**
     * Assign the first current salary for an employee.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(Employee $employee, array $data): EmployeeSalary
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            if ($this->lockCurrentSalary($employee) !== null) {
                throw new DuplicateActiveRecordException(
                    'Employee already has a current salary. Use changeSalary() instead.'
                );
            }

            return $this->createCurrentSalary($employee, $data);
        });
    }

    /**
     * Close the current salary and open a new one atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function changeSalary(Employee $employee, array $data): EmployeeSalary
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $current = $this->lockCurrentSalary($employee);

            if ($current === null) {
                throw new InvalidArgumentException('Employee has no current salary to change.');
            }

            $newEffectiveDate = isset($data['effective_date'])
                ? Carbon::parse($data['effective_date'])->startOfDay()
                : Carbon::now()->startOfDay();

            $closeDate = $newEffectiveDate->copy()->subDay();

            if ($closeDate->lt($current->effective_date->copy()->startOfDay())) {
                $closeDate = $current->effective_date->copy()->startOfDay();
            }

            $this->closeSalary($current, $closeDate);

            return $this->createCurrentSalary($employee, array_merge($data, [
                'effective_date' => $newEffectiveDate,
            ]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(EmployeeSalary $employeeSalary): array
    {
        return $this->calculator->calculate($employeeSalary);
    }

    public function currentSalary(Employee $employee): ?EmployeeSalary
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Salary rows whose effective window overlaps [from, to] (inclusive).
     *
     * @return Collection<int, EmployeeSalary>
     */
    public function salariesForPeriod(Employee $employee, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        $start = Carbon::parse($from)->startOfDay()->toDateString();
        $end = Carbon::parse($to)->startOfDay()->toDateString();

        return EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start);
            })
            ->with(['items.salaryItem', 'salaryStructure.items.salaryItem'])
            ->orderBy('effective_date')
            ->get();
    }

    protected function lockCurrentSalary(Employee $employee): ?EmployeeSalary
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->where('is_current', true)
            ->lockForUpdate()
            ->first();
    }

    protected function closeSalary(EmployeeSalary $salary, Carbon $endDate): void
    {
        $salary->update([
            'is_current' => false,
            'end_date' => $endDate->toDateString(),
            'current_key' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createCurrentSalary(Employee $employee, array $data): EmployeeSalary
    {
        if (! isset($data['base_salary'])) {
            throw new InvalidArgumentException('base_salary is required.');
        }

        HrDocumentReference::assertValid(isset($data['hr_document_id']) ? (int) $data['hr_document_id'] : null);

        $effectiveDate = isset($data['effective_date'])
            ? Carbon::parse($data['effective_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $payload = [
            'employee_id' => $employee->id,
            'salary_structure_id' => $data['salary_structure_id'] ?? null,
            'base_salary' => $data['base_salary'],
            'effective_date' => $effectiveDate->toDateString(),
            'end_date' => null,
            'hr_document_id' => $data['hr_document_id'] ?? null,
            'is_current' => true,
            'current_key' => $employee->id,
        ];

        try {
            $salary = EmployeeSalary::query()->create($payload);
        } catch (QueryException $e) {
            if (QueryExceptionClassifier::isUniqueViolation($e)) {
                throw new DuplicateActiveRecordException(
                    'Could not create current salary: duplicate current salary for employee.',
                    previous: $e
                );
            }

            throw $e;
        }

        $this->syncItems($salary, $data['items'] ?? []);

        return $salary->refresh()->load(['items.salaryItem', 'salaryStructure.items.salaryItem']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function syncItems(EmployeeSalary $salary, array $items): void
    {
        foreach ($items as $itemData) {
            $salaryItemId = $itemData['salary_item_id'] ?? null;

            if ($salaryItemId === null && isset($itemData['code'])) {
                $salaryItemId = SalaryItem::query()
                    ->where('code', $itemData['code'])
                    ->value('id');
            }

            if ($salaryItemId === null || ! isset($itemData['value'])) {
                throw new InvalidArgumentException('Each salary item override requires salary_item_id or code plus value.');
            }

            EmployeeSalaryItem::query()->updateOrCreate(
                [
                    'employee_salary_id' => $salary->id,
                    'salary_item_id' => $salaryItemId,
                ],
                [
                    'value' => $itemData['value'],
                ]
            );
        }
    }
}
