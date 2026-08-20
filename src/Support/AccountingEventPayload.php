<?php

namespace Karnoweb\Hr\Support;

use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Models\PayrollRecord;

/**
 * Builds accounting-integration event payloads from payroll records (HR-139 / HR-146 / HR-147).
 */
final class AccountingEventPayload
{
    /**
     * @return array{
     *     employees: list<array<string, mixed>>,
     *     period_totals: array<string, float>
     * }
     */
    public static function fromPayrollPeriod(PayrollPeriod $period): array
    {
        $period->loadMissing('records');

        $records = $period->records;

        $employees = [];
        $totals = [
            'gross_salary' => 0.0,
            'net_salary' => 0.0,
            'payable' => 0.0,
            'insurance_employee' => 0.0,
            'insurance_employer' => 0.0,
            'insurance_unemployment' => 0.0,
            'tax' => 0.0,
            'loan_deduction' => 0.0,
        ];

        foreach ($records as $record) {
            $line = self::employeeLine($record);
            $employees[] = $line;

            $totals['gross_salary'] += $line['compensation']['gross_salary'];
            $totals['net_salary'] += $line['compensation']['net_salary'];
            $totals['payable'] += $line['compensation']['payable'];
            $totals['insurance_employee'] += $line['employee_liabilities']['insurance_employee'];
            $totals['insurance_employer'] += $line['employer_liabilities']['insurance_employer'];
            $totals['insurance_unemployment'] += $line['employer_liabilities']['insurance_unemployment'];
            $totals['tax'] += $line['employee_liabilities']['tax'];
            $totals['loan_deduction'] += $line['employee_liabilities']['loan_deduction'];
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return [
            'employees' => $employees,
            'period_totals' => $totals,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function employeeLine(PayrollRecord $record): array
    {
        return [
            'payroll_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'compensation' => [
                'gross_salary' => round((float) $record->gross_salary, 2),
                'net_salary' => round((float) $record->net_salary, 2),
                'payable' => round((float) $record->payable, 2),
            ],
            'employee_liabilities' => [
                'insurance_employee' => round((float) $record->insurance_employee, 2),
                'tax' => round((float) $record->tax, 2),
                'loan_deduction' => round((float) $record->loan_deduction, 2),
            ],
            'employer_liabilities' => [
                'insurance_employer' => round((float) $record->insurance_employer, 2),
                'insurance_unemployment' => round((float) $record->insurance_unemployment, 2),
            ],
            'loan_deductions' => self::loanDeductionLines($record),
        ];
    }

    /**
     * @return list<array{loan_id: int, loan_payment_id: int, amount: float, installment_number: int|null}>
     */
    public static function loanDeductionLines(PayrollRecord $record): array
    {
        $entries = $record->calculation_log['loan_payments'] ?? [];
        $lines = [];

        foreach ($entries as $entry) {
            $paymentId = (int) ($entry['id'] ?? 0);

            if ($paymentId <= 0) {
                continue;
            }

            $payment = LoanPayment::query()->find($paymentId);

            if ($payment === null) {
                continue;
            }

            $lines[] = [
                'loan_id' => (int) $payment->loan_id,
                'loan_payment_id' => $payment->id,
                'amount' => round((float) ($entry['amount'] ?? $payment->amount), 2),
                'installment_number' => isset($entry['installment_number'])
                    ? (int) $entry['installment_number']
                    : (int) $payment->installment_number,
            ];
        }

        return $lines;
    }
}
