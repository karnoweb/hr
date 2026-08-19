<?php

namespace Karnoweb\Hr\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\CalculationType;
use Karnoweb\Hr\Enums\SalaryItemType;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\EmployeeSalaryItem;
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Models\SalaryStructureItem;
use Karnoweb\Hr\Support\SalaryExpressionEvaluator;

/**
 * Resolves salary line items for an employee assignment (HR-071–HR-073, HR-076).
 */
class SalaryCalculator
{
    public function __construct(
        protected SalaryExpressionEvaluator $expressions,
    ) {}

    /**
     * @return array{
     *     base_salary: float,
     *     items: list<array{
     *         code: string,
     *         name: string,
     *         type: string,
     *         calculation_type: string,
     *         amount: float,
     *         is_taxable: bool,
     *         is_insurable: bool
     *     }>,
     *     totals: array{
     *         earnings: float,
     *         deductions: float,
     *         taxable_amount: float,
     *         insurable_amount: float,
     *         gross_earnings: float,
     *         net_before_statutory: float
     *     }
     * }
     */
    public function calculate(EmployeeSalary $employeeSalary): array
    {
        $employeeSalary->loadMissing([
            'items.salaryItem',
            'salaryStructure.items.salaryItem',
        ]);

        $baseSalary = (float) $employeeSalary->base_salary;
        $computed = ['base_salary' => $baseSalary];
        $itemsByCode = $this->resolveCalculationItems($employeeSalary);
        $orderedCodes = $this->resolveComputationOrder($itemsByCode);

        $resolvedItems = [];

        foreach ($orderedCodes as $code) {
            /** @var SalaryItem $item */
            $item = $itemsByCode[$code];
            $structureItem = $this->findStructureItem($employeeSalary, $item);
            $employeeItem = $this->findEmployeeItem($employeeSalary, $item);

            $amount = match ($item->calculation_type) {
                CalculationType::Fixed => $this->resolveFixedAmount($item, $structureItem, $employeeItem),
                CalculationType::Percentage => $this->resolvePercentageAmount($item, $computed),
                CalculationType::Formula => $this->resolveFormulaAmount($item, $computed),
            };

            $computed[$code] = $amount;

            $resolvedItems[] = [
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type->value,
                'calculation_type' => $item->calculation_type->value,
                'amount' => $amount,
                'is_taxable' => (bool) $item->is_taxable,
                'is_insurable' => (bool) $item->is_insurable,
            ];
        }

        return [
            'base_salary' => $baseSalary,
            'items' => $resolvedItems,
            'totals' => $this->summarizeTotals($resolvedItems, $baseSalary),
        ];
    }

    /**
     * @return array<string, SalaryItem>
     */
    protected function resolveCalculationItems(EmployeeSalary $employeeSalary): array
    {
        /** @var \Illuminate\Support\Collection<string, SalaryItem> $items */
        $items = collect();

        if ($employeeSalary->salaryStructure !== null) {
            foreach ($employeeSalary->salaryStructure->items as $structureItem) {
                if ($structureItem->salaryItem !== null) {
                    $items->put($structureItem->salaryItem->code, $structureItem->salaryItem);
                }
            }
        }

        foreach ($employeeSalary->items as $employeeItem) {
            if ($employeeItem->salaryItem !== null) {
                $items->put($employeeItem->salaryItem->code, $employeeItem->salaryItem);
            }
        }

        $expanded = $items->all();

        foreach ($items as $item) {
            foreach ($this->dependencyCodes($item) as $dependencyCode) {
                if ($dependencyCode === 'base_salary' || isset($expanded[$dependencyCode])) {
                    continue;
                }

                $dependency = SalaryItem::query()->where('code', $dependencyCode)->first();

                if ($dependency === null) {
                    throw new InvalidArgumentException("Salary item [{$item->code}] depends on unknown code [{$dependencyCode}].");
                }

                $expanded[$dependencyCode] = $dependency;
            }
        }

        return $expanded;
    }

    /**
     * @param  array<string, SalaryItem>  $itemsByCode
     * @return list<string>
     */
    protected function resolveComputationOrder(array $itemsByCode): array
    {
        $visited = [];
        $visiting = [];
        $order = [];

        $visit = function (string $code) use (&$visit, &$visited, &$visiting, $itemsByCode, &$order): void {
            if (isset($visited[$code])) {
                return;
            }

            if (isset($visiting[$code])) {
                throw new InvalidArgumentException('Circular salary item dependency detected.');
            }

            $visiting[$code] = true;

            if (isset($itemsByCode[$code])) {
                foreach ($this->dependencyCodes($itemsByCode[$code]) as $dependency) {
                    if ($dependency === 'base_salary') {
                        continue;
                    }

                    if (! isset($itemsByCode[$dependency])) {
                        throw new InvalidArgumentException("Missing dependency [{$dependency}] for salary item [{$code}].");
                    }

                    $visit($dependency);
                }
            }

            unset($visiting[$code]);
            $visited[$code] = true;
            $order[] = $code;
        };

        foreach (array_keys($itemsByCode) as $code) {
            $visit($code);
        }

        return $order;
    }

    /**
     * @return list<string>
     */
    protected function dependencyCodes(SalaryItem $item): array
    {
        return match ($item->calculation_type) {
            CalculationType::Percentage => [(string) $item->percentage_of],
            CalculationType::Formula => $this->expressions->extractIdentifiers((string) $item->formula),
            CalculationType::Fixed => [],
        };
    }

    protected function resolveFixedAmount(
        SalaryItem $item,
        ?SalaryStructureItem $structureItem,
        ?EmployeeSalaryItem $employeeItem
    ): float {
        if ($employeeItem !== null) {
            return round((float) $employeeItem->value, 2);
        }

        if ($structureItem !== null) {
            return round((float) $structureItem->value, 2);
        }

        return round((float) ($item->default_value ?? 0), 2);
    }

    /**
     * @param  array<string, float>  $computed
     */
    protected function resolvePercentageAmount(SalaryItem $item, array $computed): float
    {
        $referenceCode = (string) $item->percentage_of;

        if (! array_key_exists($referenceCode, $computed)) {
            throw new InvalidArgumentException("Referenced salary item [{$referenceCode}] is not computed yet.");
        }

        $percent = (float) ($item->default_value ?? 0);

        return round($computed[$referenceCode] * $percent / 100, 2);
    }

    /**
     * @param  array<string, float>  $computed
     */
    protected function resolveFormulaAmount(SalaryItem $item, array $computed): float
    {
        return $this->expressions->evaluate((string) $item->formula, $computed);
    }

    protected function findStructureItem(EmployeeSalary $employeeSalary, SalaryItem $item): ?SalaryStructureItem
    {
        if ($employeeSalary->salaryStructure === null) {
            return null;
        }

        return $employeeSalary->salaryStructure->items
            ->first(fn (SalaryStructureItem $row) => $row->salary_item_id === $item->id);
    }

    protected function findEmployeeItem(EmployeeSalary $employeeSalary, SalaryItem $item): ?EmployeeSalaryItem
    {
        return $employeeSalary->items
            ->first(fn (EmployeeSalaryItem $row) => $row->salary_item_id === $item->id);
    }

    /**
     * @param  list<array{code: string, name: string, type: string, calculation_type: string, amount: float, is_taxable: bool, is_insurable: bool}>  $items
     * @return array{earnings: float, deductions: float, taxable_amount: float, insurable_amount: float, gross_earnings: float, net_before_statutory: float}
     */
    protected function summarizeTotals(array $items, float $baseSalary): array
    {
        $earnings = 0.0;
        $deductions = 0.0;
        $taxable = 0.0;
        $insurable = 0.0;

        foreach ($items as $item) {
            if ($item['type'] === SalaryItemType::Earning->value) {
                $earnings += $item['amount'];

                if ($item['is_taxable']) {
                    $taxable += $item['amount'];
                }

                if ($item['is_insurable']) {
                    $insurable += $item['amount'];
                }
            } else {
                $deductions += $item['amount'];
            }
        }

        return [
            'earnings' => round($earnings, 2),
            'deductions' => round($deductions, 2),
            'taxable_amount' => round($taxable, 2),
            'insurable_amount' => round($insurable, 2),
            'gross_earnings' => round($baseSalary + $earnings, 2),
            'net_before_statutory' => round($baseSalary + $earnings - $deductions, 2),
        ];
    }
}
