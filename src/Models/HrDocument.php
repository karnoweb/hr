<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\DocumentLockedException;
use Karnoweb\Hr\Support\SequenceGenerator;

/**
 * @property DocumentType $type
 * @property DocumentStatus $status
 * @property Carbon $effective_date
 * @property Carbon|null $expiry_date
 * @property string $document_number
 * @property int|null $branch_id
 * @property int $employee_id
 * @property string|null $notes
 * @property array|null $metadata
 * @property array|null $data
 * @property int|string|null $approved_by
 * @property int|string|null $created_by
 * @property Carbon|null $approved_at
 * @property-read Employee|null $employee
 */
class HrDocument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'branch_id', 'employee_id', 'type', 'document_number', 'effective_date', 'expiry_date',
        'status', 'data', 'notes', 'created_by', 'approved_by', 'approved_at', 'locked_at', 'metadata',
    ];

    protected $casts = [
        'type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'data' => 'array',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class, 'hr_document_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'hr_document_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentHistory::class, 'hr_document_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Draft);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Pending);
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function ensureEditable(): void
    {
        if (! $this->canEdit()) {
            throw new DocumentLockedException(
                "Document #{$this->document_number} is not editable in {$this->status->value} status."
            );
        }
    }

    public function getData(string $key, $default = null)
    {
        return data_get($this->data ?? [], $key, $default);
    }

    public function requiresApproval(): bool
    {
        return $this->type->requiresApproval();
    }

    protected static function booted(): void
    {
        static::creating(function (HrDocument $document) {
            if (empty($document->document_number)) {
                $document->document_number = static::generateDocumentNumber($document->type);
            }
        });
    }

    public static function generateDocumentNumber(DocumentType $type, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = strtoupper(substr($type->value, 0, 3));
        $sequence = app(SequenceGenerator::class)->nextValue("document:{$type->value}:{$year}");

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
