<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentHistory extends BaseModel
{
    protected $table = 'document_histories';

    protected $fillable = [
        'hr_document_id', 'user_id', 'action', 'from_status', 'to_status', 'changes', 'comment', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }
}
