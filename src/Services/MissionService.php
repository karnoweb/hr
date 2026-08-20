<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\MissionRequest;
use Karnoweb\Hr\Support\DateRangeOverlap;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\WorkingDayCalculator;

/**
 * Mission (business trip) requests and lifecycle (HR-056–HR-058).
 */
class MissionService
{
    public function __construct(
        protected WorkingDayCalculator $workingDays,
        protected AttendanceService $attendance,
        protected DocumentService $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{create_document?: bool, use_calculated_days?: bool}  $options
     */
    public function request(Employee $employee, array $data, array $options = []): MissionRequest
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'] ?? $data['start_date'])->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('end_date must be on or after start_date.');
        }

        $useCalculatedDays = $options['use_calculated_days'] ?? ! isset($data['days']);
        $days = $useCalculatedDays
            ? (float) $this->workingDays->count($start, $end, $employee->branch_id)
            : (float) ($data['days'] ?? 0);

        return DB::transaction(function () use ($employee, $data, $start, $end, $days, $options) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNoMissionOverlap($employee, $start, $end);
            $this->assertNoLeaveOverlap($employee, $start, $end);

            HrDocumentReference::assertValid(isset($data['hr_document_id']) ? (int) $data['hr_document_id'] : null);

            $mission = MissionRequest::query()->create(array_merge($data, [
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'status' => LeaveRequestStatus::Pending,
            ]));

            if ($options['create_document'] ?? false) {
                $document = $this->documents->create(DocumentType::Mission, $employee, [
                    'mission_request_id' => $mission->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'destination' => $data['destination'] ?? null,
                    'purpose' => $data['purpose'] ?? null,
                ]);

                $mission->update(['hr_document_id' => $document->id]);
                $mission = $mission->refresh();
            }

            return $mission;
        });
    }

    public function approve(MissionRequest $mission): MissionRequest
    {
        return DB::transaction(function () use ($mission) {
            $employee = Employee::query()->whereKey($mission->employee_id)->lockForUpdate()->firstOrFail();
            $mission = MissionRequest::query()->whereKey($mission->getKey())->lockForUpdate()->firstOrFail();

            if ($mission->status !== LeaveRequestStatus::Pending) {
                throw new InvalidArgumentException('Only pending mission requests can be approved.');
            }

            if ($employee->status !== EmployeeStatus::Active) {
                throw new InvalidEmployeeLifecycleException(
                    'Only active employees can have missions approved.'
                );
            }

            $start = Carbon::parse($mission->start_date);
            $end = Carbon::parse($mission->end_date);
            $this->assertNoMissionOverlap($employee, $start, $end, $mission->id);
            $this->assertNoLeaveOverlap($employee, $start, $end);

            $mission->update(['status' => LeaveRequestStatus::Approved]);

            $this->attendance->markStatusForWorkingDays(
                $employee,
                Carbon::parse($mission->start_date),
                Carbon::parse($mission->end_date),
                AttendanceStatus::Mission
            );

            return $mission->refresh();
        });
    }

    public function reject(MissionRequest $mission, ?string $reason = null): MissionRequest
    {
        return DB::transaction(function () use ($mission, $reason) {
            $mission = MissionRequest::query()->whereKey($mission->getKey())->lockForUpdate()->firstOrFail();

            if ($mission->status !== LeaveRequestStatus::Pending) {
                throw new InvalidArgumentException('Only pending mission requests can be rejected.');
            }

            $mission->update([
                'status' => LeaveRequestStatus::Rejected,
                'purpose' => $reason ?? $mission->purpose,
            ]);

            return $mission->refresh();
        });
    }

    public function cancel(MissionRequest $mission, ?string $reason = null): MissionRequest
    {
        return DB::transaction(function () use ($mission, $reason) {
            $mission = MissionRequest::query()->whereKey($mission->getKey())->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->findOrFail($mission->employee_id);
            $today = Carbon::now()->startOfDay();

            if ($mission->status === LeaveRequestStatus::Pending) {
                $mission->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'purpose' => $reason ?? $mission->purpose,
                ]);

                return $mission->refresh();
            }

            if ($mission->status === LeaveRequestStatus::Approved) {
                if (Carbon::parse($mission->start_date)->lte($today)) {
                    throw new InvalidArgumentException(
                        'Approved mission that has already started cannot be cancelled through this method.'
                    );
                }

                $this->attendance->revertStatusForWorkingDays(
                    $employee,
                    Carbon::parse($mission->start_date),
                    Carbon::parse($mission->end_date)
                );

                $mission->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'purpose' => $reason ?? $mission->purpose,
                ]);

                return $mission->refresh();
            }

            throw new InvalidArgumentException('Mission request cannot be cancelled in its current status.');
        });
    }

    protected function assertNoMissionOverlap(Employee $employee, Carbon $start, Carbon $end, ?int $excludeId = null): void
    {
        $query = MissionRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved]);

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        foreach ($query->get(['start_date', 'end_date']) as $row) {
            if (DateRangeOverlap::rangesOverlap($start, $end, $row->start_date, $row->end_date)) {
                throw new InvalidArgumentException(
                    'Mission request overlaps an existing pending or approved mission.'
                );
            }
        }
    }

    protected function assertNoLeaveOverlap(Employee $employee, Carbon $start, Carbon $end): void
    {
        $requests = LeaveRequest::query()
            ->forEmployee($employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->get(['start_date', 'end_date']);

        foreach ($requests as $row) {
            if (DateRangeOverlap::rangesOverlap($start, $end, $row->start_date, $row->end_date)) {
                throw new InvalidArgumentException(
                    'Mission request overlaps an existing pending or approved leave request.'
                );
            }
        }
    }
}
