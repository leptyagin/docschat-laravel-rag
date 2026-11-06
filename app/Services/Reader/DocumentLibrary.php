<?php

declare(strict_types=1);

namespace App\Services\Reader;

use App\Models\Bot;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;

final class DocumentLibrary
{
    /**
     * @return Collection<int, Document>
     */
    public function for(Bot $bot): Collection
    {
        if (! $bot->documents_public) {
            return new Collection;
        }

        return $bot->documents()
            ->readable()
            ->orderBy('title')
            ->get(['id', 'bot_id', 'title', 'source_type', 'chunk_count', 'updated_at']);
    }
}
