<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LeaveBalance;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Support\PeriodRangeAllocator;

/**
 * Leave balance ledger: ensure rows, carry-over, termination policy (HR-053 / HR-055).
 */
class LeaveBalanceService
{
    public function __construct(
        protected PeriodRangeAllocator $allocator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function typeConfig(string $type): ?array
    {
        $types = config('hr.leave.types', []);

        return $types[$type] ?? null;
    }

    public function usesDayBalance(string $type): bool
    {
        if ($type === 'hourly') {
            return false;
        }

        $config = $this->typeConfig($type);

        return $config !== null
            && array_key_exists('days_per_year', $config)
            && $config['days_per_year'] !== null;
    }

    public function ensureBalance(Employee $employee, int $year, string $type): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $year, $type) {
            $existing = LeaveBalance::query()
                ->forEmployee($employee->id)
                ->forYear($year)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $config = $this->typeConfig($type);
            $entitled = (float) ($config['days_per_year'] ?? $config['fixed_days'] ?? 0);

            return LeaveBalance::query()->create([
                'employee_id' => $employee->id,
                'year' => $year,
                'type' => $type,
                'entitled_days' => $entitled,
                'used_days' => 0,
                'carried_days' => 0,
                'adjustment_days' => 0,
                'remaining_days' => $entitled,
            ]);
        });
    }

    public function lockBalance(Employee $employee, int $year, string $type): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->forEmployee($employee->id)
            ->forYear($year)
            ->where('type', $type)
            ->lockForUpdate()
            ->first();
    }

    public function pendingReservedDays(Employee $employee, int $year, string $type, ?int $excludeRequestId = null): float
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();

        $query = LeaveRequest::query()
            ->forEmployee($employee->id)
            ->where('type', $type)
            ->where('status', LeaveRequestStatus::Pending)
            ->whereDate('start_date', '<=', $yearEnd->toDateString())
            ->whereDate('end_date', '>=', $yearStart->toDateString());

        if ($excludeRequestId !== null) {
            $query->whereKeyNot($excludeRequestId);
        }

        $reserved = 0.0;

        foreach ($query->get(['start_date', 'end_date', 'days']) as $request) {
            $reserved += $this->allocator->allocateDaysInWindow(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
                $yearStart,
                $yearEnd,
                (float) $request->days,
                $employee->branch_id,
            );
        }

        return round($reserved, 2);
    }

    public function decrement(LeaveBalance $balance, float $days): void
    {
        $balance->update([
            'used_days' => (float) $balance->used_days + $days,
            'remaining_days' => max(0, (float) $balance->remaining_days - $days),
        ]);
    }

    public function increment(LeaveBalance $balance, float $days): void
    {
        $used = max(0, (float) $balance->used_days - $days);

        $balance->update([
            'used_days' => $used,
            'remaining_days' => max(0, (float) $balance->entitled_days + (float) $balance->carried_days + (float) $balance->adjustment_days - $used),
        ]);
    }

    /**
     * Apply carry-over from one calendar year to the next for all entitled employees.
     */
    public function carryOverYear(int $fromYear, int $toYear): int
    {
        $processed = 0;

        $balances = LeaveBalance::query()->forYear($fromYear)->get();

        foreach ($balances as $balance) {
            $config = $this->typeConfig($balance->type);

            if ($config === null || ! ($config['carry_over'] ?? false)) {
                continue;
            }

            DB::transaction(function () use ($balance, $fromYear, $toYear, $config, &$processed) {
                $locked = LeaveBalance::query()->whereKey($balance->getKey())->lockForUpdate()->firstOrFail();
                $employee = Employee::query()->findOrFail($locked->employee_id);

                $maxCarry = (float) ($config['carry_over_max'] ?? $locked->remaining_days);
                $carried = min((float) $locked->remaining_days, $maxCarry);

                $next = LeaveBalance::query()
                    ->forEmployee($employee->id)
                    ->forYear($toYear)
                    ->where('type', $locked->type)
                    ->lockForUpdate()
                    ->first();

                $entitled = (float) ($config['days_per_year'] ?? 0);

                if ($next === null) {
                    LeaveBalance::query()->create([
                        'employee_id' => $employee->id,
                        'year' => $toYear,
                        'type' => $locked->type,
                        'entitled_days' => $entitled,
                        'used_days' => 0,
                        'carried_days' => $carried,
                        'adjustment_days' => 0,
                        'remaining_days' => $entitled + $carried,
                        'notes' => "Carried {$carried} day(s) from {$fromYear}.",
                    ]);
                } else {
                    $next->update([
                        'carried_days' => $carried,
                        'remaining_days' => $entitled + $carried + (float) $next->adjustment_days - (float) $next->used_days,
                        'notes' => trim(($next->notes ?? '')." Carried {$carried} day(s) from {$fromYear}."),
                    ]);
                }

                $processed++;
            });
        }

        return $processed;
    }

    /**
     * Apply configured termination policy to open leave balances (HR-055).
     */
    public function handleTermination(Employee $employee): void
    {
        $policy = config('hr.leave.termination.balance_policy', 'forfeit');

        $balances = LeaveBalance::query()
            ->forEmployee($employee->id)
            ->lockForUpdate()
            ->get();

        foreach ($balances as $balance) {
            match ($policy) {
                'payout' => $balance->update([
                    'notes' => trim(($balance->notes ?? '').' [termination: payout eligible]'),
                ]),
                'carry' => $balance->update([
                    'notes' => trim(($balance->notes ?? '').' [termination: balance preserved]'),
                ]),
                default => $balance->update([
                    'remaining_days' => 0,
                    'notes' => trim(($balance->notes ?? '').' [termination: balance forfeited]'),
                ]),
            };
        }
    }
}
