<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends BaseModel
{
    protected $table = 'document_attachments';

    protected $fillable = [
        'hr_document_id', 'file_path', 'file_name', 'file_type', 'file_size', 'title', 'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }
}
