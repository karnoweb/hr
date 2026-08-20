<?php

namespace Karnoweb\Hr\Tests\Integration;

use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\LeaveBalance;
use Karnoweb\Hr\Models\PayrollRecord;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;
use Karnoweb\Hr\Tests\TestCase;

/**
 * Proves HR works standalone with no accounting package (HR-143).
 */
class StandaloneUsageTest extends TestCase
{
    public function test_core_hr_flows_work_without_accounting_package(): void
    {
        $this->assertFalse(
            class_exists('Karnoweb\\Accounting\\AccountingServiceProvider'),
            'Accounting package must not be present in this test environment.'
        );

        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Hr::salaries()->assign($employee, [
            'base_salary' => 40_000_000,
            'effective_date' => '2026-01-01',
        ]);

        LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'year' => 2026,
            'type' => 'annual',
            'entitled_days' => 26,
            'used_days' => 0,
            'carried_days' => 0,
            'adjustment_days' => 0,
            'remaining_days' => 26,
        ]);

        $leave = Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'days' => 2,
        ], ['use_calculated_days' => false]);

        $this->assertSame($employee->id, $leave->employee_id);

        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Standalone leave',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 1,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Manager',
            'approver_type' => ApproverType::User,
            'approver_id' => 7001,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee, ['leave_request_id' => $leave->id]);
        Hr::documents()->submit($document, actorId: 1);

        $approval = $document->fresh()->approvals()->first();
        Hr::documents()->approve($approval, null, actorId: 7001);

        $period = Hr::payroll()->openPeriod(1, 2026, 5);
        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh(), approvedBy: 1);

        $this->assertSame(
            1,
            PayrollRecord::query()->where('payroll_period_id', $period->id)->count()
        );
    }
}
