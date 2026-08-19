<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\ContractType;

/**
 * @property int $employee_id
 * @property string|null $contract_number
 * @property ContractType $type
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property ContractStatus $status
 * @property int|null $active_key Set to employee_id while status is Active; null otherwise.
 */
class Contract extends BaseModel
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $fillable = [
        'employee_id', 'contract_number', 'type', 'start_date', 'end_date', 'status',
        'active_key', 'terms', 'metadata',
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
