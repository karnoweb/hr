<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\CalculationType;
use Karnoweb\Hr\Models\SalaryItem;

/**
 * Validates SalaryItem percentage_of and formula fields (HR-072 / HR-073).
 */
class SalaryItemValidator
{
    public function __construct(
        protected SalaryExpressionEvaluator $expressions,
    ) {}

    public function validate(SalaryItem $item): void
    {
        match ($item->calculation_type) {
            CalculationType::Percentage => $this->validatePercentageItem($item),
            CalculationType::Formula => $this->validateFormulaItem($item),
            CalculationType::Fixed => $this->validateFixedItem($item),
        };
    }

    protected function validateFixedItem(SalaryItem $item): void
    {
        if ($item->percentage_of !== null || $item->formula !== null) {
            throw new InvalidArgumentException('Fixed salary items cannot define percentage_of or formula.');
        }
    }

    protected function validatePercentageItem(SalaryItem $item): void
    {
        if ($item->formula !== null) {
            throw new InvalidArgumentException('Percentage salary items cannot define a formula.');
        }

        if ($item->percentage_of === null || $item->percentage_of === '') {
            throw new InvalidArgumentException('Percentage salary items require percentage_of referencing a SalaryItem code.');
        }

        if ($item->percentage_of === $item->code) {
            throw new InvalidArgumentException('percentage_of cannot reference the same salary item.');
        }

        $this->assertReferencedCodeExists($item->percentage_of, $item->branch_id);

        if ($this->percentageChainHasCycle($item->code, $item->percentage_of, $item->branch_id, $item->exists ? $item->id : null)) {
            throw new InvalidArgumentException('Circular percentage_of reference detected.');
        }
    }

    protected function validateFormulaItem(SalaryItem $item): void
    {
        if ($item->percentage_of !== null) {
            throw new InvalidArgumentException('Formula salary items cannot define percentage_of.');
        }

        if ($item->formula === null || trim($item->formula) === '') {
            throw new InvalidArgumentException('Formula salary items require a formula expression.');
        }

        $allowed = $this->allowedFormulaIdentifiers($item->branch_id);
        $this->expressions->validate($item->formula, $allowed);
    }

    protected function assertReferencedCodeExists(string $code, ?int $branchId): void
    {
        if (! $this->codeQuery($code, $branchId)->exists()) {
            throw new InvalidArgumentException("percentage_of references unknown salary item code [{$code}].");
        }
    }

    protected function percentageChainHasCycle(
        string $itemCode,
        string $percentageOf,
        ?int $branchId,
        ?int $ignoreId
    ): bool {
        $visited = [$itemCode => true];
        $current = $percentageOf;

        while ($current !== '') {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;

            $referenced = $this->codeQuery($current, $branchId)
                ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->first();

            if (! $referenced instanceof SalaryItem || $referenced->calculation_type !== CalculationType::Percentage) {
                return false;
            }

            $current = (string) ($referenced->percentage_of ?? '');
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function allowedFormulaIdentifiers(?int $branchId): array
    {
        $codes = SalaryItem::query()
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->pluck('code')
            ->all();

        $codes[] = 'base_salary';

        return array_values(array_unique($codes));
    }

    protected function codeQuery(string $code, ?int $branchId): Builder
    {
        return SalaryItem::query()
            ->where('code', $code)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));
    }
}
