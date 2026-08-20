<?php

namespace Karnoweb\Hr\Services;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\ContractType;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Models\Contract;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\QueryExceptionClassifier;

/**
 * Contract lifecycle: hire, renew, extend, terminate.
 *
 * Enforces exactly one active contract per employee via the `active_key` DB
 * invariant (see docs/concepts/architecture.md).
 */
class ContractService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function hire(Employee $employee, array $data): Contract
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasActiveContract($employee)) {
                throw new DuplicateActiveRecordException(
                    'Employee already has an active contract. Use renew() or terminate() first.'
                );
            }

            return $this->createActiveContract($employee, $data);
        });
    }

    /**
     * Close the current active contract and open a new one atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function renew(Employee $employee, array $data): Contract
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $current = $this->lockActiveContract($employee);

            if ($current === null) {
                throw new InvalidArgumentException('Employee has no active contract to renew.');
            }

            $newStart = isset($data['start_date'])
                ? Carbon::parse($data['start_date'])->startOfDay()
                : Carbon::now()->startOfDay();

            $closeDate = $newStart->copy()->subDay();
            if ($closeDate->lt($current->start_date->copy()->startOfDay())) {
                $closeDate = $current->start_date->copy()->startOfDay();
            }

            $this->closeContract($current, $closeDate, ContractStatus::Ended);

            return $this->createActiveContract($employee, array_merge($data, [
                'start_date' => $newStart,
            ]));
        });
    }

    /**
     * Extend the end date of the employee's active contract.
     */
    public function extend(Employee $employee, DateTimeInterface|string $newEndDate): Contract
    {
        return DB::transaction(function () use ($employee, $newEndDate) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $contract = $this->lockActiveContract($employee);

            if ($contract === null) {
                throw new InvalidArgumentException('Employee has no active contract to extend.');
            }

            $end = Carbon::parse($newEndDate)->startOfDay();

            if ($end->lt($contract->start_date->copy()->startOfDay())) {
                throw new InvalidArgumentException('Contract end_date must be on or after start_date.');
            }

            $contract->update(['end_date' => $end->toDateString()]);

            return $contract->refresh();
        });
    }

    /**
     * Terminate the employee's active contract.
     */
    public function terminate(Employee $employee, DateTimeInterface|string|null $terminationDate = null): Contract
    {
        return DB::transaction(function () use ($employee, $terminationDate) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $contract = $this->lockActiveContract($employee);

            if ($contract === null) {
                throw new InvalidArgumentException('Employee has no active contract to terminate.');
            }

            $date = $terminationDate
                ? Carbon::parse($terminationDate)->startOfDay()
                : Carbon::now()->startOfDay();

            if ($date->lt($contract->start_date->copy()->startOfDay())) {
                throw new InvalidArgumentException('Contract termination date must be on or after start_date.');
            }

            $this->closeContract($contract, $date, ContractStatus::Terminated);

            return $contract->refresh();
        });
    }

    protected function hasActiveContract(Employee $employee): bool
    {
        return Contract::query()
            ->where('employee_id', $employee->id)
            ->where('status', ContractStatus::Active)
            ->exists();
    }

    protected function lockActiveContract(Employee $employee): ?Contract
    {
        return Contract::query()
            ->where('employee_id', $employee->id)
            ->where('status', ContractStatus::Active)
            ->lockForUpdate()
            ->first();
    }

    protected function closeContract(Contract $contract, Carbon $endDate, ContractStatus $status): void
    {
        $contract->update([
            'status' => $status,
            'end_date' => $endDate->toDateString(),
            'active_key' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createActiveContract(Employee $employee, array $data): Contract
    {
        HrDocumentReference::assertValid(isset($data['hr_document_id']) ? (int) $data['hr_document_id'] : null);

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $payload = [
            'employee_id' => $employee->id,
            'contract_number' => $data['contract_number'] ?? null,
            'type' => $data['type'] ?? ContractType::Permanent,
            'start_date' => $startDate->toDateString(),
            'end_date' => isset($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null,
            'status' => ContractStatus::Active,
            'active_key' => $employee->id,
            'terms' => $data['terms'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];

        if ($payload['end_date'] !== null && Carbon::parse($payload['end_date'])->lt($startDate)) {
            throw new InvalidArgumentException('Contract end_date must be on or after start_date.');
        }

        try {
            return Contract::create($payload);
        } catch (QueryException $e) {
            if (QueryExceptionClassifier::isUniqueViolation($e)) {
                throw new DuplicateActiveRecordException(
                    'Could not create active contract: duplicate active contract or contract_number.',
                    previous: $e
                );
            }

            throw $e;
        }
    }
}
