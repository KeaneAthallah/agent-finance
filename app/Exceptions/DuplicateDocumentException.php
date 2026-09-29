<?php

namespace App\Exceptions;

use App\Models\Document;

/**
 * Thrown when an upload matches an existing file hash (spec §7, §24).
 * Nothing is auto-deleted; the caller surfaces the existing document.
 */
final class DuplicateDocumentException extends \RuntimeException
{
    public function __construct(
        public readonly Document $existing,
    ) {
        parent::__construct('A document with the same content already exists.');
    }
}
