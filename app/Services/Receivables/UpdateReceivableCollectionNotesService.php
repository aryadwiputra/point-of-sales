<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Models\Receivable;

class UpdateReceivableCollectionNotesService
{
    public function execute(Receivable $receivable, ?string $collectionNotes): void
    {
        $receivable->update([
            'collection_notes' => $collectionNotes,
        ]);
    }
}
