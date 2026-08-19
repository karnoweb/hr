<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\LoanPaymentStatus;

/**
 * @property int $loan_id
 * @property int|null $payroll_record_id
 * @property int $installment_number
 * @property float $amount
 * @property Carbon $due_date
 * @property Carbon|null $paid_date
 * @property LoanPaymentStatus $status
 */
class LoanPayment extends BaseModel
{
    protected $table = 'loan_payments';

    protected $fillable = [
        'loan_id', 'payroll_record_id', 'installment_number', 'amount', 'due_date', 'paid_date', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'status' => LoanPaymentStatus::class,
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LoanPaymentStatus::Pending);
    }
}
