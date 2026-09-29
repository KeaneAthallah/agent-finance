<?php

namespace App\Support;

/**
 * Immutable result set from a SIHANDAL API list endpoint.
 */
final class SihandalCollection
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly ?int $total = null,
        public readonly ?int $page = null,
        public readonly array $meta = [],
    ) {}
}
