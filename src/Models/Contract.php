<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\ContractType;

class Contract extends BaseModel
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $fillable = [
        'employee_id', 'contract_number', 'type', 'start_date', 'end_date', 'status', 'terms', 'metadata',
    ];

    protected $casts = [
        'type' => ContractType::class,
        'status' => ContractStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContractStatus::Active);
    }
}
