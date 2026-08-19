<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\DocumentLockedException;

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
                $document->document_number = static::generateDocumentNumber($document);
            }
        });
    }

    public static function generateDocumentNumber(HrDocument $document): string
    {
        return DB::transaction(function () use ($document) {
            $prefix = strtoupper(substr($document->type->value, 0, 3));
            $year = now()->format('Y');
            $sequence = static::where('type', $document->type)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count() + 1;

            return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
        });
    }
}
