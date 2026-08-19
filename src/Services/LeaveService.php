<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LeaveBalance;
use Karnoweb\Hr\Models\LeaveRequest;

/**
 * Service for leave requests and leave balance.
 *
 *
 * @see LeaveRequest
 * @see LeaveBalance
 */
class LeaveService
{
    /**
     * Create a leave request for the given employee.
     *
     * @param  Employee  $employee  Employee requesting leave.
     * @param  array<string, mixed>  $data  Leave data: start_date, end_date (parsed via Carbon if string), status (defaults to Pending). employee_id is set automatically.
     * @return LeaveRequest Created leave request.
     */
    public function request(Employee $employee, array $data): LeaveRequest
    {
        $data['employee_id'] = $employee->id;

        if (isset($data['start_date']) && ! $data['start_date'] instanceof \DateTimeInterface) {
            $data['start_date'] = Carbon::parse($data['start_date']);
        }
        if (isset($data['end_date']) && ! $data['end_date'] instanceof \DateTimeInterface) {
            $data['end_date'] = Carbon::parse($data['end_date']);
        }

        if (! isset($data['status'])) {
            $data['status'] = LeaveRequestStatus::Pending;
        }

        return LeaveRequest::create($data);
    }

    /**
     * Get leave balance for an employee for a given year and leave type.
     *
     * @param  Employee  $employee  Employee to get balance for.
     * @param  int  $year  Calendar year.
     * @param  string  $type  Leave type (e.g. annual, sick).
     * @return LeaveBalance|null The balance record or null if none.
     */
    public function balance(Employee $employee, int $year, string $type): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->forEmployee($employee->id)
            ->forYear($year)
            ->where('type', $type)
            ->first();
    }
}
