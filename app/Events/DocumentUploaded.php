<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a document is successfully ingested. Phase 3 listens with a
 * queued listener to enqueue processing (spec §30).
 */
final class DocumentUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly User $uploader,
    ) {}
}
