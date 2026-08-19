<?php

namespace Karnoweb\Hr\Tests\Unit;

use InvalidArgumentException;
use Karnoweb\Hr\Enums\CalculationType;
use Karnoweb\Hr\Enums\SalaryItemType;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Models\SalaryStructure;
use Karnoweb\Hr\Models\SalaryStructureItem;
use Karnoweb\Hr\Services\SalaryCalculator;
use Karnoweb\Hr\Tests\TestCase;

class SalaryCalculatorTest extends TestCase
{
    public function test_fixed_precedence_employee_override_structure_default_item_default(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $item = SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'HOUSING',
            'name' => 'Housing',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Fixed,
            'default_value' => 100,
            'is_taxable' => true,
            'is_insurable' => false,
        ]);

        $structure = SalaryStructure::query()->create([
            'branch_id' => 1,
            'code' => 'STD',
            'name' => 'Standard',
        ]);

        SalaryStructureItem::query()->create([
            'salary_structure_id' => $structure->id,
            'salary_item_id' => $item->id,
            'value' => 200,
        ]);

        $salary = Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'salary_structure_id' => $structure->id,
        ]);

        $structureResult = app(SalaryCalculator::class)->calculate($salary->fresh(['items.salaryItem', 'salaryStructure.items.salaryItem']));
        $this->assertSame(200.0, $structureResult['items'][0]['amount']);

        Hr::salaries()->changeSalary($employee, [
            'base_salary' => 55_000_000,
            'salary_structure_id' => $structure->id,
            'effective_date' => '2026-04-01',
            'items' => [
                ['code' => 'HOUSING', 'value' => 300],
            ],
        ]);

        $current = Hr::salaries()->currentSalary($employee->fresh());
        $overrideResult = app(SalaryCalculator::class)->calculate($current);
        $this->assertSame(300.0, $overrideResult['items'][0]['amount']);
    }

    public function test_percentage_uses_referenced_item_value(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $baseItem = SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'BASE_ITEM',
            'name' => 'Base Item',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Fixed,
            'default_value' => 1_000_000,
        ]);

        SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'BONUS_PCT',
            'name' => 'Bonus',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Percentage,
            'default_value' => 10,
            'percentage_of' => 'BASE_ITEM',
        ]);

        $structure = SalaryStructure::query()->create([
            'branch_id' => 1,
            'code' => 'PCT',
            'name' => 'Pct',
        ]);

        foreach (SalaryItem::query()->where('branch_id', 1)->pluck('id', 'code') as $code => $id) {
            SalaryStructureItem::query()->create([
                'salary_structure_id' => $structure->id,
                'salary_item_id' => $id,
                'value' => $code === 'BASE_ITEM' ? 2_000_000 : 0,
            ]);
        }

        $salary = Hr::salaries()->assign($employee, [
            'base_salary' => 40_000_000,
            'salary_structure_id' => $structure->id,
        ]);

        $result = app(SalaryCalculator::class)->calculate($salary->fresh(['items.salaryItem', 'salaryStructure.items.salaryItem']));

        $amounts = collect($result['items'])->pluck('amount', 'code');
        $this->assertSame(2_000_000.0, $amounts['BASE_ITEM']);
        $this->assertSame(200_000.0, $amounts['BONUS_PCT']);
    }

    public function test_formula_evaluation(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'FORMULA_ITEM',
            'name' => 'Formula',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Formula,
            'formula' => 'base_salary * 0.01',
        ]);

        $structure = SalaryStructure::query()->create([
            'branch_id' => 1,
            'code' => 'FORM',
            'name' => 'Formula Structure',
        ]);

        SalaryStructureItem::query()->create([
            'salary_structure_id' => $structure->id,
            'salary_item_id' => SalaryItem::query()->where('code', 'FORMULA_ITEM')->value('id'),
            'value' => 0,
        ]);

        $salary = Hr::salaries()->assign($employee, [
            'base_salary' => 50_000_000,
            'salary_structure_id' => $structure->id,
        ]);

        $result = app(SalaryCalculator::class)->calculate($salary->fresh(['items.salaryItem', 'salaryStructure.items.salaryItem']));

        $this->assertSame(500_000.0, $result['items'][0]['amount']);
    }

    public function test_output_includes_taxable_and_insurable_totals(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'TAXABLE',
            'name' => 'Taxable',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Fixed,
            'default_value' => 1_000_000,
            'is_taxable' => true,
            'is_insurable' => true,
        ]);

        SalaryItem::query()->create([
            'branch_id' => 1,
            'code' => 'NONTAX',
            'name' => 'Non Taxable',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Fixed,
            'default_value' => 500_000,
            'is_taxable' => false,
            'is_insurable' => false,
        ]);

        $structure = SalaryStructure::query()->create([
            'branch_id' => 1,
            'code' => 'FLAGS',
            'name' => 'Flags',
        ]);

        foreach (SalaryItem::query()->where('branch_id', 1)->get() as $item) {
            SalaryStructureItem::query()->create([
                'salary_structure_id' => $structure->id,
                'salary_item_id' => $item->id,
                'value' => $item->default_value,
            ]);
        }

        $salary = Hr::salaries()->assign($employee, [
            'base_salary' => 10_000_000,
            'salary_structure_id' => $structure->id,
        ]);

        $result = app(SalaryCalculator::class)->calculate($salary->fresh(['items.salaryItem', 'salaryStructure.items.salaryItem']));

        $this->assertSame(1_000_000.0, $result['totals']['taxable_amount']);
        $this->assertSame(1_000_000.0, $result['totals']['insurable_amount']);
        $this->assertSame(11_500_000.0, $result['totals']['gross_earnings']);
    }

    public function test_circular_percentage_dependency_is_rejected_on_update(): void
    {
        SalaryItem::query()->create([
            'code' => 'A',
            'name' => 'A',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Fixed,
            'default_value' => 100,
        ]);

        SalaryItem::query()->create([
            'code' => 'B',
            'name' => 'B',
            'type' => SalaryItemType::Earning,
            'calculation_type' => CalculationType::Percentage,
            'default_value' => 10,
            'percentage_of' => 'A',
        ]);

        $itemA = SalaryItem::query()->where('code', 'A')->firstOrFail();
        $itemA->calculation_type = CalculationType::Percentage;
        $itemA->default_value = 10;
        $itemA->percentage_of = 'B';

        $this->expectException(InvalidArgumentException::class);
        $itemA->save();
    }
}
