<?php

namespace Karnoweb\Hr\Support;

use Karnoweb\Hr\Models\HrDocument;

/**
 * Validates optional hr_document_id references at write sites.
 *
 * Tables that reference hr_documents before the documents migration order
 * cannot use DB foreign keys; this helper enforces referential integrity in
 * application code instead (HR-033).
 */
final class HrDocumentReference
{
    public static function assertValid(?int $hrDocumentId): void
    {
        if ($hrDocumentId === null) {
            return;
        }

        if (! HrDocument::query()->whereKey($hrDocumentId)->exists()) {
            throw new \InvalidArgumentException(
                "hr_document_id [{$hrDocumentId}] does not reference an existing HR document."
            );
        }
    }
}
