<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
