<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\LoanStatus;

/**
 * @property int $employee_id
 * @property int|null $hr_document_id
 * @property string $loan_number
 * @property float $amount
 * @property int $installments
 * @property float $installment_amount
 * @property float $remaining_amount
 * @property int $remaining_installments
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property LoanStatus $status
 * @property string|null $notes
 * @property-read Collection<int, LoanPayment> $payments
 */
class Loan extends BaseModel
{
    use SoftDeletes;

    protected $table = 'loans';

    protected $fillable = [
        'employee_id', 'hr_document_id', 'loan_number', 'type', 'amount', 'installments', 'installment_amount',
        'remaining_amount', 'remaining_installments', 'start_date', 'end_date', 'status', 'purpose', 'notes',
    ];

    protected $casts = [
        'status' => LoanStatus::class,
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active);
    }
}
