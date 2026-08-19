<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\ContractType;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Exceptions\InvalidOrganizationStructureException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\Contract;
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\EmployeePosition;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Position;
use Karnoweb\Hr\Services\ContractService;
use Karnoweb\Hr\Tests\TestCase;

class OrganizationContractTest extends TestCase
{
    public function test_branch_scoped_department_codes_allow_same_code_in_different_branches(): void
    {
        Department::query()->create(['branch_id' => 1, 'code' => 'IT', 'name' => 'IT A']);
        Department::query()->create(['branch_id' => 2, 'code' => 'IT', 'name' => 'IT B']);

        $this->assertSame(2, Department::query()->where('code', 'IT')->count());
    }

    public function test_department_parent_cycle_is_rejected(): void
    {
        $root = Department::query()->create(['code' => 'ROOT', 'name' => 'Root']);
        $child = Department::query()->create(['code' => 'CHILD', 'name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = Department::query()->create(['code' => 'GC', 'name' => 'Grandchild', 'parent_id' => $child->id]);

        $this->expectException(InvalidOrganizationStructureException::class);
        $root->update(['parent_id' => $grandchild->id]);
    }

    public function test_department_with_children_cannot_be_soft_deleted(): void
    {
        $parent = Department::query()->create(['code' => 'PARENT', 'name' => 'Parent']);
        Department::query()->create(['code' => 'CHILD', 'name' => 'Child', 'parent_id' => $parent->id]);

        $this->expectException(InvalidOrganizationStructureException::class);
        $parent->delete();
    }

    public function test_update_path_rolls_back_entire_subtree_on_failure(): void
    {
        $root = Department::query()->create(['code' => 'R', 'name' => 'Root']);
        $child = Department::query()->create(['code' => 'C1', 'name' => 'Child', 'parent_id' => $root->id]);
        $sibling = Department::query()->create(['code' => 'C2', 'name' => 'Sibling', 'parent_id' => $root->id]);

        $originalChildPath = $child->fresh()->path;
        $originalSiblingPath = $sibling->fresh()->path;

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if (
                $shouldFail
                && str_contains($query->sql, 'departments')
                && str_contains(strtolower($query->sql), 'update')
                && str_contains($query->sql, 'path')
            ) {
                $shouldFail = false;
                throw new \RuntimeException('forced path update failure');
            }
        });

        try {
            $child->update(['parent_id' => null]);
            $this->fail('Expected updatePath failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced path update failure', $e->getMessage());
        }

        $this->assertSame($originalChildPath, $child->fresh()->path);
        $this->assertSame($originalSiblingPath, $sibling->fresh()->path);
    }

    public function test_contract_hire_and_renew_maintain_single_active_contract(): void
    {
        Carbon::setTestNow('2026-01-01');
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $first = Hr::contracts()->hire($employee, [
            'contract_number' => 'C-001',
            'type' => ContractType::Permanent,
            'start_date' => '2026-01-01',
        ]);

        $this->assertSame(ContractStatus::Active, $first->status);
        $this->assertSame($employee->id, $first->active_key);

        $second = Hr::contracts()->renew($employee->fresh(), [
            'contract_number' => 'C-002',
            'type' => ContractType::Permanent,
            'start_date' => '2027-01-01',
        ]);

        $first->refresh();
        $this->assertSame(ContractStatus::Ended, $first->status);
        $this->assertNull($first->active_key);
        $this->assertSame(ContractStatus::Active, $second->status);
        $this->assertSame(1, Contract::query()->where('employee_id', $employee->id)->where('status', ContractStatus::Active)->count());
    }

    public function test_second_hire_throws_duplicate_active_record(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        Hr::contracts()->hire($employee, ['contract_number' => 'C-100']);

        $this->expectException(DuplicateActiveRecordException::class);
        Hr::contracts()->hire($employee->fresh(), ['contract_number' => 'C-101']);
    }

    public function test_contract_number_must_be_unique(): void
    {
        $employeeA = Hr::employees()->createForUser($this->makeUser());
        $employeeB = Hr::employees()->createForUser($this->makeUser());

        Hr::contracts()->hire($employeeA, ['contract_number' => 'SHARED-001']);

        $this->expectException(DuplicateActiveRecordException::class);
        Hr::contracts()->hire($employeeB, ['contract_number' => 'SHARED-001']);
    }

    public function test_contract_extend_and_terminate(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());
        $contract = Hr::contracts()->hire($employee, [
            'contract_number' => 'C-EXT',
            'start_date' => '2026-01-01',
        ]);

        $extended = Hr::contracts()->extend($employee->fresh(), '2028-12-31');
        $this->assertTrue($extended->end_date->equalTo(Carbon::parse('2028-12-31')));

        $terminated = Hr::contracts()->terminate($employee->fresh(), '2027-06-01');
        $this->assertSame(ContractStatus::Terminated, $terminated->status);
        $this->assertNull($terminated->active_key);
    }

    public function test_active_key_unique_constraint_at_database_level(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        Contract::query()->create([
            'employee_id' => $employee->id,
            'contract_number' => 'DB-1',
            'type' => ContractType::Permanent,
            'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
            'active_key' => $employee->id,
        ]);

        $this->expectException(QueryException::class);

        Contract::query()->create([
            'employee_id' => $employee->id,
            'contract_number' => 'DB-2',
            'type' => ContractType::Permanent,
            'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
            'active_key' => $employee->id,
        ]);
    }

    public function test_assign_position_closes_previous_current_primary(): void
    {
        Carbon::setTestNow('2026-03-01');
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $deptA = Department::query()->create(['branch_id' => 1, 'code' => 'D-A', 'name' => 'Dept A']);
        $deptB = Department::query()->create(['branch_id' => 1, 'code' => 'D-B', 'name' => 'Dept B']);
        $pos = Position::query()->create(['branch_id' => 1, 'code' => 'DEV', 'name' => 'Developer']);

        $first = Hr::employees()->assignPosition($employee, $deptA->id, $pos->id, '2026-01-01');
        $second = Hr::employees()->assignPosition($employee->fresh(), $deptB->id, $pos->id, '2026-03-01');

        $first->refresh();
        $this->assertNotNull($first->end_date);
        $this->assertNull($first->current_key);
        $this->assertNull($second->end_date);
        $this->assertSame($employee->id, $second->current_key);

        $this->assertSame(
            1,
            EmployeePosition::query()
                ->where('employee_id', $employee->id)
                ->where('is_primary', true)
                ->whereNull('end_date')
                ->count()
        );
    }

    public function test_assign_position_rejects_cross_branch_by_default(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $dept = Department::query()->create(['branch_id' => 2, 'code' => 'X', 'name' => 'Other']);
        $pos = Position::query()->create(['branch_id' => 2, 'code' => 'P', 'name' => 'Pos']);

        $this->expectException(InvalidArgumentException::class);
        Hr::employees()->assignPosition($employee, $dept->id, $pos->id);
    }

    public function test_assign_position_allows_cross_branch_with_override(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $dept = Department::query()->create(['branch_id' => 2, 'code' => 'X', 'name' => 'Other']);
        $pos = Position::query()->create(['branch_id' => 2, 'code' => 'P', 'name' => 'Pos']);

        $assignment = Hr::employees()->assignPosition($employee, $dept->id, $pos->id, null, [
            'allow_cross_branch' => true,
        ]);

        $this->assertSame($dept->id, $assignment->department_id);
    }

    public function test_assign_position_rejects_invalid_hr_document_id(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $dept = Department::query()->create(['branch_id' => 1, 'code' => 'D', 'name' => 'Dept']);
        $pos = Position::query()->create(['branch_id' => 1, 'code' => 'P', 'name' => 'Pos']);

        $this->expectException(InvalidArgumentException::class);
        Hr::employees()->assignPosition($employee, $dept->id, $pos->id, null, [
            'hr_document_id' => 99999,
        ]);
    }

    public function test_assign_position_accepts_valid_hr_document_id(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $dept = Department::query()->create(['branch_id' => 1, 'code' => 'D', 'name' => 'Dept']);
        $pos = Position::query()->create(['branch_id' => 1, 'code' => 'P', 'name' => 'Pos']);

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::PositionChange,
            'effective_date' => now()->toDateString(),
            'status' => DocumentStatus::Draft,
        ]);

        $assignment = Hr::employees()->assignPosition($employee, $dept->id, $pos->id, null, [
            'hr_document_id' => $document->id,
        ]);

        $this->assertSame($document->id, $assignment->hr_document_id);
    }

    public function test_current_key_unique_constraint_at_database_level(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());
        $dept = Department::query()->create(['code' => 'D', 'name' => 'Dept']);
        $pos = Position::query()->create(['code' => 'P', 'name' => 'Pos']);

        EmployeePosition::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'is_primary' => true,
            'effective_date' => now()->toDateString(),
            'current_key' => $employee->id,
        ]);

        $this->expectException(QueryException::class);

        EmployeePosition::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'is_primary' => true,
            'effective_date' => now()->toDateString(),
            'current_key' => $employee->id,
        ]);
    }

    public function test_contract_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(ContractService::class),
            Hr::contracts()
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
