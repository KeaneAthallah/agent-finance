<?php

namespace App\Console\Commands;

use App\Events\DocumentUploaded;
use App\Models\Document;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class DiagEvents extends Command
{
    protected $signature = 'diag:events';

    public function handle(): int
    {
        $listeners = Event::getListeners(DocumentUploaded::class);
        $this->info('registered listeners: '.count($listeners));

        $document = Document::query()->first() ?? Document::factory()->create();
        $user = User::query()->first();

        DocumentUploaded::dispatch($document, $user);

        $this->info('pipeline run count: '.$document->processingRuns()->count());
        $this->info('versions: '.$document->processingRuns()->pluck('version')->implode(','));

        return self::SUCCESS;
    }
}
