<?php

namespace App\Services\Sihandal;

use App\Support\SihandalCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Real HTTP implementation of the SIHANDAL V2 API contract. Master data
 * (OPD, program, kegiatan, rekening) is cached briefly to protect SIHANDAL
 * (spec §16, §50).
 */
final class SihandalClient implements SihandalApiInterface
{
    /** @param array<string, mixed> $filters */
    public function opds(array $filters = []): SihandalCollection
    {
        return $this->get('opds', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function programs(array $filters = []): SihandalCollection
    {
        return $this->get('programs', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function kegiatan(array $filters = []): SihandalCollection
    {
        return $this->get('kegiatan', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function subKegiatan(array $filters = []): SihandalCollection
    {
        return $this->get('sub-kegiatan', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function belanja(array $filters = []): SihandalCollection
    {
        return $this->get('belanja', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function rekenings(array $filters = []): SihandalCollection
    {
        return $this->get('rekenings', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function sumberDana(array $filters = []): SihandalCollection
    {
        return $this->get('sumber-dana', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function penerimaan(array $filters = []): SihandalCollection
    {
        return $this->get('penerimaan', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function pengeluaran(array $filters = []): SihandalCollection
    {
        return $this->get('pengeluaran', $filters);
    }

    /** @param array<string, mixed> $filters */
    public function tahunAnggaran(array $filters = []): SihandalCollection
    {
        return $this->get('tahun-anggaran', $filters);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ConnectionException when SIHANDAL is unreachable
     * @throws RequestException on API errors
     */
    public function syncDocument(array $payload): array
    {
        // Writes are never cached (spec §50): each sync must reach
        // the system of record exactly once per attempt.
        $response = Http::baseUrl((string) config('agent-finance.sihandal.base_url'))
            ->withToken((string) config('agent-finance.sihandal.api_key'))
            ->timeout((int) config('agent-finance.sihandal.timeout', 15))
            ->acceptJson()
            ->post('/api/v1/documents', $payload);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @throws ConnectionException when SIHANDAL is unreachable
     */
    /**
     * @param  array<string, mixed>  $filters
     *
     * @throws ConnectionException when SIHANDAL is unreachable
     */
    private function get(string $endpoint, array $filters): SihandalCollection
    {
        $ttl = (int) config('agent-finance.sihandal.cache_ttl', 300);

        return Cache::remember(
            'sihandal:'.md5($endpoint.serialize($filters)),
            $ttl,
            function () use ($endpoint, $filters): SihandalCollection {
                $response = Http::baseUrl((string) config('agent-finance.sihandal.base_url'))
                    ->withToken((string) config('agent-finance.sihandal.api_key'))
                    ->timeout((int) config('agent-finance.sihandal.timeout', 15))
                    ->acceptJson()
                    ->get('/api/v1/'.$endpoint, $filters);

                $response->throw();

                /** @var array{data?: array<int, array<string, mixed>>, meta?: array<string, mixed>} $payload */
                $payload = $response->json() ?? [];

                return new SihandalCollection(
                    items: $payload['data'] ?? [],
                    total: isset($payload['meta']['total']) ? (int) $payload['meta']['total'] : null,
                    page: isset($payload['meta']['current_page']) ? (int) $payload['meta']['current_page'] : null,
                    meta: $payload['meta'] ?? [],
                );
            },
        );
    }
}
